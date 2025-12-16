<?php
session_start();

class Auth {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function login($usuario, $password) {
        try {
            $query = "SELECT u.*, r.nombre_rol as rol 
                     FROM Usuarios u 
                     INNER JOIN Roles r ON u.id_rol = r.id_rol 
                     WHERE u.usuario = :usuario AND u.estado = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                // Verificar contraseña
                if (password_verify($password, $user['password'])) {
                    // Actualizar último acceso
                    $this->updateLastAccess($user['id_usuario']);
                    
                    // Crear sesión
                    $_SESSION['user_id'] = $user['id_usuario'];
                    $_SESSION['username'] = $user['usuario'];
                    $_SESSION['nombre'] = $user['nombre'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['rol_id'] = $user['id_rol'];
                    $_SESSION['logged_in'] = true;
                    
                    return ['success' => true, 'rol' => $user['rol']];
                }
            }
            
            return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }
    
    public function isAuthenticated() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getCurrentUser() {
        if ($this->isAuthenticated()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'nombre' => $_SESSION['nombre'],
                'rol' => $_SESSION['rol'],
                'rol_id' => $_SESSION['rol_id']
            ];
        }
        return null;
    }
    
    public function checkRole($rol) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        return $_SESSION['rol'] === $rol;
    }
    
    public function hasPermission($permiso) {
        // Implementar lógica de permisos según sea necesario
        return true;
    }
    
    private function updateLastAccess($user_id) {
        try {
            $query = "UPDATE Usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
        } catch (PDOException $e) {
            // Silenciar error
        }
    }
}

// Crear instancia global
$auth = new Auth();

// Funciones helper
function requireAuth() {
    global $auth;
    if (!$auth->isAuthenticated()) {
        header('Location: ../login.php');
        exit;
    }
}

function requireRole($rol) {
    global $auth;
    requireAuth();
    if (!$auth->checkRole($rol)) {
        header('Location: ../unauthorized.php');
        exit;
    }
}
?>