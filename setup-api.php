<?php
// c:\xampp\htdocs\voting_system\setup-api.php
// Database Setup API

header('Content-Type: application/json');

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
    // Database credentials
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $db_name = 'voting_system';
    
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $status['database_connected'] = true;
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
    
    // Select the database
    $pdo->exec("USE `$db_name`");
    
    // Read schema file
    $schema_file = __DIR__ . '/database/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception('Schema file not found');
    }
    
    $schema = file_get_contents($schema_file);
    
    // Split and execute statements
    $statements = preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
    }
    
    $status['tables_created'] = true;
    
    // Create admin user
    $admin_email = 'admin@votingsystem.local';
    $admin_password = password_hash('Admin123!', PASSWORD_BCRYPT);
    $admin_voter_id = 'ADMIN' . strtoupper(substr(md5(time()), 0, 8));
    $national_id_hash = hash('sha256', 'ADMIN_NATIONAL_ID');
    
    try {
        $stmt = $pdo->prepare("INSERT INTO voters (voter_id, national_id_hash, full_name, email, password_hash, user_type, is_verified) 
                              VALUES (:voter_id, :national_id_hash, :full_name, :email, :password_hash, 'admin', 1)");
        $stmt->execute([
            ':voter_id' => $admin_voter_id,
            ':national_id_hash' => $national_id_hash,
            ':full_name' => 'Administrator',
            ':email' => $admin_email,
            ':password_hash' => $admin_password
        ]);
        $status['admin_created'] = true;
    } catch (PDOException $e) {
        // Admin may already exist, that's OK
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
?>
