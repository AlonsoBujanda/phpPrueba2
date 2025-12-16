<?php
// Actualizar contraseña del usuario admin
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Actualizar Contraseña del Administrador</h1>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=RestauranteDB;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE Usuarios SET password = ? WHERE usuario = 'admin'");
    
    if ($stmt->execute([$hash])) {
        echo "<div style='color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "✅ Contraseña actualizada exitosamente<br>";
        echo "Usuario: <strong>admin</strong><br>";
        echo "Contraseña: <strong>admin123</strong><br>";
        echo "Hash generado correctamente";
        echo "</div>";
        
        // Verificar
        $stmt = $pdo->query("SELECT usuario, password FROM Usuarios WHERE usuario = 'admin'");
        $user = $stmt->fetch();
        
        if (password_verify('admin123', $user['password'])) {
            echo "<p style='color: green;'>✅ Verificación exitosa: La contraseña funciona</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error al actualizar</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Actualizar Contraseña</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        // El contenido ya está arriba
        ?>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h3>Instrucciones:</h3>
            <ol>
                <li>Si ves el mensaje de éxito arriba, la contraseña está actualizada</li>
                <li>Ve a <a href="login.php">login.php</a> e ingresa con:
                    <ul>
                        <li>Usuario: <strong>admin</strong></li>
                        <li>Contraseña: <strong>admin123</strong></li>
                    </ul>
                </li>
                <li>Después del login exitoso, ELIMINA este archivo por seguridad</li>
            </ol>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="login.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                Ir al Login
            </a>
            <a href="dashboard.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Ir al Dashboard
            </a>
        </div>
    </div>
</body>
</html>