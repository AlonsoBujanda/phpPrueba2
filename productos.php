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

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Obtener categorías para dropdown
$categorias = [];
try {
    $stmt = $pdo->query("SELECT * FROM Categorias WHERE estado = 1 ORDER BY nombre_categoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar categorías: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $precio = $_POST['precio'];
            $id_categoria = $_POST['id_categoria'];
            $costo = $_POST['costo'];
            $tiempo_preparacion = $_POST['tiempo_preparacion'];
            $disponibilidad = isset($_POST['disponibilidad']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO Productos 
                    (nombre, descripcion, precio, id_categoria, costo, tiempo_preparacion, disponibilidad) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $precio, $id_categoria, $costo, $tiempo_preparacion, $disponibilidad]);
                $mensaje = "Producto creado exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al crear producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'update':
            $id_producto = $_POST['id_producto'];
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $precio = $_POST['precio'];
            $id_categoria = $_POST['id_categoria'];
            $costo = $_POST['costo'];
            $tiempo_preparacion = $_POST['tiempo_preparacion'];
            $disponibilidad = isset($_POST['disponibilidad']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("UPDATE Productos SET 
                    nombre = ?, descripcion = ?, precio = ?, id_categoria = ?, 
                    costo = ?, tiempo_preparacion = ?, disponibilidad = ? 
                    WHERE id_producto = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $id_categoria, $costo, $tiempo_preparacion, $disponibilidad, $id_producto]);
                $mensaje = "Producto actualizado exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'delete':
            $id_producto = $_POST['id_producto'];
            
            try {
                // Verificar si el producto está en pedidos activos
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM DetallesPedido dp 
                    JOIN Pedidos p ON dp.id_pedido = p.id_pedido 
                    WHERE dp.id_producto = ? AND p.estado NOT IN ('Entregado', 'Cancelado')");
                $stmt->execute([$id_producto]);
                $en_pedidos = $stmt->fetchColumn();
                
                if ($en_pedidos > 0) {
                    $mensaje = "No se puede eliminar el producto porque está en pedidos activos";
                    $tipo_mensaje = 'warning';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Productos WHERE id_producto = ?");
                    $stmt->execute([$id_producto]);
                    $mensaje = "Producto eliminado exitosamente";
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener todos los productos con información de categoría
try {
    $stmt = $pdo->query("
        SELECT p.*, c.nombre_categoria 
        FROM Productos p 
        LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria 
        ORDER BY c.nombre_categoria, p.nombre
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productos = [];
    $mensaje = "Error al cargar productos: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener estadísticas
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN disponibilidad = 1 THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN disponibilidad = 0 THEN 1 ELSE 0 END) as no_disponibles
    FROM Productos");
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Sistema Restaurante</title>
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

        .btn-outline-danger {
            background-color: transparent;
            color: var(--color-error);
            border: 1px solid var(--color-error);
        }

        .btn-outline-danger:hover {
            background-color: var(--color-error);
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
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px -15px rgba(0, 51, 102, 0.2);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--color-light-gray);
            border-radius: var(--radius-md);
            color: var(--color-primary);
            flex-shrink: 0;
            font-size: 1.5rem;
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

        .status-disponible {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--color-success);
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .status-no-disponible {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--color-error);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        /* Price and Category Badges */
        .price-badge {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--color-info);
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .category-badge {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
        }

        /* Form Elements */
        .form-check-input:checked {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
        }

        .form-check-input:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        /* Input Groups */
        .input-group-text {
            background-color: var(--color-light-gray);
            border: 1px solid var(--color-border);
            color: var(--color-text-primary);
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

        .invalid-feedback {
            color: var(--color-error);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .is-invalid {
            border-color: var(--color-error) !important;
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
            <div class="sidebar-subtitle">Gestión de Productos</div>
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
            <a href="productos.php" class="nav-item active">
                <i class="bi bi-cup-straw"></i> Productos
            </a>
            <a href="pedidos.php" class="nav-item">
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
            <h1><i class="bi bi-cup-straw me-2"></i> Gestión de Productos</h1>
            <div class="user-info-main">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($rol); ?></div>
            </div>
        </div>
        
        <!-- Botón Nuevo Producto -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto">
                <i class="bi bi-plus-circle"></i> Nuevo Producto
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
                <div class="stat-icon" style="background-color: rgba(0, 51, 102, 0.1); color: var(--color-primary);">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['total'] ?? 0; ?></h3>
                    <p>Total Productos</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(46, 204, 113, 0.1); color: var(--color-success);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['disponibles'] ?? 0; ?></h3>
                    <p>Disponibles</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(231, 76, 60, 0.1); color: var(--color-error);">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['no_disponibles'] ?? 0; ?></h3>
                    <p>No Disponibles</p>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Productos -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Costo</th>
                            <th>Tiempo Prep.</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos) > 0): ?>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?php echo $producto['id_producto']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                    <?php if ($producto['descripcion']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="category-badge">
                                        <?php echo htmlspecialchars($producto['nombre_categoria'] ?? 'Sin categoría'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="price-badge">
                                        $<?php echo number_format($producto['precio'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        $<?php echo number_format($producto['costo'], 2); ?>
                                    </small>
                                </td>
                                <td>
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo $producto['tiempo_preparacion']; ?> min
                                </td>
                                <td>
                                    <?php
                                    $estado_class = $producto['disponibilidad'] ? 'status-disponible' : 'status-no-disponible';
                                    $estado_text = $producto['disponibilidad'] ? 'Disponible' : 'No Disponible';
                                    ?>
                                    <span class="status-badge <?php echo $estado_class; ?>">
                                        <?php echo $estado_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarProducto"
                                                data-id="<?php echo $producto['id_producto']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                data-descripcion="<?php echo htmlspecialchars($producto['descripcion']); ?>"
                                                data-precio="<?php echo $producto['precio']; ?>"
                                                data-costo="<?php echo $producto['costo']; ?>"
                                                data-categoria="<?php echo $producto['id_categoria']; ?>"
                                                data-tiempo="<?php echo $producto['tiempo_preparacion']; ?>"
                                                data-disponibilidad="<?php echo $producto['disponibilidad']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <button class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEliminarProducto"
                                                data-id="<?php echo $producto['id_producto']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-cup-straw"></i>
                                        </div>
                                        <p class="text-muted">No hay productos registrados</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalAgregarProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="id_categoria" class="form-label">Categoría *</label>
                                <select class="form-select" id="id_categoria" name="id_categoria" required>
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="precio" class="form-label">Precio de Venta *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio" name="precio" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="costo" class="form-label">Costo *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo" name="costo" 
                                           step="0.01" min="0" value="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="tiempo_preparacion" class="form-label">Tiempo Preparación *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="tiempo_preparacion" 
                                           name="tiempo_preparacion" min="1" value="15" required>
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="disponibilidad" name="disponibilidad" checked>
                                <label class="form-check-label" for="disponibilidad">
                                    Producto disponible
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Producto -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_producto" id="edit_id_producto">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nombre" class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_id_categoria" class="form-label">Categoría *</label>
                                <select class="form-select" id="edit_id_categoria" name="id_categoria" required>
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_precio" class="form-label">Precio de Venta *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit_precio" name="precio" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_costo" class="form-label">Costo *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit_costo" name="costo" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_tiempo_preparacion" class="form-label">Tiempo Preparación *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="edit_tiempo_preparacion" 
                                           name="tiempo_preparacion" min="1" required>
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="edit_disponibilidad" name="disponibilidad">
                                <label class="form-check-label" for="edit_disponibilidad">
                                    Producto disponible
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Producto -->
    <div class="modal fade" id="modalEliminarProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_producto" id="delete_id_producto">
                        
                        <p>¿Está seguro de eliminar el producto <strong id="delete_nombre_producto"></strong>?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Editar Producto
            const modalEditarProducto = document.getElementById('modalEditarProducto');
            modalEditarProducto.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('edit_id_producto').value = button.getAttribute('data-id');
                document.getElementById('edit_nombre').value = button.getAttribute('data-nombre');
                document.getElementById('edit_descripcion').value = button.getAttribute('data-descripcion');
                document.getElementById('edit_precio').value = button.getAttribute('data-precio');
                document.getElementById('edit_costo').value = button.getAttribute('data-costo');
                document.getElementById('edit_id_categoria').value = button.getAttribute('data-categoria');
                document.getElementById('edit_tiempo_preparacion').value = button.getAttribute('data-tiempo');
                
                const disponibilidad = button.getAttribute('data-disponibilidad') === '1';
                document.getElementById('edit_disponibilidad').checked = disponibilidad;
            });
            
            // Modal Eliminar Producto
            const modalEliminarProducto = document.getElementById('modalEliminarProducto');
            modalEliminarProducto.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('delete_id_producto').value = button.getAttribute('data-id');
                document.getElementById('delete_nombre_producto').textContent = button.getAttribute('data-nombre');
            });
            
            // Validación de precios
            const precioInput = document.getElementById('precio');
            const costoInput = document.getElementById('costo');
            const editPrecioInput = document.getElementById('edit_precio');
            const editCostoInput = document.getElementById('edit_costo');
            
            function validarPrecios(precioElement, costoElement) {
                const precio = parseFloat(precioElement.value) || 0;
                const costo = parseFloat(costoElement.value) || 0;
                
                if (costo > precio) {
                    costoElement.classList.add('is-invalid');
                    const feedback = costoElement.parentElement.querySelector('.invalid-feedback');
                    if (!feedback) {
                        const div = document.createElement('div');
                        div.className = 'invalid-feedback';
                        div.textContent = 'El costo no puede ser mayor al precio de venta';
                        costoElement.parentElement.appendChild(div);
                    }
                    return false;
                } else {
                    costoElement.classList.remove('is-invalid');
                    return true;
                }
            }
            
            if (precioInput && costoInput) {
                precioInput.addEventListener('input', () => validarPrecios(precioInput, costoInput));
                costoInput.addEventListener('input', () => validarPrecios(precioInput, costoInput));
            }
            
            if (editPrecioInput && editCostoInput) {
                editPrecioInput.addEventListener('input', () => validarPrecios(editPrecioInput, editCostoInput));
                editCostoInput.addEventListener('input', () => validarPrecios(editPrecioInput, editCostoInput));
            }
            
            // Validación de formularios antes de enviar
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Validar precio vs costo en este formulario
                    const formPrecio = form.querySelector('input[name="precio"]');
                    const formCosto = form.querySelector('input[name="costo"]');
                    
                    if (formPrecio && formCosto) {
                        if (!validarPrecios(formPrecio, formCosto)) {
                            isValid = false;
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Por favor corrija los errores en el formulario.');
                    }
                });
            });
        });
    </script>
</body>
</html>