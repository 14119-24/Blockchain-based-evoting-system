<?php
/**
 * Setup script to create payment_requests table in localhost database
 * Run this in your browser: http://localhost/voting_system/setup-payment-table.php
 */

require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->connect();

if (!$pdo) {
    die('Failed to connect to database. Check config/database.php');
}

// Read and execute the SQL
$sql = file_get_contents(__DIR__ . '/database/create-payment-table.sql');

try {
    $pdo->exec($sql);
    
    // Verify the table was created
    $stmt = $pdo->query("DESC payment_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h2 style="color: green;">✓ Payment Table Created Successfully!</h2>';
    echo '<p><strong>Database:</strong> voting_system</p>';
    echo '<p><strong>Table:</strong> payment_requests</p>';
    echo '<h3>Table Columns:</h3>';
    echo '<ul>';
    foreach ($columns as $col) {
        echo '<li><strong>' . $col['Field'] . '</strong> (' . $col['Type'] . ')</li>';
    }
    echo '</ul>';
    
    echo '<hr>';
    echo '<h3>Next Steps:</h3>';
    echo '<ol>';
    echo '<li>Go to <a href="public/candidate-register.html">candidate registration</a></li>';
    echo '<li>Fill in the form and click "Send Payment Prompt"</li>';
    echo '<li>The payment request will be tracked in the payment_requests table</li>';
    echo '</ol>';
    
} catch (PDOException $e) {
    echo '<h2 style="color: red;">✗ Error Creating Table</h2>';
    echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
    echo '<p>Make sure the database "voting_system" exists.</p>';
}
?>
