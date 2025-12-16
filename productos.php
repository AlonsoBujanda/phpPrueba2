<?php
session_start();
require_once 'config/database.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$roles_permitidos = ['Administrador', 'Gerente'];
if (!in_array($rol, $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->connect();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Obtener categorías para dropdown
$categorias = [];
try {
    $stmt = $pdo->query("SELECT * FROM Categorias WHERE estado = 1 ORDER BY nombre_categoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar categorías: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $precio = $_POST['precio'];
            $id_categoria = $_POST['id_categoria'];
            $costo = $_POST['costo'];
            $tiempo_preparacion = $_POST['tiempo_preparacion'];
            $disponibilidad = isset($_POST['disponibilidad']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO Productos 
                    (nombre, descripcion, precio, id_categoria, costo, tiempo_preparacion, disponibilidad) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $precio, $id_categoria, $costo, $tiempo_preparacion, $disponibilidad]);
                $mensaje = "Producto creado exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al crear producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'update':
            $id_producto = $_POST['id_producto'];
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $precio = $_POST['precio'];
            $id_categoria = $_POST['id_categoria'];
            $costo = $_POST['costo'];
            $tiempo_preparacion = $_POST['tiempo_preparacion'];
            $disponibilidad = isset($_POST['disponibilidad']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("UPDATE Productos SET 
                    nombre = ?, descripcion = ?, precio = ?, id_categoria = ?, 
                    costo = ?, tiempo_preparacion = ?, disponibilidad = ? 
                    WHERE id_producto = ?");
                $stmt->execute([$nombre, $descripcion, $precio, $id_categoria, $costo, $tiempo_preparacion, $disponibilidad, $id_producto]);
                $mensaje = "Producto actualizado exitosamente";
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'delete':
            $id_producto = $_POST['id_producto'];
            
            try {
                // Verificar si el producto está en pedidos activos
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM DetallesPedido dp 
                    JOIN Pedidos p ON dp.id_pedido = p.id_pedido 
                    WHERE dp.id_producto = ? AND p.estado NOT IN ('Entregado', 'Cancelado')");
                $stmt->execute([$id_producto]);
                $en_pedidos = $stmt->fetchColumn();
                
                if ($en_pedidos > 0) {
                    $mensaje = "No se puede eliminar el producto porque está en pedidos activos";
                    $tipo_mensaje = 'warning';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Productos WHERE id_producto = ?");
                    $stmt->execute([$id_producto]);
                    $mensaje = "Producto eliminado exitosamente";
                    $tipo_mensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar producto: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener todos los productos con información de categoría
try {
    $stmt = $pdo->query("
        SELECT p.*, c.nombre_categoria 
        FROM Productos p 
        LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria 
        ORDER BY c.nombre_categoria, p.nombre
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productos = [];
    $mensaje = "Error al cargar productos: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Obtener estadísticas
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN disponibilidad = 1 THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN disponibilidad = 0 THEN 1 ELSE 0 END) as no_disponibles
    FROM Productos");
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Sistema Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #38b000;
            --warning: #ff9e00;
            --danger: #ef476f;
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
        
        .status-disponible { background-color: #d4edda; color: #155724; }
        .status-no-disponible { background-color: #f8d7da; color: #721c24; }
        
        .price-badge {
            background-color: #e3f2fd;
            color: #1565c0;
            padding: 3px 8px;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .category-badge {
            background-color: #f3e5f5;
            color: #7b1fa2;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
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
            <small>Gestión de Productos</small>
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
            <a href="mesas.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-table me-2"></i> Mesas
            </a>
            <a href="productos.php" class="nav-link text-white py-2 px-3 bg-white bg-opacity-10 rounded">
                <i class="bi bi-cup-straw me-2"></i> Productos
            </a>
            <a href="pedidos.php" class="nav-link text-white py-2 px-3">
                <i class="bi bi-receipt me-2"></i> Pedidos
            </a>
            <a href="logout.php" class="nav-link text-white py-2 px-3 text-danger">
                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-cup-straw me-2"></i> Gestión de Productos</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto">
                <i class="bi bi-plus-circle me-1"></i> Nuevo Producto
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
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Productos</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['total'] ?? 0; ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Disponibles</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['disponibles'] ?? 0; ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">No Disponibles</h6>
                            <h3 class="mb-0"><?php echo $estadisticas['no_disponibles'] ?? 0; ?></h3>
                        </div>
                        <div class="text-danger">
                            <i class="bi bi-x-circle" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Productos -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Costo</th>
                            <th>Tiempo Prep.</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos) > 0): ?>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?php echo $producto['id_producto']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                    <?php if ($producto['descripcion']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="category-badge">
                                        <?php echo htmlspecialchars($producto['nombre_categoria'] ?? 'Sin categoría'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="price-badge">
                                        $<?php echo number_format($producto['precio'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        $<?php echo number_format($producto['costo'], 2); ?>
                                    </small>
                                </td>
                                <td>
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo $producto['tiempo_preparacion']; ?> min
                                </td>
                                <td>
                                    <?php
                                    $estado_class = $producto['disponibilidad'] ? 'status-disponible' : 'status-no-disponible';
                                    $estado_text = $producto['disponibilidad'] ? 'Disponible' : 'No Disponible';
                                    ?>
                                    <span class="status-badge <?php echo $estado_class; ?>">
                                        <?php echo $estado_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary btn-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarProducto"
                                            data-id="<?php echo $producto['id_producto']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                            data-descripcion="<?php echo htmlspecialchars($producto['descripcion']); ?>"
                                            data-precio="<?php echo $producto['precio']; ?>"
                                            data-costo="<?php echo $producto['costo']; ?>"
                                            data-categoria="<?php echo $producto['id_categoria']; ?>"
                                            data-tiempo="<?php echo $producto['tiempo_preparacion']; ?>"
                                            data-disponibilidad="<?php echo $producto['disponibilidad']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-outline-danger btn-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEliminarProducto"
                                            data-id="<?php echo $producto['id_producto']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-cup-straw text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No hay productos registrados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalAgregarProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="id_categoria" class="form-label">Categoría *</label>
                                <select class="form-select" id="id_categoria" name="id_categoria" required>
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="precio" class="form-label">Precio de Venta *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio" name="precio" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="costo" class="form-label">Costo *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo" name="costo" 
                                           step="0.01" min="0" value="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="tiempo_preparacion" class="form-label">Tiempo Preparación *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="tiempo_preparacion" 
                                           name="tiempo_preparacion" min="1" value="15" required>
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="disponibilidad" name="disponibilidad" checked>
                                <label class="form-check-label" for="disponibilidad">
                                    Producto disponible
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Producto -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_producto" id="edit_id_producto">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nombre" class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_id_categoria" class="form-label">Categoría *</label>
                                <select class="form-select" id="edit_id_categoria" name="id_categoria" required>
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_precio" class="form-label">Precio de Venta *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit_precio" name="precio" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_costo" class="form-label">Costo *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="edit_costo" name="costo" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_tiempo_preparacion" class="form-label">Tiempo Preparación *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="edit_tiempo_preparacion" 
                                           name="tiempo_preparacion" min="1" required>
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       id="edit_disponibilidad" name="disponibilidad">
                                <label class="form-check-label" for="edit_disponibilidad">
                                    Producto disponible
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Producto -->
    <div class="modal fade" id="modalEliminarProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_producto" id="delete_id_producto">
                        
                        <p>¿Está seguro de eliminar el producto <strong id="delete_nombre_producto"></strong>?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal Editar Producto
            const modalEditarProducto = document.getElementById('modalEditarProducto');
            modalEditarProducto.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('edit_id_producto').value = button.getAttribute('data-id');
                document.getElementById('edit_nombre').value = button.getAttribute('data-nombre');
                document.getElementById('edit_descripcion').value = button.getAttribute('data-descripcion');
                document.getElementById('edit_precio').value = button.getAttribute('data-precio');
                document.getElementById('edit_costo').value = button.getAttribute('data-costo');
                document.getElementById('edit_id_categoria').value = button.getAttribute('data-categoria');
                document.getElementById('edit_tiempo_preparacion').value = button.getAttribute('data-tiempo');
                
                const disponibilidad = button.getAttribute('data-disponibilidad') === '1';
                document.getElementById('edit_disponibilidad').checked = disponibilidad;
            });
            
            // Modal Eliminar Producto
            const modalEliminarProducto = document.getElementById('modalEliminarProducto');
            modalEliminarProducto.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('delete_id_producto').value = button.getAttribute('data-id');
                document.getElementById('delete_nombre_producto').textContent = button.getAttribute('data-nombre');
            });
            
            // Validación de precios
            const precioInput = document.getElementById('precio');
            const costoInput = document.getElementById('costo');
            
            if (precioInput && costoInput) {
                function validarPrecios() {
                    const precio = parseFloat(precioInput.value) || 0;
                    const costo = parseFloat(costoInput.value) || 0;
                    
                    if (costo > precio) {
                        costoInput.classList.add('is-invalid');
                        costoInput.nextElementSibling?.classList.add('d-none');
                        const feedback = costoInput.parentElement.querySelector('.invalid-feedback');
                        if (!feedback) {
                            const div = document.createElement('div');
                            div.className = 'invalid-feedback';
                            div.textContent = 'El costo no puede ser mayor al precio de venta';
                            costoInput.parentElement.appendChild(div);
                        }
                    } else {
                        costoInput.classList.remove('is-invalid');
                    }
                }
                
                precioInput.addEventListener('input', validarPrecios);
                costoInput.addEventListener('input', validarPrecios);
            }
        });
    </script>
</body>
</html>