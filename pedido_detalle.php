<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$id_pedido = $_GET['id'] ?? 0;
if (!$id_pedido) {
    header('Location: pedidos.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Procesar agregar producto al pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product') {
        $id_producto = $_POST['id_producto'];
        $cantidad = $_POST['cantidad'];
        $notas_producto = $_POST['notas_producto'] ?? '';
        
        try {
            // Obtener precio del producto
            $stmt = $pdo->prepare("SELECT precio FROM Productos WHERE id_producto = ? AND disponibilidad = 1");
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
                
                $mensaje = "Producto agregado al pedido";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Producto no disponible";
                $tipo_mensaje = 'warning';
            }
            
        } catch (PDOException $e) {
            $mensaje = "Error al agregar producto: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
    
    if ($action === 'remove_product') {
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
    }
    
    if ($action === 'update_status') {
        $nuevo_estado = $_POST['estado'];
        
        try {
            $stmt = $pdo->prepare("UPDATE Pedidos SET estado = ? WHERE id_pedido = ?");
            $stmt->execute([$nuevo_estado, $id_pedido]);
            
            $mensaje = "Estado actualizado a: $nuevo_estado";
            $tipo_mensaje = 'success';
            
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar estado: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener información del pedido
try {
    $stmt = $pdo->prepare("
        SELECT p.*, m.numero_mesa, u.nombre as nombre_mesero 
        FROM Pedidos p 
        LEFT JOIN Mesas m ON p.id_mesa = m.id_mesa 
        LEFT JOIN Usuarios u ON p.id_mesero = u.id_usuario 
        WHERE p.id_pedido = ?
    ");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        header('Location: pedidos.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar pedido: " . $e->getMessage());
}

// Obtener productos del pedido
try {
    $stmt = $pdo->prepare("
        SELECT dp.*, pr.nombre as producto_nombre, pr.descripcion as producto_desc
        FROM DetallesPedido dp 
        JOIN Productos pr ON dp.id_producto = pr.id_producto 
        WHERE dp.id_pedido = ?
        ORDER BY dp.fecha_creacion
    ");
    $stmt->execute([$id_pedido]);
    $productos_pedido = $stmt->fetchAll();
} catch (PDOException $e) {
    $productos_pedido = [];
}

// Obtener productos disponibles
try {
    $stmt = $pdo->query("
        SELECT p.*, c.nombre_categoria 
        FROM Productos p 
        JOIN Categorias c ON p.id_categoria = c.id_categoria 
        WHERE p.disponibilidad = 1 
        ORDER BY c.nombre_categoria, p.nombre
    ");
    $productos_disponibles = $stmt->fetchAll();
} catch (PDOException $e) {
    $productos_disponibles = [];
}

// Agrupar productos por categoría
$productos_por_categoria = [];
foreach ($productos_disponibles as $producto) {
    $categoria = $producto['nombre_categoria'];
    if (!isset($productos_por_categoria[$categoria])) {
        $productos_por_categoria[$categoria] = [];
    }
    $productos_por_categoria[$categoria][] = $producto;
}

// Calcular totales
$total_productos = array_sum(array_column($productos_pedido, 'subtotal'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Pedido #<?php echo $id_pedido; ?> - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f94144;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .pedido-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .productos-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .product-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .product-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .product-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .product-item.selected {
            background-color: #e3f2fd;
            border-color: var(--primary);
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .status-pendiente { background-color: #fff3cd; color: #856404; }
        .status-preparando { background-color: #d1ecf1; color: #0c5460; }
        .status-listo { background-color: #d4edda; color: #155724; }
        .status-entregado { background-color: #cce5ff; color: #004085; }
        .status-cancelado { background-color: #f8d7da; color: #721c24; }
        
        .total-box {
            background: linear-gradient(135deg, var(--primary) 0%, #3a0ca3 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>
                <i class="bi bi-receipt me-2"></i>
                Pedido #<?php echo $id_pedido; ?>
            </h1>
            <a href="pedidos.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i> Volver a Pedidos
            </a>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Información del Pedido -->
        <div class="pedido-header">
            <div class="row">
                <div class="col-md-6">
                    <h4>Información del Pedido</h4>
                    <div class="row mt-3">
                        <div class="col-6">
                            <p class="mb-1"><strong>Mesa:</strong></p>
                            <h5>Mesa <?php echo $pedido['numero_mesa']; ?></h5>
                        </div>
                        <div class="col-6">
                            <p class="mb-1"><strong>Mesero:</strong></p>
                            <h5><?php echo htmlspecialchars($pedido['nombre_mesero']); ?></h5>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <p class="mb-1"><strong>Fecha y Hora:</strong></p>
                            <p><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_hora'])); ?></p>
                        </div>
                        <div class="col-6">
                            <p class="mb-1"><strong>Estado:</strong></p>
                            <?php
                            $color_estado = match($pedido['estado']) {
                                'Pendiente' => 'status-pendiente',
                                'Preparando' => 'status-preparando',
                                'Listo' => 'status-listo',
                                'Entregado' => 'status-entregado',
                                'Cancelado' => 'status-cancelado',
                                default => 'status-pendiente'
                            };
                            ?>
                            <span class="status-badge <?php echo $color_estado; ?>">
                                <?php echo $pedido['estado']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Cambiar Estado (solo para pedidos no finalizados) -->
                    <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="action" value="update_status">
                        <div class="row">
                            <div class="col-md-8">
                                <select class="form-select" name="estado" required>
                                    <option value="Pendiente" <?php echo $pedido['estado'] == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Preparando" <?php echo $pedido['estado'] == 'Preparando' ? 'selected' : ''; ?>>Preparando</option>
                                    <option value="Listo" <?php echo $pedido['estado'] == 'Listo' ? 'selected' : ''; ?>>Listo</option>
                                    <option value="Entregado">Entregado</option>
                                    <option value="Cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    Cambiar Estado
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <h4>Totales</h4>
                    <div class="total-box">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($pedido['subtotal'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Impuesto (16%):</span>
                            <span>$<?php echo number_format($pedido['impuesto'], 2); ?></span>
                        </div>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-0">Total:</h5>
                            <h3 class="mb-0">$<?php echo number_format($pedido['total'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Productos en el Pedido -->
        <div class="productos-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Productos en el Pedido</h4>
                <span class="badge bg-primary">
                    <?php echo count($productos_pedido); ?> productos
                </span>
            </div>
            
            <?php if (count($productos_pedido) > 0): ?>
                <?php foreach ($productos_pedido as $producto): ?>
                <div class="product-card">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-1"><?php echo htmlspecialchars($producto['producto_nombre']); ?></h6>
                            <?php if ($producto['notas']): ?>
                            <small class="text-muted">Notas: <?php echo htmlspecialchars($producto['notas']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="badge bg-secondary">
                                x<?php echo $producto['cantidad']; ?>
                            </span>
                        </div>
                        <div class="col-md-2 text-end">
                            <strong>$<?php echo number_format($producto['precio_unitario'], 2); ?></strong>
                        </div>
                        <div class="col-md-2 text-end">
                            <strong>$<?php echo number_format($producto['subtotal'], 2); ?></strong>
                        </div>
                    </div>
                    
                    <!-- Botón para eliminar producto (solo si pedido no finalizado) -->
                    <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
                    <div class="mt-2 text-end">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="remove_product">
                            <input type="hidden" name="id_detalle" value="<?php echo $producto['id_detalle']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                    onclick="return confirm('¿Eliminar este producto del pedido?')">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">No hay productos en este pedido</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Agregar Productos (solo si pedido no finalizado) -->
        <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
        <div class="productos-container">
            <h4>Agregar Productos</h4>
            
            <!-- Formulario para agregar producto -->
            <form method="POST" id="formAgregarProducto" class="mb-4">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="id_producto" id="input_id_producto" required>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Producto Seleccionado</label>
                        <div id="producto_seleccionado" class="form-control" style="min-height: 38px; background-color: #f8f9fa;">
                            <span class="text-muted">Seleccione un producto de la lista</span>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="cantidad" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" 
                               min="1" max="20" value="1" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="notas_producto" class="form-label">Notas (Opcional)</label>
                        <input type="text" class="form-control" id="notas_producto" name="notas_producto" 
                               placeholder="Ej: Sin picante, bien cocido, etc.">
                    </div>
                    
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i> Agregar
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Lista de productos disponibles -->
            <?php if (count($productos_por_categoria) > 0): ?>
                <?php foreach ($productos_por_categoria as $categoria => $productos): ?>
                <h5 class="mt-4 mb-3"><?php echo htmlspecialchars($categoria); ?></h5>
                <div class="product-grid">
                    <?php foreach ($productos as $producto): ?>
                    <div class="product-item" 
                         data-id="<?php echo $producto['id_producto']; ?>"
                         data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         data-precio="<?php echo $producto['precio']; ?>"
                         onclick="seleccionarProducto(this)">
                        <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                        <p class="text-muted small mb-2">
                            <?php echo htmlspecialchars(substr($producto['descripcion'], 0, 60)); ?>...
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-info">
                                <?php echo $producto['tiempo_preparacion']; ?> min
                            </span>
                            <strong>$<?php echo number_format($producto['precio'], 2); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-cup-straw text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">No hay productos disponibles</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Botones de acción -->
        <div class="d-flex justify-content-between mt-4">
            <a href="pedidos.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver a Pedidos
            </a>
            
            <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
            <div>
                <button class="btn btn-success" onclick="imprimirTicket()">
                    <i class="bi bi-printer me-1"></i> Imprimir Ticket
                </button>
                <a href="pagar_pedido.php?id=<?php echo $id_pedido; ?>" class="btn btn-primary ms-2">
                    <i class="bi bi-cash-coin me-1"></i> Procesar Pago
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let productoSeleccionado = null;
        
        function seleccionarProducto(elemento) {
            // Remover selección anterior
            document.querySelectorAll('.product-item.selected').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Agregar selección nueva
            elemento.classList.add('selected');
            productoSeleccionado = elemento;
            
            // Actualizar formulario
            document.getElementById('input_id_producto').value = elemento.getAttribute('data-id');
            document.getElementById('producto_seleccionado').innerHTML = `
                <strong>${elemento.getAttribute('data-nombre')}</strong><br>
                <small>$${parseFloat(elemento.getAttribute('data-precio')).toFixed(2)}</small>
            `;
        }
        
        function imprimirTicket() {
            // Aquí podrías implementar la funcionalidad de impresión
            alert('Funcionalidad de impresión en desarrollo');
        }
        
        // Validar formulario
        document.getElementById('formAgregarProducto').addEventListener('submit', function(e) {
            if (!productoSeleccionado) {
                e.preventDefault();
                alert('Por favor, seleccione un producto de la lista');
                return false;
            }
        });
        
        // Auto-refresh cada 30 segundos
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>