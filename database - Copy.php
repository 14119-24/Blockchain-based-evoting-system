<?php

$localConfigPath = __DIR__ . '/database.local.php';
if (file_exists($localConfigPath)) {
    require_once $localConfigPath;
    return;
}

require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        $this->host = (string) app_env('DB_HOST', '127.0.0.1');
        $this->port = (string) app_env('DB_PORT', '3306');
        $this->db_name = (string) app_env('DB_NAME', 'voting_system');
        $this->username = (string) app_env('DB_USERNAME', 'root');
        $this->password = (string) app_env('DB_PASSWORD', '');
        $this->charset = (string) app_env('DB_CHARSET', 'utf8mb4');
    }

    private function buildDsn($includeDatabase = true) {
        $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port;

        if ($includeDatabase && $this->db_name !== '') {
            $dsn .= ';dbname=' . $this->db_name;
        }

        return $dsn . ';charset=' . $this->charset;
    }

    private function createConnection($includeDatabase = true) {
        return new PDO(
            $this->buildDsn($includeDatabase),
            $this->username,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = $this->createConnection(true);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
        }

        return $this->conn;
    }

    public function connectWithoutDatabase() {
        try {
            return $this->createConnection(false);
        } catch (PDOException $e) {
            error_log('Database server connection error: ' . $e->getMessage());
            return null;
        }
    }

    public function getConnection() {
        return $this->connect();
    }

    public function getDatabaseName() {
        return $this->db_name;
    }
}
