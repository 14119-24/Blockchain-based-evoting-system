<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../config/database.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $admin_email = 'admin@votingsystem.local';
    $admin_password = 'Admin123!';
    $admin_password_hash = password_hash($admin_password, PASSWORD_BCRYPT);
    $admin_voter_id = 'ADMIN' . strtoupper(substr(md5(time()), 0, 8));
    $national_id_hash = hash('sha256', 'ADMIN_NATIONAL_ID_' . time());
    
    // Insert admin user
    $query = "INSERT INTO voters (voter_id, national_id_hash, full_name, email, password_hash, admin_verified, registration_status) 
              VALUES (:voter_id, :national_id_hash, :full_name, :email, :password_hash, 1, 'verified')";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':voter_id' => $admin_voter_id,
        ':national_id_hash' => $national_id_hash,
        ':full_name' => 'System Administrator',
        ':email' => $admin_email,
        ':password_hash' => $admin_password_hash
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin account created successfully',
        'voter_id' => $admin_voter_id,
        'email' => $admin_email,
        'password' => $admin_password
    ]);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin account already exists'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
