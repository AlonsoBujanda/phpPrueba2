<?php
// Iniciar sesión al principio
session_start();

// Si ya está logueado, redirigir
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Conexión directa (sin usar includes/auth.php para evitar dependencias circulares)
function conectarDB() {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=RestauranteDB;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

function verificarLogin($usuario, $password) {
    $pdo = conectarDB();
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, r.nombre_rol as rol 
                              FROM Usuarios u 
                              INNER JOIN Roles r ON u.id_rol = r.id_rol 
                              WHERE u.usuario = ? AND u.estado = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Verificar contraseña
            if (password_verify($password, $user['password'])) {
                // Actualizar último acceso
                $stmt = $pdo->prepare("UPDATE Usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
                $stmt->execute([$user['id_usuario']]);
                
                return [
                    'success' => true,
                    'user' => $user
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()];
    }
}

// Procesar formulario
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        $result = verificarLogin($usuario, $password);
        
        if ($result['success']) {
            // Iniciar sesión
            $_SESSION['user_id'] = $result['user']['id_usuario'];
            $_SESSION['username'] = $result['user']['usuario'];
            $_SESSION['nombre'] = $result['user']['nombre'];
            $_SESSION['rol'] = $result['user']['rol'];
            $_SESSION['rol_id'] = $result['user']['id_rol'];
            $_SESSION['logged_in'] = true;
            
            // Redirigir
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        }

        /* ================================
           PÁGINA DE AUTENTICACIÓN (Login)
           ================================ */

           .auth-footer {
            text-align: center;
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--color-border);
        }
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-dark) 0%, var(--color-primary) 100%);
            padding: var(--spacing-md);
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
        }

        .auth-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            transition: transform 0.3s ease;
        }

        .auth-card:hover {
            transform: translateY(-5px);
        }
        
          /* ================================
           BOTÓN VOLVER AL INICIO - DISEÑO MINIMALISTA
           ================================ */
        .auth-footer {
            text-align: center;
            margin-top: var(--spacing-md);
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 400;
            padding: 0.5rem 0;
            transition: all 0.2s ease;
            position: relative;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        /* Línea decorativa sutil */
        .back-home::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background-color: var(--color-accent);
            transition: width 0.3s ease;
        }

        /* Efecto hover minimalista */
        .back-home:hover {
            color: var(--color-primary);
        }

        .back-home:hover::before {
            width: 100%;
        }

        /* Flecha animada */
        .back-home .arrow {
            display: inline-block;
            transition: transform 0.2s ease;
            font-size: 1.1em;
            line-height: 1;
        }

        .back-home:hover .arrow {
            transform: translateX(-3px);
        }

        /* Versión más sutil (sin subrayado) */
        .back-home.minimal-simple {
            color: var(--color-text-secondary);
            font-weight: 300;
            letter-spacing: 0.02em;
        }

        .back-home.minimal-simple:hover {
            color: var(--color-primary);
        }

        /* Versión con icono SVG */
        .back-home svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
            transition: transform 0.2s ease;
        }

        .back-home:hover svg {
            transform: translateX(-2px);
        }

        /* Versión con borde sutil al hover */
        .back-home.border-hover {
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
        }

        .back-home.border-hover:hover {
            border-color: var(--color-border);
            background-color: rgba(204, 238, 255, 0.1);
        }
        .auth-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .auth-header h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            color: var(--color-primary);
            margin-bottom: var(--spacing-xs);
        }

        .auth-header p {
            color: var(--color-text-secondary);
            font-size: 0.95rem;
        }

        .logo {
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }

        .logo h2 {
            font-family: var(--font-display);
            color: var(--color-primary);
            font-size: 2rem;
            margin-bottom: var(--spacing-xs);
        }

        .logo p {
            color: var(--color-text-secondary);
            font-size: 0.95rem;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .form-group label {
            font-weight: 500;
            color: var(--color-text-primary);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .form-control {
            padding: 0.875rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: var(--font-body);
            transition: all 0.3s ease;
            background-color: var(--color-background);
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.3);
        }

        .btn-login {
            padding: 1rem;
            background-color: var(--color-primary);
            color: var(--color-text-light);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: var(--spacing-sm);
            letter-spacing: 0.05em;
            width: 100%;
        }

        .btn-login:hover {
            background-color: var(--color-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .alert {
            padding: var(--spacing-sm);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-md);
            font-size: 0.95rem;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--color-error);
            color: var(--color-error);
        }

        .demo-credentials {
            text-align: center;
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--color-border);
        }

        .demo-credentials small {
            color: var(--color-text-secondary);
            font-size: 0.85rem;
        }

        /* ================================
           RESPONSIVE
           ================================ */
        @media (max-width: 768px) {
            .auth-card {
                padding: var(--spacing-lg);
            }

            .auth-header h1 {
                font-size: 2rem;
            }

            .logo h2 {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 480px) {
            .auth-page {
                padding: var(--spacing-sm);
            }

            .auth-card {
                padding: var(--spacing-md);
            }

            .auth-header h1 {
                font-size: 1.75rem;
            }

            .logo h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">
                <h2>Bienvenido</h2>
                <p class="text-muted">Inicie sesión para continuar</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" class="form-control" id="usuario" name="usuario" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">
                    Iniciar Sesión
                </button>
                <div class="auth-footer">
                    <a href="index.php" class="back-home">
                        <span class="arrow">←</span>
                        Volver al inicio
                    </a>
                </div>

                <div class="demo-credentials">
                    <small>
                        Usuario: Admin | Contraseña: admin123
                    </small>
                </div>
            </form>
        </div>
    </div>
</body>
</html>