<?php
require_once __DIR__ . '/config/database.php';

$db = new Database();
$pdo = $db->connect();

if (!$pdo) {
    echo "<h2 style='color:red;'>Database connection failed. Check config/database.php</h2>";
    exit;
}

echo "<h2>Creating candidate registration and payment tables</h2>";

$files = [
    __DIR__ . '/database/create-candidate-registration-table.sql',
    __DIR__ . '/database/create-payment-table.sql'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "<p style='color:orange;'>SQL file not found: $file</p>";
        continue;
    }

    $sql = file_get_contents($file);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
            echo "<p style='color:green;'>Executed statement.</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p>Table already exists.</p>";
            } else {
                echo "<p style='color:red;'>SQL Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}

echo "<h3>Done.</h3>";
echo "<p><a href='public/candidate-register.html'>Go to Candidate Portal</a></p>";

?>
