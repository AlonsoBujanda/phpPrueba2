<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$roles_permitidos = ['Administrador', 'Gerente', 'Mesero'];
if (!in_array($rol, $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Obtener mesas disponibles
$mesas = [];
try {
    $stmt = $pdo->query("SELECT * FROM Mesas ORDER BY numero_mesa");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar mesas: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener productos disponibles
$productos = [];
try {
    $stmt = $pdo->query("SELECT p.*, c.nombre_categoria 
                         FROM Productos p 
                         JOIN Categorias c ON p.id_categoria = c.id_categoria 
                         WHERE p.disponibilidad = 1 
                         ORDER BY c.nombre_categoria, p.nombre");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar productos: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Agrupar productos por categoría
$productos_por_categoria = [];
foreach ($productos as $producto) {
    $categoria = $producto['nombre_categoria'];
    if (!isset($productos_por_categoria[$categoria])) {
        $productos_por_categoria[$categoria] = [];
    }
    $productos_por_categoria[$categoria][] = $producto;
}

// CRUD Operations para pedidos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $id_mesa = $_POST['id_mesa'];
            $notas = $_POST['notas'] ?? '';
            $id_mesero = $_SESSION['user_id'];
            
            try {
                // Crear pedido
                $stmt = $pdo->prepare("INSERT INTO Pedidos (id_mesa, id_mesero, notas) VALUES (?, ?, ?)");
                $stmt->execute([$id_mesa, $id_mesero, $notas]);
                $id_pedido = $pdo->lastInsertId();
                
                // Actualizar estado de mesa
                $stmt = $pdo->prepare("UPDATE Mesas SET estado = 'Ocupada' WHERE id_mesa = ?");
                $stmt->execute([$id_mesa]);
                
                $mensaje = "Pedido #$id_pedido creado exitosamente";
                $tipo_mensaje = 'success';
                
                // Redirigir a detalles del pedido
                header("Location: pedido_detalle.php?id=$id_pedido");
                exit;
                
            } catch (PDOException $e) {
                $mensaje = "Error al crear pedido: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'update_status':
            $id_pedido = $_POST['id_pedido'];
            $nuevo_estado = $_POST['estado'];
            
            try {
                $stmt = $pdo->prepare("UPDATE Pedidos SET estado = ? WHERE id_pedido = ?");
                $stmt->execute([$nuevo_estado, $id_pedido]);
                
                // Si el pedido se marca como entregado o cancelado, verificar si liberar mesa
                if (in_array($nuevo_estado, ['Entregado', 'Cancelado'])) {
                    // Verificar si hay otros pedidos activos en la misma mesa
                    $stmt = $pdo->prepare("SELECT id_mesa FROM Pedidos WHERE id_pedido = ?");
                    $stmt->execute([$id_pedido]);
                    $id_mesa = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Pedidos 
                                          WHERE id_mesa = ? AND estado NOT IN ('Entregado', 'Cancelado')");
                    $stmt->execute([$id_mesa]);
                    $pedidos_activos = $stmt->fetchColumn();
                    
                    if ($pedidos_activos == 0) {
                        $stmt = $pdo->prepare("UPDATE Mesas SET estado = 'Libre' WHERE id_mesa = ?");
                        $stmt->execute([$id_mesa]);
                    }
                }
                
                $mensaje = "Estado del pedido actualizado a: $nuevo_estado";
                $tipo_mensaje = 'success';
                
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar estado: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'add_product':
            $id_pedido = $_POST['id_pedido'];
            $id_producto = $_POST['id_producto'];
            $cantidad = $_POST['cantidad'];
            $notas_producto = $_POST['notas_producto'] ?? '';
            
            try {
                // Obtener precio del producto
                $stmt = $pdo->prepare("SELECT precio FROM Productos WHERE id_producto = ?");
                $stmt->execute([$id_producto]);
                $producto = $stmt->fetch();
                
                if ($producto) {
                    $precio_unitario = $producto['precio'];
                    $subtotal = $precio_unitario * $cantidad;
                    
                    // Agregar producto al pedido
                    $stmt = $pdo->prepare("INSERT INTO DetallesPedido 
                        (id_pedido, id_producto, cantidad, precio_unitario, subtotal, notas) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_pedido, $id_producto, $cantidad, $precio_unitario, $subtotal, $notas_producto]);
                    
                    // El trigger actualizará automáticamente el total del pedido
                    $mensaje = "Producto agregado al pedido";
                    $tipo_mensaje = 'success';
                }
                
            } catch (PDOException $e) {
                $mensaje = "Error al agregar producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'remove_product':
            $id_detalle = $_POST['id_detalle'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM DetallesPedido WHERE id_detalle = ?");
                $stmt->execute([$id_detalle]);
                
                $mensaje = "Producto eliminado del pedido";
                $tipo_mensaje = 'success';
                
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener todos los pedidos
try {
    $stmt = $pdo->query("
        SELECT p.*, m.numero_mesa, u.nombre as nombre_mesero 
        FROM Pedidos p 
        LEFT JOIN Mesas m ON p.id_mesa = m.id_mesa 
        LEFT JOIN Usuarios u ON p.id_mesero = u.id_usuario 
        ORDER BY p.fecha_hora DESC
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pedidos = [];
    $mensaje = "Error al cargar pedidos: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener estadísticas
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
    SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
    SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados,
    SUM(CASE WHEN estado = 'Cancelado' THEN 1 ELSE 0 END) as cancelados,
    SUM(total) as total_ventas
    FROM Pedidos WHERE DATE(fecha_hora) = CURDATE()");
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f94144;
            --secondary: #7209b7;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, #3a0ca3 100%);
            color: white;
            min-height: 100vh;
            width: 250px;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pendiente { background-color: #fff3cd; color: #856404; }
        .status-preparando { background-color: #d1ecf1; color: #0c5460; }
        .status-listo { background-color: #d4edda; color: #155724; }
        .status-entregado { background-color: #cce5ff; color: #004085; }
        .status-cancelado { background-color: #f8d7da; color: #721c24; }
        
        .btn-action {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .pedido-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .product-card.selected {
            background-color: #e3f2fd;
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-none d-md-block">
        <div class="text-center mb-4">
            <h3 class="mb-1">🍽️</h3>
            <h5 class="mb-0">Sistema Restaurante</h5>
            <small>Gestión de Pedidos</small>
        </div>
        
        <div class="px-3 mb-4">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" 
                     style="width: 40px; height: 40px;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="ms-3">
                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></h6>
                    <small><?php echo htmlspecialchars($rol); ?></small>
                </div>
            </div>
        </div>
        
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="mesas.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-table me-2"></i> Mesas
            </a>
            <?php if (in_array($rol, ['Administrador', 'Gerente'])): ?>
            <a href="productos.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-cup-straw me-2"></i> Productos
            </a>
            <?php endif; ?>
            <a href="pedidos.php" class="nav-link text-white py-2 px-3 bg-white bg-opacity-10 rounded">
                <i class="bi bi-receipt me-2"></i> Pedidos
            </a>
            <a href="logout.php" class="nav-link text-white py-2 px-3 text-danger">
                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-receipt me-2"></i> Gestión de Pedidos</h2>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoPedido">
                    <i class="bi bi-plus-circle me-1"></i> Nuevo Pedido
                </button>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-1">Total Hoy</h6>
                    <h3 class="mb-0"><?php echo $estadisticas['total'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-1">Pendientes</h6>
                    <h3 class="mb-0 text-warning"><?php echo $estadisticas['pendientes'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-1">Preparando</h6>
                    <h3 class="mb-0 text-info"><?php echo $estadisticas['preparando'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-1">Listos</h6>
                    <h3 class="mb-0 text-primary"><?php echo $estadisticas['listos'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h6 class="text-muted mb-1">Ventas Hoy</h6>
                    <h4 class="mb-0 text-success">
                        $<?php echo number_format($estadisticas['total_ventas'] ?? 0, 2); ?>
                    </h4>
                </div>
            </div>
        </div>
        
        <!-- Lista de Pedidos -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mesa</th>
                            <th>Mesero</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Hora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pedidos) > 0): ?>
                            <?php foreach ($pedidos as $pedido): 
                                $color_estado = match($pedido['estado']) {
                                    'Pendiente' => 'status-pendiente',
                                    'Preparando' => 'status-preparando',
                                    'Listo' => 'status-listo',
                                    'Entregado' => 'status-entregado',
                                    'Cancelado' => 'status-cancelado',
                                    default => 'status-pendiente'
                                };
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $pedido['id_pedido']; ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-table me-1"></i>
                                    Mesa <?php echo $pedido['numero_mesa']; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($pedido['nombre_mesero'] ?? 'Sin asignar'); ?></small>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($pedido['total'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $color_estado; ?>">
                                        <?php echo $pedido['estado']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('H:i', strtotime($pedido['fecha_hora'])); ?>
                                    <br><small><?php echo date('d/m', strtotime($pedido['fecha_hora'])); ?></small>
                                </td>
                                <td>
                                    <a href="pedido_detalle.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                       class="btn btn-sm btn-outline-primary btn-action">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    
                                    <?php if ($pedido['estado'] !== 'Entregado' && $pedido['estado'] !== 'Cancelado'): ?>
                                    <button class="btn btn-sm btn-outline-warning btn-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalCambiarEstado"
                                            data-id="<?php echo $pedido['id_pedido']; ?>"
                                            data-estado="<?php echo $pedido['estado']; ?>">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-receipt text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No hay pedidos registrados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Nuevo Pedido -->
    <div class="modal fade" id="modalNuevoPedido" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="id_mesa" class="form-label">Seleccionar Mesa *</label>
                            <select class="form-select" id="id_mesa" name="id_mesa" required>
                                <option value="">Seleccionar mesa...</option>
                                <?php foreach ($mesas as $mesa): 
                                    $disabled = $mesa['estado'] === 'Ocupada' ? 'disabled' : '';
                                    $status_text = $mesa['estado'] === 'Ocupada' ? ' (Ocupada)' : '';
                                ?>
                                <option value="<?php echo $mesa['id_mesa']; ?>" <?php echo $disabled; ?>>
                                    Mesa <?php echo $mesa['numero_mesa']; ?> - 
                                    <?php echo $mesa['capacidad']; ?> personas<?php echo $status_text; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo se muestran mesas disponibles</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notas" class="form-label">Notas del Pedido (Opcional)</label>
                            <textarea class="form-control" id="notas" name="notas" rows="2" 
                                      placeholder="Ej: Sin picante, salsa aparte, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Pedido</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Cambiar Estado -->
    <div class="modal fade" id="modalCambiarEstado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado del Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id_pedido" id="estado_id_pedido">
                        
                        <div class="mb-3">
                            <label for="estado" class="form-label">Nuevo Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="Pendiente">Pendiente</option>
                                <option value="Preparando">Preparando</option>
                                <option value="Listo">Listo</option>
                                <option value="Entregado">Entregado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>
                        
                        <div id="estado_actual" class="alert alert-info">
                            Estado actual: <strong id="estado_actual_text"></strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Cambiar Estado
            const modalCambiarEstado = document.getElementById('modalCambiarEstado');
            modalCambiarEstado.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const estado = button.getAttribute('data-estado');
                
                document.getElementById('estado_id_pedido').value = id;
                document.getElementById('estado').value = estado;
                document.getElementById('estado_actual_text').textContent = estado;
            });
            
            // Auto-refresh cada 30 segundos
            setTimeout(function() {
                window.location.reload();
            }, 30000);
            
            // Filtrar mesas disponibles
            const selectMesa = document.getElementById('id_mesa');
            if (selectMesa) {
                Array.from(selectMesa.options).forEach(option => {
                    if (option.disabled) {
                        option.style.color = '#dc3545';
                    }
                });
            }
        });
    </script>
</body>
</html>