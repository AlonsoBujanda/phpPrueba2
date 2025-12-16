<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$roles_permitidos = ['Administrador', 'Gerente', 'Mesero'];
if (!in_array($rol, $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $numero_mesa = $_POST['numero_mesa'];
            $capacidad = $_POST['capacidad'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO Mesas (numero_mesa, capacidad) VALUES (?, ?)");
                $stmt->execute([$numero_mesa, $capacidad]);
                $mensaje = "Mesa creada exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al crear mesa: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'update':
            $id_mesa = $_POST['id_mesa'];
            $numero_mesa = $_POST['numero_mesa'];
            $capacidad = $_POST['capacidad'];
            $estado = $_POST['estado'];
            
            try {
                $stmt = $pdo->prepare("UPDATE Mesas SET numero_mesa = ?, capacidad = ?, estado = ? WHERE id_mesa = ?");
                $stmt->execute([$numero_mesa, $capacidad, $estado, $id_mesa]);
                $mensaje = "Mesa actualizada exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar mesa: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'delete':
            $id_mesa = $_POST['id_mesa'];
            
            try {
                // Verificar si la mesa tiene pedidos activos
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Pedidos WHERE id_mesa = ? AND estado NOT IN ('Entregado', 'Cancelado')");
                $stmt->execute([$id_mesa]);
                $pedidos_activos = $stmt->fetchColumn();
                
                if ($pedidos_activos > 0) {
                    $mensaje = "No se puede eliminar la mesa porque tiene pedidos activos";
                    $tipo_mensaje = 'warning';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Mesas WHERE id_mesa = ?");
                    $stmt->execute([$id_mesa]);
                    $mensaje = "Mesa eliminada exitosamente";
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar mesa: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener todas las mesas
try {
    $stmt = $pdo->query("SELECT * FROM Mesas ORDER BY numero_mesa");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mesas = [];
    $mensaje = "Error al cargar mesas: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener mesas para estadísticas
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Libre' THEN 1 ELSE 0 END) as libres,
    SUM(CASE WHEN estado = 'Ocupada' THEN 1 ELSE 0 END) as ocupadas,
    SUM(CASE WHEN estado = 'Reservada' THEN 1 ELSE 0 END) as reservadas
    FROM Mesas");
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mesas - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f94144;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, #3a0ca3 100%);
            color: white;
            min-height: 100vh;
            width: 250px;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-libre { background-color: #d4edda; color: #155724; }
        .status-ocupada { background-color: #fff3cd; color: #856404; }
        .status-reservada { background-color: #d1ecf1; color: #0c5460; }
        
        .btn-action {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-none d-md-block">
        <div class="text-center mb-4">
            <h3 class="mb-1">🍽️</h3>
            <h5 class="mb-0">Sistema Restaurante</h5>
            <small>Gestión de Mesas</small>
        </div>
        
        <div class="px-3 mb-4">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" 
                     style="width: 40px; height: 40px;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="ms-3">
                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></h6>
                    <small><?php echo htmlspecialchars($rol); ?></small>
                </div>
            </div>
        </div>
        
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="mesas.php" class="nav-link text-white py-2 px-3 bg-white bg-opacity-10 rounded">
                <i class="bi bi-table me-2"></i> Mesas
            </a>
            <?php if (in_array($rol, ['Administrador', 'Gerente', 'Mesero'])): ?>
            <a href="pedidos.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-receipt me-2"></i> Pedidos
            </a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link text-white py-2 px-3 text-danger">
                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-table me-2"></i> Gestión de Mesas</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarMesa">
                <i class="bi bi-plus-circle me-1"></i> Nueva Mesa
            </button>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Mesas</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['total'] ?? 0; ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-table" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Libres</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['libres'] ?? 0; ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Ocupadas</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['ocupadas'] ?? 0; ?></h3>
                        </div>
                        <div class="text-warning">
                            <i class="bi bi-clock" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Reservadas</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['reservadas'] ?? 0; ?></h3>
                        </div>
                        <div class="text-info">
                            <i class="bi bi-calendar-check" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Mesas -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número</th>
                            <th>Capacidad</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($mesas) > 0): ?>
                            <?php foreach ($mesas as $mesa): ?>
                            <tr>
                                <td><?php echo $mesa['id_mesa']; ?></td>
                                <td>
                                    <strong>Mesa <?php echo $mesa['numero_mesa']; ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-people me-1"></i>
                                    <?php echo $mesa['capacidad']; ?> personas
                                </td>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    switch ($mesa['estado']) {
                                        case 'Libre': $estado_class = 'status-libre'; break;
                                        case 'Ocupada': $estado_class = 'status-ocupada'; break;
                                        case 'Reservada': $estado_class = 'status-reservada'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $estado_class; ?>">
                                        <?php echo $mesa['estado']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($mesa['fecha_creacion'])); ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary btn-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarMesa"
                                            data-id="<?php echo $mesa['id_mesa']; ?>"
                                            data-numero="<?php echo $mesa['numero_mesa']; ?>"
                                            data-capacidad="<?php echo $mesa['capacidad']; ?>"
                                            data-estado="<?php echo $mesa['estado']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-outline-danger btn-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEliminarMesa"
                                            data-id="<?php echo $mesa['id_mesa']; ?>"
                                            data-numero="<?php echo $mesa['numero_mesa']; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="bi bi-table text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No hay mesas registradas</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Mesa -->
    <div class="modal fade" id="modalAgregarMesa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nueva Mesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="numero_mesa" class="form-label">Número de Mesa</label>
                            <input type="number" class="form-control" id="numero_mesa" name="numero_mesa" 
                                   min="1" max="999" required>
                            <div class="form-text">Número único para identificar la mesa</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="capacidad" class="form-label">Capacidad</label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad" 
                                   min="1" max="20" value="4" required>
                            <div class="form-text">Número máximo de personas</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Mesa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Mesa -->
    <div class="modal fade" id="modalEditarMesa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Mesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_mesa" id="edit_id_mesa">
                        
                        <div class="mb-3">
                            <label for="edit_numero_mesa" class="form-label">Número de Mesa</label>
                            <input type="number" class="form-control" id="edit_numero_mesa" name="numero_mesa" 
                                   min="1" max="999" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_capacidad" class="form-label">Capacidad</label>
                            <input type="number" class="form-control" id="edit_capacidad" name="capacidad" 
                                   min="1" max="20" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_estado" class="form-label">Estado</label>
                            <select class="form-select" id="edit_estado" name="estado" required>
                                <option value="Libre">Libre</option>
                                <option value="Ocupada">Ocupada</option>
                                <option value="Reservada">Reservada</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Mesa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Mesa -->
    <div class="modal fade" id="modalEliminarMesa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Mesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_mesa" id="delete_id_mesa">
                        
                        <p>¿Está seguro de eliminar la mesa <strong id="delete_numero_mesa"></strong>?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Mesa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Script para llenar modales con datos
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Editar
            const modalEditar = document.getElementById('modalEditarMesa');
            modalEditar.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const numero = button.getAttribute('data-numero');
                const capacidad = button.getAttribute('data-capacidad');
                const estado = button.getAttribute('data-estado');
                
                document.getElementById('edit_id_mesa').value = id;
                document.getElementById('edit_numero_mesa').value = numero;
                document.getElementById('edit_capacidad').value = capacidad;
                document.getElementById('edit_estado').value = estado;
            });
            
            // Modal Eliminar
            const modalEliminar = document.getElementById('modalEliminarMesa');
            modalEliminar.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const numero = button.getAttribute('data-numero');
                
                document.getElementById('delete_id_mesa').value = id;
                document.getElementById('delete_numero_mesa').textContent = 'Mesa ' + numero;
            });
            
            // Auto-refresh cada 30 segundos para actualizar estados
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        });
    </script>
</body>
</html>