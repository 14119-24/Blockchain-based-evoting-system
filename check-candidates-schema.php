<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../config/database.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get candidates table structure
    $query = "DESCRIBE candidates";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'table' => 'candidates',
        'columns' => $columns
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
