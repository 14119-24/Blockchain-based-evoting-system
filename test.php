<?php
// Quick diagnostic test
header('Content-Type: application/json');

$tests = [];

// Test 1: Check config file
$configPath = __DIR__ . '/../config/database.php';
$tests['config_file_exists'] = file_exists($configPath);

if ($tests['config_file_exists']) {
    require_once($configPath);
}

// Test 2: Check database connection
try {
    if (class_exists('Database')) {
        $db = new Database();
        $conn = $db->getConnection();
        $tests['database_connected'] = $conn !== null;
        
        // Test 3: Check if voters table exists
        if ($conn) {
            $query = "SHOW TABLES LIKE 'voters'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $tests['voters_table_exists'] = $stmt->rowCount() > 0;
            
            // Test 4: Try to get table structure
            if ($tests['voters_table_exists']) {
                $query = "DESCRIBE voters";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $tests['voters_table_columns'] = $columns;
            }
        }
    } else {
        $tests['database_connected'] = false;
        $tests['error'] = 'Database class not found';
    }
} catch (Exception $e) {
    $tests['database_error'] = $e->getMessage();
}

echo json_encode($tests, JSON_PRETTY_PRINT);
?>
