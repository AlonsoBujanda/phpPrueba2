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

        /* ================================
           DASHBOARD / PANEL
           ================================ */
        .dashboard-page {
            display: flex;
            min-height: 100vh;
            background-color: var(--color-background);
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: var(--color-primary);
            color: var(--color-text-light);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
        }

        .sidebar-logo {
            font-family: var(--font-display);
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--color-secondary);
            margin-bottom: 0.25rem;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: var(--color-light-gray);
            opacity: 0.8;
        }

        .user-info-sidebar {
            padding: var(--spacing-md) var(--spacing-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background-color: var(--color-background);
            color: var(--color-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: var(--spacing-md) 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: 0.875rem var(--spacing-lg);
            color: var(--color-light-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            border-left: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background-color: var(--color-dark-gray);
            color: var(--color-secondary);
            border-left: 3px solid var(--color-secondary);
        }

        .nav-item svg {
            flex-shrink: 0;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding: var(--spacing-md) 0;
        }

        .logout-btn {
            color: var(--color-light-gray);
        }

        .logout-btn:hover {
            color: var(--color-error);
            background-color: rgba(231, 76, 60, 0.1);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: var(--spacing-lg);
            width: calc(100% - 280px);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--color-border);
        }

        .content-header h1 {
            font-family: var(--font-display);
            font-size: 2.25rem;
            color: var(--color-primary);
        }

        .user-info-main {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }

        .user-name {
            font-weight: 600;
            color: var(--color-primary);
        }

        .user-role {
            font-size: 0.875rem;
            color: var(--color-primary);
            background-color: var(--color-light-gray);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
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

        .btn-outline-warning {
            background-color: transparent;
            color: var(--color-warning);
            border: 1px solid var(--color-warning);
        }

        .btn-outline-warning:hover {
            background-color: var(--color-warning);
            color: var(--color-text-light);
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

        .btn-secondary {
            background-color: var(--color-text-secondary);
            color: var(--color-text-light);
        }

        .btn-secondary:hover {
            background-color: #3a506b;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        /* Alertas */
        .alert {
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-md);
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

        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }

        .stat-card {
            background: var(--color-surface);
            padding: var(--spacing-md);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            transition: all 0.3s ease;
            height: 100%;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px -15px rgba(0, 51, 102, 0.2);
        }

        .stat-info h3 {
            font-size: 1.5rem;
            color: var(--color-primary);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .stat-info p {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
            margin: 0;
        }

        /* Table Container */
        .table-container {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            overflow: hidden;
        }

        /* Tables */
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

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
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
            color: var(--color-accent);
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

        /* Modales */
        .modal-content {
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background-color: var(--color-background);
            border-bottom: 1px solid var(--color-border);
            padding: var(--spacing-md) var(--spacing-lg);
        }

        .modal-header h5 {
            font-family: var(--font-display);
            color: var(--color-primary);
            margin: 0;
            font-weight: 600;
        }

        .modal-body {
            padding: var(--spacing-lg);
        }

        .modal-footer {
            background-color: var(--color-background);
            border-top: 1px solid var(--color-border);
            padding: var(--spacing-md) var(--spacing-lg);
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

        .form-text {
            font-size: 0.85rem;
            color: var(--color-text-secondary);
            margin-top: 0.25rem;
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

        /* Colores para estadísticas */
        .text-warning { color: var(--color-warning); }
        .text-info { color: var(--color-accent); }
        .text-primary { color: var(--color-primary); }
        .text-success { color: var(--color-success); }

        /* ================================
           RESPONSIVE
           ================================ */
        @media (max-width: 968px) {
            .sidebar {
                width: 240px;
            }

            .main-content {
                margin-left: 240px;
                width: calc(100% - 240px);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: static;
                height: auto;
                max-height: 70vh;
                overflow-y: auto;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }

            .user-info-main {
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                max-height: 60vh;
            }

            .sidebar-nav {
                padding: var(--spacing-sm) 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: var(--spacing-md);
            }

            .content-header h1 {
                font-size: 1.75rem;
            }

            .table {
                font-size: 0.875rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">Restaurante</div>
            <div class="sidebar-subtitle">Gestión de Pedidos</div>
        </div>
        
        <div class="user-info-sidebar">
            <div class="d-flex align-items-center">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></h6>
                    <small><?php echo htmlspecialchars($rol); ?></small>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="bi bi-house"></i> Menu Principal
            </a>
            <a href="mesas.php" class="nav-item">
                <i class="bi bi-table"></i> Mesas
            </a>
            <?php if (in_array($rol, ['Administrador', 'Gerente'])): ?>
            <a href="productos.php" class="nav-item">
                <i class="bi bi-cup-straw"></i> Productos
            </a>
            <?php endif; ?>
            <a href="pedidos.php" class="nav-item active">
                <i class="bi bi-receipt"></i> Pedidos
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="perfil.php" class="nav-item">
                <i class="bi bi-person-circle"></i> Mi Perfil
            </a>
            
            <a href="logout.php" class="nav-item logout-btn">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="content-header">
            <h1><i class="bi bi-receipt me-2"></i> Gestión de Pedidos</h1>
            <div class="user-info-main">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($rol); ?></div>
            </div>
        </div>
        
        <!-- Botón Nuevo Pedido -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoPedido">
                <i class="bi bi-plus-circle"></i> Nuevo Pedido
            </button>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>
                    <h6 class="text-muted mb-1">Total Hoy</h6>
                    <h3 class="mb-0"><?php echo $estadisticas['total'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="stat-card">
                <div>
                    <h6 class="text-muted mb-1">Pendientes</h6>
                    <h3 class="mb-0 text-warning"><?php echo $estadisticas['pendientes'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="stat-card">
                <div>
                    <h6 class="text-muted mb-1">Preparando</h6>
                    <h3 class="mb-0 text-info"><?php echo $estadisticas['preparando'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="stat-card">
                <div>
                    <h6 class="text-muted mb-1">Listos</h6>
                    <h3 class="mb-0 text-primary"><?php echo $estadisticas['listos'] ?? 0; ?></h3>
                </div>
            </div>
            
            <div class="stat-card">
                <div>
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
                <table class="table">
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
                                    <div class="d-flex gap-2">
                                        <a href="pedido_detalle.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if ($pedido['estado'] !== 'Entregado' && $pedido['estado'] !== 'Cancelado'): ?>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalCambiarEstado"
                                                data-id="<?php echo $pedido['id_pedido']; ?>"
                                                data-estado="<?php echo $pedido['estado']; ?>">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                        <p class="text-muted">No hay pedidos registrados</p>
                                    </div>
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