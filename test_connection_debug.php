<?php
// Activar mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>DEBUG - Test de Conexión</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Probar si PDO está habilitado
if (!extension_loaded('pdo_mysql')) {
    die("<div class='alert alert-danger'>✗ La extensión PDO MySQL no está habilitada</div>");
} else {
    echo "<div class='alert alert-success'>✓ PDO MySQL está habilitado</div>";
}

// Probar conexión directa sin clases
echo "<h3>Prueba de conexión directa:</h3>";

try {
    $host = 'localhost';
    $dbname = 'RestauranteDB';
    $username = 'root';
    $password = '';
    
    echo "Intentando conectar a:<br>";
    echo "Host: $host<br>";
    echo "Base de datos: $dbname<br>";
    echo "Usuario: $username<br>";
    echo "Contraseña: " . (empty($password) ? '(vacía)' : '******') . "<br><br>";
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $conn = new PDO($dsn, $username, $password, $options);
    echo "<div class='alert alert-success'>✓ Conexión exitosa a la base de datos</div>";
    
    // Mostrar tablas
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tablas encontradas (" . count($tables) . "):</h3>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        // Mostrar datos de prueba
        echo "<h3>Datos de prueba:</h3>";
        
        // Mostrar roles
        $stmt = $conn->query("SELECT * FROM Roles");
        $roles = $stmt->fetchAll();
        
        echo "<h4>Roles:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th></tr>";
        foreach ($roles as $rol) {
            echo "<tr>";
            echo "<td>" . $rol['id_rol'] . "</td>";
            echo "<td>" . $rol['nombre_rol'] . "</td>";
            echo "<td>" . $rol['descripcion'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar usuarios
        $stmt = $conn->query("SELECT u.*, r.nombre_rol FROM Usuarios u LEFT JOIN Roles r ON u.id_rol = r.id_rol");
        $usuarios = $stmt->fetchAll();
        
        echo "<h4>Usuarios:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th></tr>";
        foreach ($usuarios as $usuario) {
            echo "<tr>";
            echo "<td>" . $usuario['id_usuario'] . "</td>";
            echo "<td>" . $usuario['nombre'] . "</td>";
            echo "<td>" . $usuario['usuario'] . "</td>";
            echo "<td>" . $usuario['nombre_rol'] . "</td>";
            echo "<td>" . ($usuario['estado'] ? 'Activo' : 'Inactivo') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div class='alert alert-warning'>No se encontraron tablas en la base de datos</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>✗ Error de conexión: " . $e->getMessage() . "</div>";
    
    // Intentar crear la base de datos si no existe
    echo "<h3>Intentando crear base de datos...</h3>";
    try {
        $conn = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $conn->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<div class='alert alert-success'>✓ Base de datos creada o ya existente</div>";
        
        // Usar la base de datos
        $conn->exec("USE $dbname");
        echo "<div class='alert alert-success'>✓ Usando base de datos: $dbname</div>";
        
    } catch (PDOException $e2) {
        echo "<div class='alert alert-danger'>✗ Error al crear base de datos: " . $e2->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Conexión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .alert { margin: 10px 0; }
        table { margin: 15px 0; background: white; }
        th { background: #f1f1f1; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        // El contenido PHP ya está arriba
        ?>
        
        <div class="mt-4">
            <h3>Próximos pasos:</h3>
            <ol>
                <li>Si la conexión falla, verifica tus credenciales MySQL</li>
                <li>Si no hay tablas, ejecuta el script SQL en phpMyAdmin</li>
                <li>Si PDO no está habilitado, activa la extensión en php.ini</li>
            </ol>
            
            <div class="mt-3">
                <a href="login.php" class="btn btn-primary">Ir al Login</a>
                <a href="index.php" class="btn btn-secondary">Ir al Inicio</a>
                <button onclick="location.reload()" class="btn btn-info">Reintentar Conexión</button>
            </div>
        </div>
    </div>
</body>
</html>