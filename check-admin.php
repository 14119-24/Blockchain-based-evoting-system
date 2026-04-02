<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../config/database.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if admin exists
    $query = "SELECT voter_id, email, password_hash, admin_verified FROM voters WHERE email = 'admin@votingsystem.local' LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'admin_exists' => true,
            'voter_id' => $admin['voter_id'],
            'email' => $admin['email'],
            'admin_verified' => $admin['admin_verified'],
            'password_hash' => substr($admin['password_hash'], 0, 20) . '...'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'admin_exists' => false,
            'message' => 'Admin account not found. Need to create it.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
