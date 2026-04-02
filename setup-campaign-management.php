<?php
/**
 * Setup Campaign Management Tables
 * Run this script to create campaign management database tables
 */

require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->connect();

if (!$pdo) {
    die('Failed to connect to database');
}

try {
    echo "<h2>Setting up Campaign Management Tables...</h2>";
    
    $schema = file_get_contents(__DIR__ . '/database/create-campaign-management-tables.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            echo "✓ " . substr($statement, 0, 80) . "...<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "✗ Error: " . $e->getMessage() . "<br>";
            } else {
                echo "✓ Table already exists<br>";
            }
        }
    }
    
    echo "<h3>✓ Campaign Management Setup Complete</h3>";
    echo "<p><a href='public/admin_new.html'>Return to Admin Panel</a></p>";
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
