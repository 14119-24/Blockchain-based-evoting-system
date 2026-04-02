<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../config/database.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all voters
    $query = "SELECT voter_id, full_name, email, admin_verified, registration_status FROM voters LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($voters),
        'voters' => $voters
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
