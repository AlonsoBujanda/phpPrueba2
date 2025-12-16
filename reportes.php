<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$roles_permitidos = ['Administrador', 'Gerente'];
if (!in_array($rol, $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Parámetros de fecha
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Reporte de ventas por día
$ventas_por_dia = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(fecha_hora) as fecha,
            COUNT(*) as total_pedidos,
            SUM(total) as venta_total,
            AVG(total) as promedio_venta
        FROM Pedidos
        WHERE DATE(fecha_hora) BETWEEN ? AND ?
        AND estado != 'Cancelado'
        GROUP BY DATE(fecha_hora)
        ORDER BY fecha DESC
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $ventas_por_dia = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar ventas por día: " . $e->getMessage();
}

// Productos más vendidos
$productos_mas_vendidos = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.nombre,
            c.nombre_categoria,
            SUM(dp.cantidad) as total_vendido,
            SUM(dp.subtotal) as ingresos_totales
        FROM DetallesPedido dp
        INNER JOIN Productos p ON dp.id_producto = p.id_producto
        INNER JOIN Categorias c ON p.id_categoria = c.id_categoria
        INNER JOIN Pedidos pd ON dp.id_pedido = pd.id_pedido
        WHERE DATE(pd.fecha_hora) BETWEEN ? AND ?
        AND pd.estado != 'Cancelado'
        GROUP BY p.id_producto
        ORDER BY total_vendido DESC
        LIMIT 10
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $productos_mas_vendidos = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar productos más vendidos: " . $e->getMessage();
}

// Estadísticas generales
$estadisticas = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_pedidos,
            SUM(total) as ventas_totales,
            AVG(total) as promedio_por_pedido,
            MIN(total) as venta_minima,
            MAX(total) as venta_maxima,
            COUNT(DISTINCT id_mesa) as mesas_utilizadas
        FROM Pedidos
        WHERE DATE(fecha_hora) BETWEEN ? AND ?
        AND estado != 'Cancelado'
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $estadisticas = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .report-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: 300px;
        }
        
        .table-report {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filtros {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-graph-up me-3"></i> Reportes y Estadísticas</h1>
                    <p class="mb-0">Análisis de ventas y desempeño del restaurante</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filtros de fecha -->
        <div class="filtros">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Estadísticas generales -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-2">Pedidos Totales</h6>
                    <h2 class="text-primary"><?php echo $estadisticas['total_pedidos'] ?? 0; ?></h2>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-2">Ventas Totales</h6>
                    <h3 class="text-success">
                        $<?php echo number_format($estadisticas['ventas_totales'] ?? 0, 2); ?>
                    </h3>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-2">Promedio por Pedido</h6>
                    <h3 class="text-info">
                        $<?php echo number_format($estadisticas['promedio_por_pedido'] ?? 0, 2); ?>
                    </h3>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-2">Mesas Utilizadas</h6>
                    <h2 class="text-warning"><?php echo $estadisticas['mesas_utilizadas'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de ventas -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="chart-container">
                    <h5>Ventas por Día</h5>
                    <canvas id="ventasChart"></canvas>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="chart-container">
                    <h5>Distribución por Categoría</h5>
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tabla de ventas por día -->
        <div class="table-report mb-4">
            <h5 class="mb-3">Ventas por Día</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Pedidos</th>
                            <th>Ventas Totales</th>
                            <th>Promedio por Pedido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas_por_dia) > 0): ?>
                            <?php foreach ($ventas_por_dia as $venta): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></td>
                                <td><?php echo $venta['total_pedidos']; ?></td>
                                <td>$<?php echo number_format($venta['venta_total'], 2); ?></td>
                                <td>$<?php echo number_format($venta['promedio_venta'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-3">No hay datos para el período seleccionado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Productos más vendidos -->
        <div class="table-report">
            <h5 class="mb-3">Productos Más Vendidos</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Cantidad Vendida</th>
                            <th>Ingresos Totales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos_mas_vendidos) > 0): ?>
                            <?php foreach ($productos_mas_vendidos as $producto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($producto['nombre_categoria']); ?>
                                    </span>
                                </td>
                                <td><?php echo $producto['total_vendido']; ?></td>
                                <td>$<?php echo number_format($producto['ingresos_totales'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-3">No hay datos para el período seleccionado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Botones de exportación -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i> Exportar a Excel
                </button>
                <button class="btn btn-danger ms-3" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Imprimir Reporte
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Datos para gráficos
        const ventasData = {
            fechas: [<?php echo implode(',', array_map(function($v) { 
                return "'" . date('d/m', strtotime($v['fecha'])) . "'"; 
            }, $ventas_por_dia)); ?>],
            ventas: [<?php echo implode(',', array_column($ventas_por_dia, 'venta_total')); ?>]
        };
        
        // Gráfico de ventas
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctxVentas, {
            type: 'bar',
            data: {
                labels: ventasData.fechas,
                datasets: [{
                    label: 'Ventas ($)',
                    data: ventasData.ventas,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico de categorías (datos de ejemplo)
        const ctxCategorias = document.getElementById('categoriasChart').getContext('2d');
        new Chart(ctxCategorias, {
            type: 'doughnut',
            data: {
                labels: ['Entradas', 'Platos Fuertes', 'Postres', 'Bebidas'],
                datasets: [{
                    data: [25, 40, 20, 15],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        
        // Exportar a Excel (simplificado)
        function exportToExcel() {
            alert('La exportación a Excel se implementaría con una librería como SheetJS');
            // En un sistema real, aquí iría el código para generar un archivo Excel
        }
        
        // Auto-refresh cada 5 minutos
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>