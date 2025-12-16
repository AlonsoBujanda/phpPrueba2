<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y rol
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Obtener roles para dropdown
$roles = [];
try {
    $stmt = $pdo->query("SELECT * FROM Roles ORDER BY id_rol");
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = "Error al cargar roles: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nombre = $_POST['nombre'];
            $usuario = $_POST['usuario'];
            $password = $_POST['password'];
            $id_rol = $_POST['id_rol'];
            $email = $_POST['email'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            
            try {
                // Verificar si usuario ya existe
                $stmt = $pdo->prepare("SELECT id_usuario FROM Usuarios WHERE usuario = ?");
                $stmt->execute([$usuario]);
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "El usuario ya existe";
                    $tipo_mensaje = 'warning';
                } else {
                    // Hash de contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO Usuarios 
                        (nombre, usuario, password, id_rol, email, telefono) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nombre, $usuario, $password_hash, $id_rol, $email, $telefono]);
                    
                    $mensaje = "Usuario creado exitosamente";
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al crear usuario: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'update':
            $id_usuario = $_POST['id_usuario'];
            $nombre = $_POST['nombre'];
            $usuario = $_POST['usuario'];
            $id_rol = $_POST['id_rol'];
            $email = $_POST['email'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $estado = isset($_POST['estado']) ? 1 : 0;
            
            // Si se proporciona nueva contraseña
            $password_update = '';
            if (!empty($_POST['password'])) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $password_update = ", password = '$password_hash'";
            }
            
            try {
                $sql = "UPDATE Usuarios SET 
                        nombre = ?, usuario = ?, id_rol = ?, email = ?, telefono = ?, estado = ?
                        $password_update
                        WHERE id_usuario = ?";
                
                $params = [$nombre, $usuario, $id_rol, $email, $telefono, $estado, $id_usuario];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $mensaje = "Usuario actualizado exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar usuario: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'delete':
        $id_usuario = $_POST['id_usuario'];
        
        try {
            $stmt = $pdo->prepare("SELECT usuario FROM Usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
            $usuario_a_eliminar = $stmt->fetch();
            
            if ($usuario_a_eliminar && $usuario_a_eliminar['usuario'] == ($_SESSION['usuario'] ?? '')) {
                $mensaje = "No puede eliminar su propio usuario";
                $tipo_mensaje = 'warning';
            } else {
                // SOFT DELETE: Marcar como eliminado en lugar de borrar
                $stmt = $pdo->prepare("UPDATE Usuarios SET estado = 0, eliminado = 1 WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                
                $mensaje = "Usuario eliminado exitosamente";
                $tipo_mensaje = 'success';
            }
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
        break;
        }
}

// Obtener todos los usuarios con información de rol
try {
    $stmt = $pdo->query("
    SELECT u.*, r.nombre_rol 
    FROM Usuarios u 
    LEFT JOIN Roles r ON u.id_rol = r.id_rol 
    WHERE u.eliminado = 0 OR u.eliminado IS NULL
    ORDER BY u.fecha_creacion DESC
");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
    $mensaje = "Error al cargar usuarios: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener estadísticas
$stats = [];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos,
        COUNT(DISTINCT id_rol) as roles_utilizados
        FROM Usuarios");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'activos' => 0, 'roles_utilizados' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" href="Icon/favicon.ico" type="image/x-icon">
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

        /* Header de Página */
        .page-header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
            color: var(--color-text-light);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        .page-header h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }

        .page-header p {
            color: var(--color-light-gray);
            margin-bottom: 0;
            position: relative;
            z-index: 1;
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
            background-color: var(--color-light-gray);
            color: var(--color-primary);
        }

        .btn-light:hover {
            background-color: var(--color-border);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-light {
            background-color: transparent;
            color: var(--color-text-light);
            border: 1px solid var(--color-text-light);
        }

        .btn-outline-light:hover {
            background-color: var(--color-text-light);
            color: var(--color-primary);
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
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px -15px rgba(0, 51, 102, 0.2);
        }

        .stat-card h2 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .stat-card p {
            color: var(--color-text-secondary);
            font-size: 0.875rem;
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

        /* Avatar de Usuario */
        .user-avatar-circle {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-text-light);
            font-weight: 600;
            font-size: 0.9rem;
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

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--color-success);
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--color-error);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        /* Role Badge */
        .role-badge {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
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

        .form-check-input:checked {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
        }

        .form-check-input:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
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

            .page-header {
                padding: var(--spacing-lg);
            }

            .page-header h1 {
                font-size: 2rem;
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

            .page-header h1 {
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
            <div class="sidebar-subtitle">Gestión de Usuarios</div>
        </div>
        
        <div class="user-info-sidebar">
            <div class="d-flex align-items-center">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></h6>
                    <small>Administrador</small>
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
            <a href="productos.php" class="nav-item">
                <i class="bi bi-cup-straw"></i> Productos
            </a>
            <a href="pedidos.php" class="nav-item">
                <i class="bi bi-receipt"></i> Pedidos
            </a>
            <a href="usuarios.php" class="nav-item active">
                <i class="bi bi-people"></i> Usuarios
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
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-people me-3"></i> Gestión de Usuarios</h1>
                    <p>Administración de usuarios del sistema</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalAgregarUsuario">
                        <i class="bi bi-plus-circle me-2"></i> Nuevo Usuario
                    </button>
                </div>
            </div>
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
                <h2 class="text-primary"><?php echo $stats['total']; ?></h2>
                <p>Total Usuarios</p>
            </div>
            
            <div class="stat-card">
                <h2 class="text-success"><?php echo $stats['activos']; ?></h2>
                <p>Usuarios Activos</p>
            </div>
            
            <div class="stat-card">
                <h2 class="text-info"><?php echo $stats['roles_utilizados']; ?></h2>
                <p>Roles Utilizados</p>
            </div>
        </div>
        
        <!-- Tabla de Usuarios -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Rol</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios) > 0): ?>
                            <?php foreach ($usuarios as $usuario): 
                                $iniciales = strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['nombre'], strpos($usuario['nombre'], ' ') + 1, 1));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar-circle me-3">
                                            <?php echo $iniciales; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($usuario['usuario']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                <td>
                                    <span class="role-badge">
                                        <?php echo htmlspecialchars($usuario['nombre_rol']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                                <td>
                                    <?php if ($usuario['estado']): ?>
                                    <span class="status-badge status-active">Activo</span>
                                    <?php else: ?>
                                    <span class="status-badge status-inactive">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['ultimo_acceso']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarUsuario"
                                                data-id="<?php echo $usuario['id_usuario']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                                data-usuario="<?php echo htmlspecialchars($usuario['usuario']); ?>"
                                                data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                                data-telefono="<?php echo htmlspecialchars($usuario['telefono']); ?>"
                                                data-rol="<?php echo $usuario['id_rol']; ?>"
                                                data-estado="<?php echo $usuario['estado']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEliminarUsuario"
                                                data-id="<?php echo $usuario['id_usuario']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <p class="text-muted">No hay usuarios registrados</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Usuario -->
    <div class="modal fade" id="modalAgregarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_rol" class="form-label">Rol *</label>
                            <select class="form-select" id="id_rol" name="id_rol" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_usuario" id="edit_id_usuario">
                        
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="edit_usuario" name="usuario" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Nueva Contraseña (dejar en blanco para no cambiar)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_id_rol" class="form-label">Rol *</label>
                            <select class="form-select" id="edit_id_rol" name="id_rol" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="edit_telefono" name="telefono">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="edit_estado" name="estado">
                                <label class="form-check-label" for="edit_estado">
                                    Usuario Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Usuario -->
    <div class="modal fade" id="modalEliminarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_usuario" id="delete_id_usuario">
                        
                        <p>¿Está seguro de eliminar al usuario <strong id="delete_nombre_usuario"></strong>?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Editar Usuario
            const modalEditar = document.getElementById('modalEditarUsuario');
            modalEditar.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('edit_id_usuario').value = button.getAttribute('data-id');
                document.getElementById('edit_nombre').value = button.getAttribute('data-nombre');
                document.getElementById('edit_usuario').value = button.getAttribute('data-usuario');
                document.getElementById('edit_email').value = button.getAttribute('data-email');
                document.getElementById('edit_telefono').value = button.getAttribute('data-telefono');
                document.getElementById('edit_id_rol').value = button.getAttribute('data-rol');
                document.getElementById('edit_estado').checked = button.getAttribute('data-estado') === '1';
            });
            
            // Modal Eliminar Usuario
            const modalEliminar = document.getElementById('modalEliminarUsuario');
            modalEliminar.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('delete_id_usuario').value = button.getAttribute('data-id');
                document.getElementById('delete_nombre_usuario').textContent = button.getAttribute('data-nombre');
            });
            
            // Validación de contraseña
            const formAgregar = document.querySelector('#modalAgregarUsuario form');
            if (formAgregar) {
                formAgregar.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('La contraseña debe tener al menos 6 caracteres');
                    }
                });
            }
        });
    </script>
</body>
</html>