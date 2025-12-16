<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y rol
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'Cocinero' && $rol !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Cambiar estado de un producto en preparación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'marcar_listo') {
        $id_detalle = $_POST['id_detalle'];
        
        try {
            $stmt = $pdo->prepare("UPDATE DetallesPedido SET estado = 'Listo', fecha_listo = NOW() WHERE id_detalle = ?");
            $stmt->execute([$id_detalle]);
            
            // Verificar si todos los productos del pedido están listos
            $stmt = $pdo->prepare("SELECT COUNT(*) as pendientes 
                                  FROM DetallesPedido 
                                  WHERE id_pedido = (SELECT id_pedido FROM DetallesPedido WHERE id_detalle = ?) 
                                  AND estado IN ('Pendiente', 'Preparando')");
            $stmt->execute([$id_detalle]);
            $pendientes = $stmt->fetchColumn();
            
            if ($pendientes == 0) {
                // Todos los productos están listos, marcar pedido como listo
                $stmt = $pdo->prepare("UPDATE Pedidos SET estado = 'Listo' 
                                      WHERE id_pedido = (SELECT id_pedido FROM DetallesPedido WHERE id_detalle = ?)");
                $stmt->execute([$id_detalle]);
            }
            
            $mensaje = "Producto marcado como listo";
            $tipo_mensaje = 'success';
            
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener órdenes para cocina (excluyendo bebidas)
try {
    $query = "
        SELECT 
            dp.id_detalle,
            p.id_pedido,
            m.numero_mesa,
            pr.nombre as producto,
            dp.cantidad,
            dp.notas as especificaciones,
            dp.estado,
            dp.fecha_creacion,
            TIMESTAMPDIFF(MINUTE, dp.fecha_creacion, NOW()) as minutos_espera,
            pr.tiempo_preparacion as tiempo_estimado
        FROM DetallesPedido dp
        INNER JOIN Pedidos p ON dp.id_pedido = p.id_pedido
        INNER JOIN Mesas m ON p.id_mesa = m.id_mesa
        INNER JOIN Productos pr ON dp.id_producto = pr.id_producto
        WHERE dp.estado IN ('Pendiente', 'Preparando')
        AND pr.id_categoria != 4  -- Excluir bebidas (categoría 4)
        AND p.estado NOT IN ('Entregado', 'Cancelado')
        ORDER BY 
            CASE 
                WHEN TIMESTAMPDIFF(MINUTE, dp.fecha_creacion, NOW()) > pr.tiempo_preparacion THEN 1
                ELSE 2
            END,
            dp.fecha_creacion ASC
    ";
    
    $stmt = $pdo->query($query);
    $ordenes = $stmt->fetchAll();
} catch (PDOException $e) {
    $ordenes = [];
    $mensaje = "Error al cargar órdenes: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener estadísticas
$stats = [];
try {
    // Pendientes
    $stmt = $pdo->query("SELECT COUNT(*) FROM DetallesPedido dp 
                        INNER JOIN Productos pr ON dp.id_producto = pr.id_producto 
                        WHERE dp.estado = 'Pendiente' AND pr.id_categoria != 4");
    $stats['pendientes'] = $stmt->fetchColumn();
    
    // En preparación
    $stmt = $pdo->query("SELECT COUNT(*) FROM DetallesPedido dp 
                        INNER JOIN Productos pr ON dp.id_producto = pr.id_producto 
                        WHERE dp.estado = 'Preparando' AND pr.id_categoria != 4");
    $stats['preparando'] = $stmt->fetchColumn();
    
    // Listos
    $stmt = $pdo->query("SELECT COUNT(*) FROM DetallesPedido dp 
                        INNER JOIN Productos pr ON dp.id_producto = pr.id_producto 
                        WHERE dp.estado = 'Listo' AND pr.id_categoria != 4");
    $stats['listos'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $stats = ['pendientes' => 0, 'preparando' => 0, 'listos' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --warning: #f8961e;
            --success: #4cc9f0;
            --danger: #f94144;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header-cocina {
            background: linear-gradient(135deg, var(--warning) 0%, #f3722c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .orden-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 5px solid var(--warning);
            transition: all 0.3s;
        }
        
        .orden-card.urgente {
            border-left-color: var(--danger);
            animation: pulse 2s infinite;
        }
        
        .orden-card.listo {
            border-left-color: var(--success);
            opacity: 0.8;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(249, 65, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(249, 65, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(249, 65, 68, 0); }
        }
        
        .mesa-badge {
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .tiempo-badge {
            background: #e9ecef;
            color: #495057;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .tiempo-urgente {
            background: var(--danger);
            color: white;
        }
        
        .btn-cocina {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-cocina:hover {
            transform: translateY(-2px);
        }
        
        .audio-alert {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="header-cocina">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-egg-fried me-3"></i> Panel de Cocina</h1>
                    <p class="mb-0">Órdenes en tiempo real - <?php echo date('H:i:s'); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <button onclick="location.reload()" class="btn btn-light">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light ms-2">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted mb-1">Pendientes</h6>
                    <h2 class="text-warning"><?php echo $stats['pendientes']; ?></h2>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted mb-1">En Preparación</h6>
                    <h2 class="text-primary"><?php echo $stats['preparando']; ?></h2>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted mb-1">Listos</h6>
                    <h2 class="text-success"><?php echo $stats['listos']; ?></h2>
                </div>
            </div>
        </div>
        
        <!-- Órdenes -->
        <div class="row">
            <?php if (count($ordenes) > 0): ?>
                <?php foreach ($ordenes as $orden): 
                    $es_urgente = $orden['minutos_espera'] > $orden['tiempo_estimado'];
                    $clase_card = $orden['estado'] === 'Listo' ? 'listo' : ($es_urgente ? 'urgente' : '');
                ?>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="orden-card <?php echo $clase_card; ?>" id="orden-<?php echo $orden['id_detalle']; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="mesa-badge">Mesa <?php echo $orden['numero_mesa']; ?></span>
                                <div class="mt-2">
                                    <small class="text-muted">Pedido #<?php echo $orden['id_pedido']; ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="tiempo-badge <?php echo $es_urgente ? 'tiempo-urgente' : ''; ?>">
                                    <i class="bi bi-clock"></i> 
                                    <?php echo $orden['minutos_espera']; ?> min
                                </span>
                                <div class="mt-2">
                                    <small>Estimado: <?php echo $orden['tiempo_estimado']; ?> min</small>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mb-2">
                            <?php echo htmlspecialchars($orden['producto']); ?>
                            <span class="badge bg-secondary">x<?php echo $orden['cantidad']; ?></span>
                        </h5>
                        
                        <?php if ($orden['especificaciones']): ?>
                        <div class="alert alert-light py-2 mb-3">
                            <small><i class="bi bi-chat-dots"></i> <?php echo htmlspecialchars($orden['especificaciones']); ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php
                                $estado_badge = match($orden['estado']) {
                                    'Pendiente' => 'warning',
                                    'Preparando' => 'primary',
                                    'Listo' => 'success',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $estado_badge; ?>">
                                    <?php echo $orden['estado']; ?>
                                </span>
                            </div>
                            
                            <?php if ($orden['estado'] !== 'Listo'): ?>
                            <form method="POST" action="" class="m-0">
                                <input type="hidden" name="action" value="marcar_listo">
                                <input type="hidden" name="id_detalle" value="<?php echo $orden['id_detalle']; ?>">
                                <button type="submit" class="btn btn-success btn-cocina">
                                    <i class="bi bi-check-circle me-1"></i> Listo
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-success">
                                <i class="bi bi-check2-circle"></i> Completado
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3 text-end">
                            <small class="text-muted">
                                Recibido: <?php echo date('H:i', strtotime($orden['fecha_creacion'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-egg-fried text-muted" style="font-size: 4rem;"></i>
                    <h3 class="text-muted mt-3">No hay órdenes pendientes</h3>
                    <p class="text-muted">Todas las órdenes están completadas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Audio para alertas (opcional) -->
    <audio id="alertAudio" class="audio-alert">
        <source src="https://assets.mixkit.co/sfx/preview/mixkit-kitchen-timer-1001.mp3" type="audio/mpeg">
    </audio>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh cada 30 segundos
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Reproducir sonido si hay órdenes urgentes
        document.addEventListener('DOMContentLoaded', function() {
            const ordenesUrgentes = document.querySelectorAll('.orden-card.urgente');
            if (ordenesUrgentes.length > 0) {
                const audio = document.getElementById('alertAudio');
                if (audio) {
                    audio.play().catch(e => console.log("Audio no pudo reproducirse automáticamente"));
                }
                
                // Titilar en la pestaña del navegador
                let tituloOriginal = document.title;
                let isAlert = false;
                
                setInterval(function() {
                    if (isAlert) {
                        document.title = tituloOriginal;
                    } else {
                        document.title = "🚨 Órdenes urgentes!";
                    }
                    isAlert = !isAlert;
                }, 1000);
            }
        });
        
        // Marcar como leído al hacer clic
        document.querySelectorAll('.orden-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.opacity = '0.9';
            });
        });
        
        // Función para hablar las órdenes nuevas (si el navegador soporta Web Speech API)
        function speakOrder(orderText) {
            if ('speechSynthesis' in window) {
                const speech = new SpeechSynthesisUtterance();
                speech.text = orderText;
                speech.volume = 1;
                speech.rate = 1;
                speech.pitch = 1;
                window.speechSynthesis.speak(speech);
            }
        }
        
        // Ejemplo de uso (descomentar si quieres):
        // speakOrder("Nueva orden para mesa 5");
    </script>
</body>
</html>