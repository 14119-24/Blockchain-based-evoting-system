<?php

class AdminDatabase {
    private $host = "localhost";
    private $db_name = "admin";
    private $username = "your_admin_db_user";
    private $password = "your_admin_db_password";
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Admin database connection error: " . $e->getMessage());
        }

        return $this->conn;
    }

    public function getConnection() {
        return $this->connect();
    }
}
?>
