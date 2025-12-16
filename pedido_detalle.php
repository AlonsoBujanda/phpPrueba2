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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }

        /* Header */
        .page-header {
            margin-bottom: var(--spacing-xl);
        }

        .page-header h1 {
            font-family: var(--font-display);
            font-size: 2.25rem;
            color: var(--color-primary);
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

        .btn-outline-primary {
            background-color: transparent;
            color: var(--color-primary);
            border: 1px solid var(--color-primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--color-primary);
            color: var(--color-text-light);
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

        .btn-outline-secondary {
            background-color: transparent;
            color: var(--color-text-secondary);
            border: 1px solid var(--color-text-secondary);
        }

        .btn-outline-secondary:hover {
            background-color: var(--color-text-secondary);
            color: var(--color-text-light);
        }

        .btn-outline-danger {
            background-color: transparent;
            color: var(--color-error);
            border: 1px solid var(--color-error);
        }

        .btn-outline-danger:hover {
            background-color: var(--color-error);
            color: var(--color-text-light);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        /* Alertas */
        .alert {
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border: 1px solid transparent;
            font-size: 0.95rem;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border-color: var(--color-success);
            color: #155724;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-color: var(--color-error);
            color: #721c24;
        }

        .alert-warning {
            background-color: rgba(243, 156, 18, 0.1);
            border-color: var(--color-warning);
            color: #856404;
        }

        /* Cards y Contenedores */
        .pedido-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
        }

        .productos-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
        }

        .total-box {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
            color: var(--color-text-light);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pendiente {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--color-warning);
            border: 1px solid rgba(243, 156, 18, 0.3);
        }

        .status-preparando {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--color-info);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .status-listo {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--color-success);
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .status-entregado {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            border: 1px solid rgba(155, 89, 182, 0.3);
        }

        .status-cancelado {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--color-error);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        /* Productos en Pedido */
        .product-item-card {
            background: var(--color-background);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-sm);
            border-left: 4px solid var(--color-primary);
            transition: all 0.3s ease;
        }

        .product-item-card:hover {
            background-color: rgba(204, 238, 255, 0.2);
        }

        /* Grid de Productos Disponibles */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }

        .product-select-card {
            background: var(--color-surface);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
        }

        .product-select-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-accent);
        }

        .product-select-card.selected {
            background-color: rgba(0, 51, 102, 0.05);
            border-color: var(--color-primary);
        }

        /* Badges */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge.bg-primary {
            background-color: var(--color-primary) !important;
        }

        .badge.bg-secondary {
            background-color: var(--color-text-secondary) !important;
        }

        .badge.bg-info {
            background-color: var(--color-accent) !important;
        }

        /* Formularios */
        .form-label {
            font-weight: 500;
            color: var(--color-text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control, .form-select {
            padding: 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-family: var(--font-body);
            transition: all 0.3s ease;
            background-color: var(--color-background);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
        }

        .empty-state-icon {
            font-size: 3rem;
            color: var(--color-light-gray);
            margin-bottom: var(--spacing-md);
        }

        /* ================================
           RESPONSIVE
           ================================ */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-md);
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }

            .pedido-card,
            .productos-card {
                padding: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .product-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: var(--spacing-sm);
            }

            .page-header h1 {
                font-size: 1.5rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center page-header">
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
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Información del Pedido -->
        <div class="pedido-card">
            <div class="row">
                <div class="col-lg-6">
                    <h4 class="mb-4">Información del Pedido</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1"><strong>Mesa:</strong></p>
                            <h5 class="text-primary">Mesa <?php echo $pedido['numero_mesa']; ?></h5>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1"><strong>Mesero:</strong></p>
                            <h5><?php echo htmlspecialchars($pedido['nombre_mesero']); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1"><strong>Fecha y Hora:</strong></p>
                            <p class="text-muted"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_hora'])); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
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
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="action" value="update_status">
                        <div class="row align-items-end">
                            <div class="col-md-8">
                                <label for="estado" class="form-label">Cambiar Estado</label>
                                <select class="form-select" id="estado" name="estado" required>
                                    <option value="Pendiente" <?php echo $pedido['estado'] == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Preparando" <?php echo $pedido['estado'] == 'Preparando' ? 'selected' : ''; ?>>Preparando</option>
                                    <option value="Listo" <?php echo $pedido['estado'] == 'Listo' ? 'selected' : ''; ?>>Listo</option>
                                    <option value="Entregado">Entregado</option>
                                    <option value="Cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-repeat me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-6 mt-4 mt-lg-0">
                    <h4 class="mb-4">Totales</h4>
                    <div class="total-box">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($pedido['subtotal'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Impuesto (16%):</span>
                            <span>$<?php echo number_format($pedido['impuesto'], 2); ?></span>
                        </div>
                        <hr class="my-4" style="border-color: rgba(255,255,255,0.3);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Total:</h4>
                            <h2 class="mb-0">$<?php echo number_format($pedido['total'], 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Productos en el Pedido -->
        <div class="productos-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Productos en el Pedido</h4>
                <span class="badge bg-primary">
                    <?php echo count($productos_pedido); ?> productos
                </span>
            </div>
            
            <?php if (count($productos_pedido) > 0): ?>
                <?php foreach ($productos_pedido as $producto): ?>
                <div class="product-item-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-1"><?php echo htmlspecialchars($producto['producto_nombre']); ?></h6>
                            <?php if ($producto['notas']): ?>
                            <small class="text-muted"><i class="bi bi-chat-left-text me-1"></i> <?php echo htmlspecialchars($producto['notas']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="badge bg-secondary">
                                x<?php echo $producto['cantidad']; ?>
                            </span>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="text-muted">$<?php echo number_format($producto['precio_unitario'], 2); ?> c/u</span>
                        </div>
                        <div class="col-md-2 text-end">
                            <strong>$<?php echo number_format($producto['subtotal'], 2); ?></strong>
                        </div>
                    </div>
                    
                    <!-- Botón para eliminar producto (solo si pedido no finalizado) -->
                    <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
                    <div class="mt-3 text-end">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="remove_product">
                            <input type="hidden" name="id_detalle" value="<?php echo $producto['id_detalle']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                    onclick="return confirm('¿Eliminar este producto del pedido?')">
                                <i class="bi bi-trash me-1"></i> Eliminar
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-cart-x"></i>
                    </div>
                    <p class="text-muted">No hay productos en este pedido</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Agregar Productos (solo si pedido no finalizado) -->
        <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
        <div class="productos-card">
            <h4 class="mb-4">Agregar Productos</h4>
            
            <!-- Formulario para agregar producto -->
            <form method="POST" id="formAgregarProducto" class="mb-4">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="id_producto" id="input_id_producto" required>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Producto Seleccionado</label>
                        <div id="producto_seleccionado" class="form-control" style="min-height: 42px; background-color: var(--color-background); display: flex; align-items: center;">
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
                <h5 class="mt-4 mb-3 text-primary"><?php echo htmlspecialchars($categoria); ?></h5>
                <div class="product-grid">
                    <?php foreach ($productos as $producto): ?>
                    <div class="product-select-card" 
                         data-id="<?php echo $producto['id_producto']; ?>"
                         data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         data-precio="<?php echo $producto['precio']; ?>"
                         onclick="seleccionarProducto(this)">
                        <h6 class="mb-2"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                        <p class="text-muted small mb-3">
                            <?php echo htmlspecialchars(substr($producto['descripcion'], 0, 80)); ?>...
                        </p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge bg-info">
                                <i class="bi bi-clock me-1"></i> <?php echo $producto['tiempo_preparacion']; ?> min
                            </span>
                            <strong class="text-primary">$<?php echo number_format($producto['precio'], 2); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-cup-straw"></i>
                    </div>
                    <p class="text-muted">No hay productos disponibles</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Botones de acción -->
        <div class="d-flex justify-content-between mt-4">
            
            
            <?php if (!in_array($pedido['estado'], ['Entregado', 'Cancelado'])): ?>
            <div class="d-flex gap-2">
                
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
            document.querySelectorAll('.product-select-card.selected').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Agregar selección nueva
            elemento.classList.add('selected');
            productoSeleccionado = elemento;
            
            // Actualizar formulario
            document.getElementById('input_id_producto').value = elemento.getAttribute('data-id');
            document.getElementById('producto_seleccionado').innerHTML = `
                <strong>${elemento.getAttribute('data-nombre')}</strong>
                <span class="ms-2 text-muted">($${parseFloat(elemento.getAttribute('data-precio')).toFixed(2)})</span>
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