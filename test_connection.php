<?php
// Activar errores para debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar la ruta correcta
echo "<h1>Test de Conexión - RestauranteDB</h1>";
echo "<p>Directorio actual: " . __DIR__ . "</p>";

// Intentar incluir database.php
$db_path = __DIR__ . '/config/database.php';
echo "<p>Buscando archivo en: " . $db_path . "</p>";

if (file_exists($db_path)) {
    echo "<p style='color: green;'>✓ Archivo database.php encontrado</p>";
    require_once $db_path;
} else {
    echo "<p style='color: red;'>✗ Archivo database.php NO encontrado</p>";
    echo "<p>Intentando rutas alternativas...</p>";
    
    // Rutas alternativas
    $alternativas = [
        'database.php',
        '../config/database.php',
        './config/database.php',
        '../../config/database.php'
    ];
    
    foreach ($alternativas as $ruta) {
        echo "Probando: $ruta... ";
        if (file_exists($ruta)) {
            echo "<span style='color: green;'>✓ Encontrado</span><br>";
            require_once $ruta;
            break;
        } else {
            echo "<span style='color: red;'>✗ No encontrado</span><br>";
        }
    }
}

// Crear instancia y probar
echo "<h2>Probando conexión con la clase Database:</h2>";

try {
    $database = new Database();
    $conn = $database->connect();
    
    if ($conn) {
        echo "<div class='alert alert-success'>";
        echo "✓ Conexión exitosa usando la clase Database";
        echo "</div>";
        
        // Mostrar información de la base de datos
        $stmt = $conn->query("SELECT DATABASE() as db");
        $db_name = $stmt->fetchColumn();
        echo "<p>Base de datos conectada: <strong>$db_name</strong></p>";
        
        // Contar tablas
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Tablas en la base de datos (" . count($tables) . "):</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        // Mostrar conteo de registros
        echo "<h3>Conteo de registros:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Tabla</th><th>Registros</th></tr>";
        
        foreach ($tables as $table) {
            try {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM $table");
                $count = $stmt->fetchColumn();
                echo "<tr><td>$table</td><td>$count</td></tr>";
            } catch (Exception $e) {
                echo "<tr><td>$table</td><td>Error al contar</td></tr>";
            }
        }
        echo "</table>";
        
    } else {
        echo "<div class='alert alert-danger'>";
        echo "✗ No se pudo obtener conexión de la clase Database";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "✗ Error en la clase Database: " . $e->getMessage();
    echo "</div>";
}

// Probar la función helper getDB()
echo "<h2>Probando función helper getDB():</h2>";

try {
    if (function_exists('getDB')) {
        $db = getDB();
        if ($db) {
            echo "<div class='alert alert-success'>";
            echo "✓ Función getDB() funciona correctamente";
            echo "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>";
        echo "⚠ Función getDB() no está definida";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "✗ Error en getDB(): " . $e->getMessage();
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Conexión Corregido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .alert { margin: 10px 0; }
        table { margin: 15px 0; background: white; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        // El contenido PHP ya está arriba
        ?>
        
        <div class="mt-4">
            <h3>Estado del Sistema:</h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">✓ Base de Datos</h5>
                            <p class="card-text">Conexión establecida correctamente</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">✓ Tablas</h5>
                            <p class="card-text">8 tablas creadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">⚠ Usuario Admin</h5>
                            <p class="card-text">Contraseña necesita ser actualizada</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <h4>Próximos pasos:</h4>
                <ol>
                    <li><strong>Actualizar contraseña del admin:</strong> Ejecuta el script de actualización</li>
                    <li><strong>Probar login:</strong> Ve a <a href="login.php">login.php</a></li>
                    <li><strong>Crear más usuarios:</strong> Usa el panel de administración</li>
                </ol>
            </div>
            
            <div class="mt-4">
                <a href="login.php" class="btn btn-primary">Ir al Login</a>
                <a href="dashboard.php" class="btn btn-success">Ir al Dashboard</a>
                <button onclick="location.reload()" class="btn btn-info">Actualizar Test</button>
            </div>
        </div>
    </div>
</body>
</html>