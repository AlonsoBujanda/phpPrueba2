<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'RestauranteDB';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function __construct() {
        // Constructor vacío
    }
    
    public function connect() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            } catch(PDOException $e) {
                die("Error de conexión a la base de datos: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
    
    public function close() {
        $this->conn = null;
    }
    
    public function testConnection() {
        try {
            $this->connect();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Función helper global
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db->connect();
}
?>