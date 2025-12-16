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

            /* Estados - Cocina específico */
            --color-cocina-warning: #f39c12; /* Naranja/Amarillo para alertas */
            --color-cocina-danger: #e74c3c; /* Rojo para urgente */
            --color-cocina-success: #2ecc71; /* Verde para listo */

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
           PÁGINA DE COCINA
           ================================ */
        .cocina-page {
            min-height: 100vh;
        }

        /* Header de Cocina */
        .cocina-header {
            background: linear-gradient(135deg, var(--color-cocina-warning) 0%, #e67e22 100%);
            color: var(--color-text-light);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .cocina-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }

        .cocina-header h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }

        .cocina-header p {
            color: rgba(255, 255, 255, 0.9);
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

        .btn-light {
            background-color: var(--color-text-light);
            color: var(--color-cocina-warning);
        }

        .btn-light:hover {
            background-color: var(--color-light-gray);
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
            color: var(--color-cocina-warning);
        }

        .btn-success {
            background-color: var(--color-cocina-success);
            color: var(--color-text-light);
        }

        .btn-success:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
            border-color: var(--color-cocina-success);
            color: #155724;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-color: var(--color-cocina-danger);
            color: #721c24;
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

        /* Órdenes */
        .ordenes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-md);
        }

        .orden-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            border-left: 6px solid var(--color-cocina-warning);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .orden-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .orden-card.urgente {
            border-left-color: var(--color-cocina-danger);
            animation: pulse 2s infinite;
        }

        .orden-card.listo {
            border-left-color: var(--color-cocina-success);
            opacity: 0.9;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(231, 76, 60, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
            }
        }

        /* Mesa Badge */
        .mesa-badge {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
            color: var(--color-text-light);
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.2rem;
            display: inline-block;
            box-shadow: var(--shadow-sm);
        }

        /* Tiempo Badge */
        .tiempo-badge {
            background-color: var(--color-light-gray);
            color: var(--color-text-primary);
            padding: 0.375rem 0.875rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .tiempo-urgente {
            background-color: var(--color-cocina-danger);
            color: var(--color-text-light);
        }

        /* Estado Badge */
        .estado-badge {
            padding: 0.375rem 0.875rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .estado-pendiente {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--color-cocina-warning);
            border: 1px solid rgba(243, 156, 18, 0.3);
        }

        .estado-preparando {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--color-accent);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .estado-listo {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--color-cocina-success);
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        /* Especificaciones */
        .especificaciones-card {
            background-color: rgba(204, 238, 255, 0.2);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: var(--spacing-sm);
            margin: var(--spacing-sm) 0;
        }

        /* Cantidad Badge */
        .cantidad-badge {
            background-color: var(--color-text-secondary);
            color: var(--color-text-light);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
            grid-column: 1 / -1;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--color-light-gray);
            margin-bottom: var(--spacing-md);
        }

        /* Hora actual */
        .hora-actual {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
        }

        /* Audio Alert (hidden) */
        .audio-alert {
            display: none;
        }

        /* ================================
           RESPONSIVE
           ================================ */
        @media (max-width: 1200px) {
            .ordenes-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .cocina-header {
                padding: var(--spacing-lg);
            }

            .cocina-header h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .ordenes-grid {
                grid-template-columns: 1fr;
            }

            .orden-card {
                padding: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .cocina-header h1 {
                font-size: 1.75rem;
            }

            .cocina-header .row {
                flex-direction: column;
                gap: var(--spacing-sm);
            }

            .cocina-header .text-end {
                text-align: left !important;
            }
        }
    </style>
</head>
<body class="cocina-page">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="cocina-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-egg-fried me-3"></i> Panel de Cocina</h1>
                    <p>Órdenes en tiempo real - <span class="hora-actual"><?php echo date('H:i:s'); ?></span></p>
                </div>
                <div class="col-md-4 text-end">
                    <button onclick="location.reload()" class="btn btn-light me-2">
                        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                    </button>
                    
                    <button onclick="location.href='dashboard.php'" class="btn btn-outline-light">
                        <i class="bi bi-house me-1"></i> Menú Principal
                    </button>


                </div>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h2 class="text-warning"><?php echo $stats['pendientes']; ?></h2>
                <p>Pendientes</p>
            </div>
            
            <div class="stat-card">
                <h2 class="text-primary"><?php echo $stats['preparando']; ?></h2>
                <p>En Preparación</p>
            </div>
            
            <div class="stat-card">
                <h2 class="text-success"><?php echo $stats['listos']; ?></h2>
                <p>Listos</p>
            </div>
        </div>
        
        <!-- Órdenes -->
        <div class="ordenes-grid">
            <?php if (count($ordenes) > 0): ?>
                <?php foreach ($ordenes as $orden): 
                    $es_urgente = $orden['minutos_espera'] > $orden['tiempo_estimado'];
                    $clase_card = $orden['estado'] === 'Listo' ? 'listo' : ($es_urgente ? 'urgente' : '');
                ?>
                <div class="orden-card <?php echo $clase_card; ?>" id="orden-<?php echo $orden['id_detalle']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="mesa-badge mb-2">Mesa <?php echo $orden['numero_mesa']; ?></div>
                            <div>
                                <small class="text-muted">Pedido #<?php echo $orden['id_pedido']; ?></small>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="tiempo-badge <?php echo $es_urgente ? 'tiempo-urgente' : ''; ?>">
                                <i class="bi bi-clock"></i> 
                                <?php echo $orden['minutos_espera']; ?> min
                            </span>
                            <div class="mt-1">
                                <small class="text-muted">Estimado: <?php echo $orden['tiempo_estimado']; ?> min</small>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3">
                        <?php echo htmlspecialchars($orden['producto']); ?>
                        <span class="cantidad-badge ms-2">x<?php echo $orden['cantidad']; ?></span>
                    </h5>
                    
                    <?php if ($orden['especificaciones']): ?>
                    <div class="especificaciones-card mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-chat-dots text-primary me-2 mt-1"></i>
                            <small class="text-muted"><?php echo htmlspecialchars($orden['especificaciones']); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <?php
                            $estado_class = match($orden['estado']) {
                                'Pendiente' => 'estado-pendiente',
                                'Preparando' => 'estado-preparando',
                                'Listo' => 'estado-listo',
                                default => 'estado-pendiente'
                            };
                            ?>
                            <span class="estado-badge <?php echo $estado_class; ?>">
                                <?php echo $orden['estado']; ?>
                            </span>
                        </div>
                        
                        <?php if ($orden['estado'] !== 'Listo'): ?>
                        <form method="POST" action="" class="m-0">
                            <input type="hidden" name="action" value="marcar_listo">
                            <input type="hidden" name="id_detalle" value="<?php echo $orden['id_detalle']; ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> Marcar como Listo
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="text-success d-flex align-items-center">
                            <i class="bi bi-check2-circle me-1"></i>
                            <span>Completado</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3 text-end">
                        <small class="text-muted">
                            <i class="bi bi-clock-history me-1"></i>
                            Recibido: <?php echo date('H:i', strtotime($orden['fecha_creacion'])); ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-egg-fried"></i>
                    </div>
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
        
        // Actualizar hora cada segundo
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.querySelector('.hora-actual').textContent = timeString;
        }
        setInterval(updateTime, 1000);
        
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
                
                const alertInterval = setInterval(function() {
                    if (isAlert) {
                        document.title = tituloOriginal;
                    } else {
                        document.title = "🚨 Órdenes urgentes!";
                    }
                    isAlert = !isAlert;
                }, 1000);
                
                // Limpiar intervalo cuando se carguen nuevas órdenes
                window.addEventListener('beforeunload', function() {
                    clearInterval(alertInterval);
                });
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
                speech.lang = 'es-ES';
                window.speechSynthesis.speak(speech);
            }
        }
        
        // Detectar nuevas órdenes y anunciarlas
        let ultimaOrdenId = <?php echo count($ordenes) > 0 ? $ordenes[0]['id_detalle'] : 0; ?>;
        
        setInterval(function() {
            // Esta función se implementaría mejor con WebSockets
            // Por ahora es un ejemplo básico
            const primeraOrden = document.querySelector('.orden-card');
            if (primeraOrden) {
                const ordenId = primeraOrden.id.split('-')[1];
                if (ordenId != ultimaOrdenId) {
                    const mesa = primeraOrden.querySelector('.mesa-badge').textContent;
                    const producto = primeraOrden.querySelector('h5').textContent.split('x')[0].trim();
                    speakOrder(`Nueva orden para ${mesa}: ${producto}`);
                    ultimaOrdenId = ordenId;
                }
            }
        }, 10000); // Verificar cada 10 segundos
    </script>
</body>
</html>