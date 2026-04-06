<?php
// Database Setup API

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$action = $jsonInput['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'setup_database') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

$status = [
    'database_connected' => false,
    'tables_created' => false,
    'admin_created' => false
];

try {
    $database = new Database();
    $dbName = $database->getDatabaseName();

    $pdo = $database->connectWithoutDatabase();
    if (!$pdo) {
        throw new Exception('Unable to connect to the database server');
    }

    $status['database_connected'] = true;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");

    $pdo = $database->connect();
    if (!$pdo) {
        throw new Exception('Unable to connect to the application database');
    }

    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception('Schema file not found');
    }

    $schema = file_get_contents($schemaFile);
    $statements = preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $schema);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    $status['tables_created'] = true;

    $adminEmail = 'admin@votingsystem.local';
    $adminPassword = password_hash('Admin123!', PASSWORD_BCRYPT);
    $adminVoterId = 'ADMIN' . strtoupper(substr(md5((string) time()), 0, 8));
    $nationalIdHash = hash('sha256', 'ADMIN_NATIONAL_ID');

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO voters (voter_id, national_id_hash, full_name, email, password_hash, user_type, is_verified)
             VALUES (:voter_id, :national_id_hash, :full_name, :email, :password_hash, 'admin', 1)"
        );
        $stmt->execute([
            ':voter_id' => $adminVoterId,
            ':national_id_hash' => $nationalIdHash,
            ':full_name' => 'Administrator',
            ':email' => $adminEmail,
            ':password_hash' => $adminPassword
        ]);
        $status['admin_created'] = true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') === false) {
            throw $e;
        }
        $status['admin_created'] = true;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully! Redirecting to home page...',
        'status' => $status
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'status' => $status
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'status' => $status
    ]);
}
