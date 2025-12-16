<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? 'Invitado';
$nombre = $_SESSION['nombre'] ?? 'Usuario';

// Conectar a la base de datos
$db = new Database();
$pdo = $db->connect();

// Obtener estadísticas
$estadisticas = [];
try {
    // Ventas hoy
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as ventas_hoy 
                         FROM Pedidos 
                         WHERE DATE(fecha_hora) = CURDATE() 
                         AND estado_pago = 'Pagado'");
    $estadisticas['ventas_hoy'] = $stmt->fetchColumn();
    
    // Pedidos activos
    $stmt = $pdo->query("SELECT COUNT(*) as pedidos_activos 
                         FROM Pedidos 
                         WHERE estado NOT IN ('Entregado', 'Cancelado')");
    $estadisticas['pedidos_activos'] = $stmt->fetchColumn();
    
    // Mesas ocupadas
    $stmt = $pdo->query("SELECT COUNT(*) as mesas_ocupadas 
                         FROM Mesas 
                         WHERE estado = 'Ocupada'");
    $estadisticas['mesas_ocupadas'] = $stmt->fetchColumn();
    
    // Productos disponibles
    $stmt = $pdo->query("SELECT COUNT(*) as productos_disponibles 
                         FROM Productos 
                         WHERE disponibilidad = 1");
    $estadisticas['productos_disponibles'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $estadisticas = [
        'ventas_hoy' => 0,
        'pedidos_activos' => 0,
        'mesas_ocupadas' => 0,
        'productos_disponibles' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Restaurante</title>
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

        /* Dashboard Grid */
        .dashboard-grid {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
            color: var(--color-text-light);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        .welcome-card h2 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }

        .welcome-card p {
            color: var(--color-light-gray);
            line-height: 1.6;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .welcome-text {
            margin-top: var(--spacing-md);
            font-size: 1.05rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
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

        /* Quick Actions */
        .quick-actions-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            overflow: hidden;
        }

        .card-header {
            background-color: var(--color-background);
            border-bottom: 1px solid var(--color-border);
            padding: var(--spacing-md) var(--spacing-lg);
        }

        .card-header h5 {
            font-family: var(--font-display);
            color: var(--color-primary);
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .card-body {
            padding: var(--spacing-lg);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            font-family: var(--font-body);
            font-size: 0.95rem;
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

        .btn-warning {
            background-color: var(--color-warning);
            color: var(--color-text-light);
        }

        .btn-warning:hover {
            background-color: #e67e22;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-info {
            background-color: var(--color-accent);
            color: var(--color-text-light);
        }

        .btn-info:hover {
            background-color: #2980b9;
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

        .btn i {
            margin-right: 0.5rem;
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

            .welcome-card h2 {
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

            .welcome-card {
                padding: var(--spacing-md);
            }

            .welcome-card h2 {
                font-size: 1.75rem;
            }

            .content-header h1 {
                font-size: 1.75rem;
            }

            .main-content {
                padding: var(--spacing-md);
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">Restaurante</div>
            <div class="sidebar-subtitle">Sistema de Gestión</div>
        </div>
        
        <div class="user-info-sidebar">
            <div class="d-flex align-items-center">
                <div class="user-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="mb-0"><?php echo htmlspecialchars($nombre); ?></h6>
                    <small><?php echo htmlspecialchars($rol); ?></small>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <i class="bi bi-house"></i> Menu Principal
            </a>
            
            <?php if (in_array($rol, ['Administrador', 'Gerente', 'Mesero'])): ?>
            <a href="mesas.php" class="nav-item">
                <i class="bi bi-table"></i> Mesas
            </a>
            <a href="pedidos.php" class="nav-item">
                <i class="bi bi-receipt"></i> Pedidos
            </a>
            <?php endif; ?>
            
            <?php if (in_array($rol, ['Administrador', 'Gerente'])): ?>
            <a href="productos.php" class="nav-item">
                <i class="bi bi-cup-straw"></i> Productos
            </a>
            <?php endif; ?>
            
            <?php if ($rol == 'Cocinero'): ?>
            <a href="cocina.php" class="nav-item">
                <i class="bi bi-egg-fried"></i> Cocina
            </a>
            <?php endif; ?>
            
            <?php if (in_array($rol, ['Administrador', 'Gerente'])): ?>
            <a href="reportes.php" class="nav-item">
                <i class="bi bi-graph-up"></i> Reportes
            </a>
            <?php endif; ?>
            
            <?php if ($rol == 'Administrador'): ?>
            <a href="usuarios.php" class="nav-item">
                <i class="bi bi-people"></i> Usuarios
            </a>
            <?php endif; ?>
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
        <div class="content-header">
            <h1>Panel de Control</h1>
            <div class="user-info-main">
                <div class="user-name"><?php echo htmlspecialchars($nombre); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($rol); ?></div>
            </div>
        </div>
        
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>¡Bienvenido, <?php echo htmlspecialchars($nombre); ?>!</h2>
                    <p class="mb-0">Sistema de Gestión para Restaurantes - Panel de <?php echo htmlspecialchars($rol); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($estadisticas['total_pedidos'] ?? 0, 2); ?></h3>
                    <p>Ventas Hoy</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['pedidos_activos']; ?></h3>
                    <p>Pedidos Activos</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-table"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['mesas_ocupadas']; ?> / 8</h3>
                    <p>Mesas Ocupadas</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-cup-straw"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $estadisticas['productos_disponibles']; ?></h3>
                    <p>Productos Disponibles</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <!--
        <div class="quick-actions-card mt-4">
            <div class="card-header">
                <h5>Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (in_array($rol, ['Administrador', 'Gerente', 'Mesero'])): ?>
                    <div class="col-md-3 mb-3">
                        <a href="nuevo_pedido.php" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Nuevo Pedido
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($rol == 'Cocinero'): ?>
                    <div class="col-md-3 mb-3">
                        <a href="cocina.php" class="btn btn-warning w-100">
                            <i class="bi bi-egg-fried"></i> Ver Cocina
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($rol, ['Administrador', 'Gerente'])): ?>
                    <div class="col-md-3 mb-3">
                        <a href="reportes.php" class="btn btn-info w-100">
                            <i class="bi bi-graph-up"></i> Ver Reportes
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3 mb-3">
                        <a href="perfil.php" class="btn btn-secondary w-100">
                            <i class="bi bi-person-circle"></i> Mi Perfil
                        </a>
                    </div>
                </div>
            </div>
        </div> -->
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>