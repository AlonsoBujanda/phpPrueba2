<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y rol
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
if ($rol !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Obtener roles para dropdown
$roles = [];
try {
    $stmt = $pdo->query("SELECT * FROM Roles ORDER BY id_rol");
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = "Error al cargar roles: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nombre = $_POST['nombre'];
            $usuario = $_POST['usuario'];
            $password = $_POST['password'];
            $id_rol = $_POST['id_rol'];
            $email = $_POST['email'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            
            try {
                // Verificar si usuario ya existe
                $stmt = $pdo->prepare("SELECT id_usuario FROM Usuarios WHERE usuario = ?");
                $stmt->execute([$usuario]);
                
                if ($stmt->rowCount() > 0) {
                    $mensaje = "El usuario ya existe";
                    $tipo_mensaje = 'warning';
                } else {
                    // Hash de contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO Usuarios 
                        (nombre, usuario, password, id_rol, email, telefono) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nombre, $usuario, $password_hash, $id_rol, $email, $telefono]);
                    
                    $mensaje = "Usuario creado exitosamente";
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al crear usuario: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'update':
            $id_usuario = $_POST['id_usuario'];
            $nombre = $_POST['nombre'];
            $usuario = $_POST['usuario'];
            $id_rol = $_POST['id_rol'];
            $email = $_POST['email'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $estado = isset($_POST['estado']) ? 1 : 0;
            
            // Si se proporciona nueva contraseña
            $password_update = '';
            if (!empty($_POST['password'])) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $password_update = ", password = '$password_hash'";
            }
            
            try {
                $sql = "UPDATE Usuarios SET 
                        nombre = ?, usuario = ?, id_rol = ?, email = ?, telefono = ?, estado = ?
                        $password_update
                        WHERE id_usuario = ?";
                
                $params = [$nombre, $usuario, $id_rol, $email, $telefono, $estado, $id_usuario];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $mensaje = "Usuario actualizado exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar usuario: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'delete':
            $id_usuario = $_POST['id_usuario'];
            
            try {
                // No permitir eliminar al propio usuario administrador
                if ($id_usuario == $_SESSION['user_id']) {
                    $mensaje = "No puede eliminar su propio usuario";
                    $tipo_mensaje = 'warning';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Usuarios WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    
                    $mensaje = "Usuario eliminado exitosamente";
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar usuario: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener todos los usuarios con información de rol
try {
    $stmt = $pdo->query("
        SELECT u.*, r.nombre_rol 
        FROM Usuarios u 
        LEFT JOIN Roles r ON u.id_rol = r.id_rol 
        ORDER BY u.fecha_creacion DESC
    ");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
    $mensaje = "Error al cargar usuarios: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener estadísticas
$stats = [];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos,
        COUNT(DISTINCT id_rol) as roles_utilizados
        FROM Usuarios");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'activos' => 0, 'roles_utilizados' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header-users {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        
        .role-badge {
            background-color: #e3f2fd;
            color: #1565c0;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="header-users">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-people me-3"></i> Gestión de Usuarios</h1>
                    <p class="mb-0">Administración de usuarios del sistema</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalAgregarUsuario">
                        <i class="bi bi-plus-circle me-1"></i> Nuevo Usuario
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light ms-2">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </div>
            </div>
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
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted mb-1">Total Usuarios</h6>
                    <h2 class="text-primary"><?php echo $stats['total']; ?></h2>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted mb-1">Usuarios Activos</h6>
                    <h2 class="text-success"><?php echo $stats['activos']; ?></h2>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted mb-1">Roles Utilizados</h6>
                    <h2 class="text-info"><?php echo $stats['roles_utilizados']; ?></h2>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Usuarios -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Rol</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios) > 0): ?>
                            <?php foreach ($usuarios as $usuario): 
                                $iniciales = strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['nombre'], strpos($usuario['nombre'], ' ') + 1, 1));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-3">
                                            <?php echo $iniciales; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($usuario['usuario']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                <td>
                                    <span class="role-badge">
                                        <?php echo htmlspecialchars($usuario['nombre_rol']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                                <td>
                                    <?php if ($usuario['estado']): ?>
                                    <span class="status-active">Activo</span>
                                    <?php else: ?>
                                    <span class="status-inactive">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['ultimo_acceso']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarUsuario"
                                            data-id="<?php echo $usuario['id_usuario']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                            data-usuario="<?php echo htmlspecialchars($usuario['usuario']); ?>"
                                            data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($usuario['telefono']); ?>"
                                            data-rol="<?php echo $usuario['id_rol']; ?>"
                                            data-estado="<?php echo $usuario['estado']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEliminarUsuario"
                                            data-id="<?php echo $usuario['id_usuario']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No hay usuarios registrados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Usuario -->
    <div class="modal fade" id="modalAgregarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_rol" class="form-label">Rol *</label>
                            <select class="form-select" id="id_rol" name="id_rol" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_usuario" id="edit_id_usuario">
                        
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="edit_usuario" name="usuario" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Nueva Contraseña (dejar en blanco para no cambiar)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_id_rol" class="form-label">Rol *</label>
                            <select class="form-select" id="edit_id_rol" name="id_rol" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>">
                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="edit_telefono" name="telefono">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="edit_estado" name="estado">
                                <label class="form-check-label" for="edit_estado">
                                    Usuario Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Usuario -->
    <div class="modal fade" id="modalEliminarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_usuario" id="delete_id_usuario">
                        
                        <p>¿Está seguro de eliminar al usuario <strong id="delete_nombre_usuario"></strong>?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Editar Usuario
            const modalEditar = document.getElementById('modalEditarUsuario');
            modalEditar.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('edit_id_usuario').value = button.getAttribute('data-id');
                document.getElementById('edit_nombre').value = button.getAttribute('data-nombre');
                document.getElementById('edit_usuario').value = button.getAttribute('data-usuario');
                document.getElementById('edit_email').value = button.getAttribute('data-email');
                document.getElementById('edit_telefono').value = button.getAttribute('data-telefono');
                document.getElementById('edit_id_rol').value = button.getAttribute('data-rol');
                document.getElementById('edit_estado').checked = button.getAttribute('data-estado') === '1';
            });
            
            // Modal Eliminar Usuario
            const modalEliminar = document.getElementById('modalEliminarUsuario');
            modalEliminar.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('delete_id_usuario').value = button.getAttribute('data-id');
                document.getElementById('delete_nombre_usuario').textContent = button.getAttribute('data-nombre');
            });
            
            // Validación de contraseña
            const formAgregar = document.querySelector('#modalAgregarUsuario form');
            if (formAgregar) {
                formAgregar.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('La contraseña debe tener al menos 6 caracteres');
                    }
                });
            }
        });
    </script>
</body>
</html>