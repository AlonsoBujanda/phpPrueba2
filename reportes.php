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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ================================
           VARIABLES Y RESET
           ================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Colores principales - Tema sofisticado de restaurante (AZUL INTENSO) */
            --color-primary: #003366; /* Azul Marino Oscuro Clásico */
            --color-secondary: #00aaff; /* Azul Cielo Brillante (Acento vibrante) */
            --color-accent: #3498db; /* Azul Medio Intenso */
            --color-background: #f0f8ff; /* Blanco/Azul Claro Suave (Azure) */
            --color-surface: #ffffff; /* Blanco Puro */

            /* Tonos oscuros */
            --color-dark: #002244; /* Azul Extra Oscuro */
            --color-dark-gray: #1a4f80; /* Azul Grisáceo Oscuro */

            /* Tonos claros */
            --color-light-gray: #cceeff; /* Azul Ultra Claro (Para fondos/bordes) */
            --color-border: #99ccff; /* Azul de Borde Claro */

            /* Texto */
            --color-text-primary: #003366; /* Azul principal para texto principal */
            --color-text-secondary: #557a95; /* Gris Azulado para texto secundario */
            --color-text-light: #ffffff; /* Blanco */

            /* Estados */
            --color-success: #2ecc71; /* Verde */
            --color-error: #e74c3c; /* Rojo */
            --color-warning: #f39c12; /* Naranja/Amarillo */
            --color-info: #3498db; /* Azul informativo */

            /* Tipografía */
            --font-display: "Playfair Display", serif;
            --font-body: "Inter", sans-serif;

            /* Espaciado */
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 1.5rem;
            --spacing-lg: 2rem;
            --spacing-xl: 3rem;

            /* Bordes */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;

            /* Sombras */
            --shadow-sm: 0 1px 3px rgba(0, 51, 102, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 51, 102, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 51, 102, 0.15);
        }

        body {
            font-family: var(--font-body);
            color: var(--color-text-primary);
            background-color: var(--color-background);
            line-height: 1.6;
            font-size: 15px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }

        /* ================================
           PÁGINA DE REPORTES
           ================================ */
        .report-page {
            min-height: 100vh;
        }

        /* Header de Reportes */
        .report-header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
            color: var(--color-text-light);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        .report-header h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }

        .report-header p {
            color: var(--color-light-gray);
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        /* Filtros */
        .filtros-container {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
        }

        .form-label {
            font-weight: 500;
            color: var(--color-text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-family: var(--font-body);
            transition: all 0.3s ease;
            background-color: var(--color-background);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.3);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            font-family: var(--font-body);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--color-primary);
            color: var(--color-text-light);
        }

        .btn-primary:hover {
            background-color: var(--color-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-light {
            background-color: var(--color-text-light);
            color: var(--color-primary);
        }

        .btn-light:hover {
            background-color: var(--color-light-gray);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background-color: var(--color-success);
            color: var(--color-text-light);
        }

        .btn-success:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background-color: var(--color-error);
            color: var(--color-text-light);
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Estadísticas Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }

        .stat-card {
            background: var(--color-surface);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px -15px rgba(0, 51, 102, 0.2);
        }

        .stat-card h2, .stat-card h3 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: var(--color-text-secondary);
            font-size: 0.875rem;
            margin: 0;
        }

        /* Chart Containers */
        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        @media (max-width: 992px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            height: 350px;
            position: relative;
        }

        .chart-card h5 {
            font-family: var(--font-display);
            color: var(--color-primary);
            margin-bottom: var(--spacing-md);
            font-weight: 600;
        }

        /* Tables */
        .table-container {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
        }

        .table-container h5 {
            font-family: var(--font-display);
            color: var(--color-primary);
            margin-bottom: var(--spacing-md);
            font-weight: 600;
        }

        .table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .table thead th {
            background-color: var(--color-background);
            color: var(--color-text-primary);
            font-weight: 600;
            padding: var(--spacing-md);
            border-bottom: 2px solid var(--color-border);
            text-align: left;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--color-border);
        }

        .table tbody tr:hover {
            background-color: rgba(204, 238, 255, 0.1);
        }

        .table tbody td {
            padding: var(--spacing-md);
            color: var(--color-text-primary);
            vertical-align: middle;
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge.bg-info {
            background-color: var(--color-info) !important;
        }

        /* Colores para estadísticas */
        .text-primary { color: var(--color-primary); }
        .text-success { color: var(--color-success); }
        .text-info { color: var(--color-info); }
        .text-warning { color: var(--color-warning); }

        /* Exportación y Acciones */
        .export-actions {
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--color-border);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
        }

        .empty-state p {
            color: var(--color-text-secondary);
            margin-bottom: 0;
        }

        /* ================================
           RESPONSIVE
           ================================ */
        @media (max-width: 768px) {
            .container-fluid {
                padding: var(--spacing-md);
            }

            .report-header {
                padding: var(--spacing-lg);
            }

            .report-header h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filtros-container {
                padding: var(--spacing-md);
            }

            .table-container {
                padding: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .report-header h1 {
                font-size: 1.75rem;
            }

            .report-header .row {
                flex-direction: column;
                gap: var(--spacing-sm);
            }

            .report-header .text-end {
                text-align: left !important;
            }

            .table {
                font-size: 0.875rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.75rem;
            }

            .chart-card {
                height: 300px;
                padding: var(--spacing-md);
            }
        }

        /* Print Styles */
        @media print {
            .btn,
            .filtros-container,
            .export-actions {
                display: none !important;
            }

            body {
                background-color: white;
            }

            .report-header {
                box-shadow: none;
                border: 1px solid var(--color-border);
            }

            .stat-card,
            .chart-card,
            .table-container {
                box-shadow: none;
                border: 1px solid var(--color-border);
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="report-page">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-graph-up me-3"></i> Reportes y Estadísticas</h1>
                    <p>Análisis de ventas y desempeño del restaurante</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="bi bi-house"></i> Menu Principal
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filtros de fecha -->
        <div class="filtros-container">
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
        <div class="stats-grid">
            <div class="stat-card">
                <h2 class="text-primary"><?php echo $estadisticas['total_pedidos'] ?? 0; ?></h2>
                <p>Pedidos Totales</p>
            </div>
            
            <div class="stat-card">
                <h3 class="text-success">
                    $<?php echo number_format($estadisticas['ventas_totales'] ?? 0, 2); ?>
                </h3>
                <p>Ventas Totales</p>
            </div>
            
            <div class="stat-card">
                <h3 class="text-info">
                    $<?php echo number_format($estadisticas['promedio_por_pedido'] ?? 0, 2); ?>
                </h3>
                <p>Promedio por Pedido</p>
            </div>
            
            <div class="stat-card">
                <h2 class="text-warning"><?php echo $estadisticas['mesas_utilizadas'] ?? 0; ?></h2>
                <p>Mesas Utilizadas</p>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="charts-container">
            <div class="chart-card">
                <h5>Ventas por Día</h5>
                <canvas id="ventasChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h5>Distribución por Categoría</h5>
                <canvas id="categoriasChart"></canvas>
            </div>
        </div>
        
        <!-- Tabla de ventas por día -->
        <div class="table-container">
            <h5>Ventas por Día</h5>
            <div class="table-responsive">
                <table class="table">
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
                                <td><strong>$<?php echo number_format($venta['venta_total'], 2); ?></strong></td>
                                <td>$<?php echo number_format($venta['promedio_venta'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="empty-state">
                                        <p>No hay datos para el período seleccionado</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Productos más vendidos -->
        <div class="table-container">
            <h5>Productos Más Vendidos</h5>
            <div class="table-responsive">
                <table class="table">
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
                                <td><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($producto['nombre_categoria']); ?>
                                    </span>
                                </td>
                                <td><?php echo $producto['total_vendido']; ?></td>
                                <td><strong>$<?php echo number_format($producto['ingresos_totales'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="empty-state">
                                        <p>No hay datos para el período seleccionado</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Botones de exportación -->
        <div class="export-actions text-center">
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar a Excel
            </button>
            <button class="btn btn-danger ms-3" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimir Reporte
            </button>
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
        const ventasChart = new Chart(ctxVentas, {
            type: 'bar',
            data: {
                labels: ventasData.fechas,
                datasets: [{
                    label: 'Ventas ($)',
                    data: ventasData.ventas,
                    backgroundColor: 'rgba(0, 51, 102, 0.7)',
                    borderColor: 'rgba(0, 51, 102, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#003366',
                            font: {
                                family: 'Inter, sans-serif'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#557a95',
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-ES');
                            }
                        },
                        grid: {
                            color: 'rgba(0, 51, 102, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#557a95'
                        },
                        grid: {
                            color: 'rgba(0, 51, 102, 0.1)'
                        }
                    }
                }
            }
        });
        
        // Gráfico de categorías (datos de ejemplo)
        const ctxCategorias = document.getElementById('categoriasChart').getContext('2d');
        const categoriasChart = new Chart(ctxCategorias, {
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
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#003366',
                            font: {
                                family: 'Inter, sans-serif',
                                size: 12
                            }
                        }
                    }
                }
            }
        });
        
        // Exportar a Excel (simplificado)
        function exportToExcel() {
            // Crear contenido HTML de la tabla
            let tableHTML = `
                <table border="1">
                    <tr>
                        <th colspan="4" style="background-color: #003366; color: white; padding: 10px; font-size: 16px;">
                            Reporte de Ventas - <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                        </th>
                    </tr>
                    <tr>
                        <th>Fecha</th>
                        <th>Pedidos</th>
                        <th>Ventas Totales</th>
                        <th>Promedio por Pedido</th>
                    </tr>
            `;
            
            <?php foreach ($ventas_por_dia as $venta): ?>
            tableHTML += `
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></td>
                    <td><?php echo $venta['total_pedidos']; ?></td>
                    <td>$<?php echo number_format($venta['venta_total'], 2); ?></td>
                    <td>$<?php echo number_format($venta['promedio_venta'], 2); ?></td>
                </tr>
            `;
            <?php endforeach; ?>
            
            tableHTML += '</table>';
            
            // Crear archivo Excel simple
            const blob = new Blob([tableHTML], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reporte_ventas_<?php echo $fecha_inicio; ?>_<?php echo $fecha_fin; ?>.xls`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Auto-refresh cada 5 minutos
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>