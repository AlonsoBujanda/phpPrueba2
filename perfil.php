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
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Mi Perfil</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                            <?php echo $mensaje; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre Completo</label>
                                    <input type="text" class="form-control" name="nombre" 
                                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" name="telefono" 
                                           value="<?php echo htmlspecialchars($usuario['telefono']); ?>">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            <h5>Cambiar Contraseña</h5>
                            <p class="text-muted small">Dejar en blanco si no desea cambiar la contraseña</p>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" name="password_actual">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" name="password_nueva">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>