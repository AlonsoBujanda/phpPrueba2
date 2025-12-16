<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

$user_id = $_SESSION['user_id'];

// Obtener información del usuario
try {
    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id_usuario = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Actualizar perfil
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    
    // Si se proporciona nueva contraseña
    if (!empty($_POST['password_actual']) && !empty($_POST['password_nueva'])) {
        // Verificar contraseña actual
        if (password_verify($_POST['password_actual'], $usuario['password'])) {
            $password_hash = password_hash($_POST['password_nueva'], PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("UPDATE Usuarios SET 
                    nombre = ?, email = ?, telefono = ?, password = ? 
                    WHERE id_usuario = ?");
                $stmt->execute([$nombre, $email, $telefono, $password_hash, $user_id]);
                
                $_SESSION['nombre'] = $nombre;
                $mensaje = "Perfil y contraseña actualizados exitosamente";
                $tipo_mensaje = 'success';
                
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
        } else {
            $mensaje = "La contraseña actual es incorrecta";
            $tipo_mensaje = 'danger';
        }
    } else {
        // Solo actualizar datos sin contraseña
        try {
            $stmt = $pdo->prepare("UPDATE Usuarios SET 
                nombre = ?, email = ?, telefono = ? 
                WHERE id_usuario = ?");
            $stmt->execute([$nombre, $email, $telefono, $user_id]);
            
            $_SESSION['nombre'] = $nombre;
            $mensaje = "Perfil actualizado exitosamente";
            $tipo_mensaje = 'success';
            
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
    
    // Actualizar datos del usuario
    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id_usuario = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema Restaurante</title>
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
        }

        /* ================================
           PÁGINA DE PERFIL
           ================================ */
        .profile-page {
            display: flex;
            min-height: 100vh;
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

        .user-avatar-large {
            width: 80px;
            height: 80px;
            background-color: var(--color-background);
            color: var(--color-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto var(--spacing-sm);
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Profile Card */
        .profile-card {
            width: 100%;
            max-width: 800px;
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--color-border);
            overflow: hidden;
        }

        .profile-card-header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
            color: var(--color-text-light);
            padding: var(--spacing-xl);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        .profile-card-header h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }

        .profile-card-header p {
            color: var(--color-light-gray);
            position: relative;
            z-index: 1;
        }

        .profile-card-body {
            padding: var(--spacing-xl);
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

        /* Formularios */
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

        .form-control:disabled {
            background-color: var(--color-light-gray);
            cursor: not-allowed;
        }

        /* Separadores */
        .separator {
            display: flex;
            align-items: center;
            margin: var(--spacing-lg) 0;
            color: var(--color-text-secondary);
        }

        .separator::before,
        .separator::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--color-border);
        }

        .separator span {
            padding: 0 var(--spacing-md);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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

        .btn-secondary {
            background-color: var(--color-text-secondary);
            color: var(--color-text-light);
        }

        .btn-secondary:hover {
            background-color: #3a506b;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* User Role Badge */
        .user-role-badge {
            display: inline-block;
            background-color: var(--color-secondary);
            color: var(--color-text-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
                padding: var(--spacing-md);
            }

            .profile-card {
                margin-top: var(--spacing-md);
            }

            .profile-card-header h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                max-height: 60vh;
            }

            .sidebar-nav {
                padding: var(--spacing-sm) 0;
            }

            .profile-card-header {
                padding: var(--spacing-lg);
            }

            .profile-card-header h1 {
                font-size: 1.75rem;
            }

            .profile-card-body {
                padding: var(--spacing-md);
            }
        }
    </style>
</head>
<body class="profile-page">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">Restaurante</div>
            <div class="sidebar-subtitle">Mi Perfil</div>
        </div>
        
        <div class="user-info-sidebar">
            <div class="user-avatar-large">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="text-center">
                <h6 class="mb-1"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></h6>
                <div class="user-role-badge"><?php echo htmlspecialchars($_SESSION['rol'] ?? 'Usuario'); ?></div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="bi bi-house"></i> Menu Principal
            </a>
            <a href="mesas.php" class="nav-item">
                <i class="bi bi-table"></i> Mesas
            </a>
            <?php if (in_array($_SESSION['rol'] ?? '', ['Administrador', 'Gerente'])): ?>
            <a href="productos.php" class="nav-item">
                <i class="bi bi-cup-straw"></i> Productos
            </a>
            <?php endif; ?>
            <a href="pedidos.php" class="nav-item">
                <i class="bi bi-receipt"></i> Pedidos
            </a>
            <a href="perfil.php" class="nav-item active">
                <i class="bi bi-person-circle"></i> Mi Perfil
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item logout-btn">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="profile-card">
            <!-- Header -->
            <div class="profile-card-header">
                <h1><i class="bi bi-person-circle me-2"></i> Mi Perfil</h1>
                <p>Administra tu información personal y credenciales</p>
            </div>
            
            <!-- Body -->
            <div class="profile-card-body">
                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre" 
                                   value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($usuario['usuario']); ?>" disabled>
                            <small class="text-muted">El nombre de usuario no se puede cambiar</small>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>"
                                   placeholder="ejemplo@correo.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" 
                                   value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>"
                                   placeholder="+52 123 456 7890">
                        </div>
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($_SESSION['rol'] ?? 'Usuario'); ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Último Acceso</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo !empty($usuario['ultimo_acceso']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>" disabled>
                        </div>
                    </div>
                    
                    <!-- Separador -->
                    <div class="separator">
                        <span>Cambiar Contraseña</span>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-muted small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Dejar en blanco si no desea cambiar la contraseña
                        </p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" name="password_actual"
                                       placeholder="Ingrese su contraseña actual">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_nueva"
                                       placeholder="Ingrese la nueva contraseña">
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <i class="bi bi-shield-lock me-1"></i>
                                La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números.
                            </small>
                        </div>
                    </div>
                    
                    <!-- Botones -->
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-4 border-top border-color-border">
                        <div>
                            <small class="text-muted">
                                <i class="bi bi-calendar-check me-1"></i>
                                Miembro desde: <?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?>
                            </small>
                        </div>
                        <div class="d-flex gap-3">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Actualizar Perfil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>