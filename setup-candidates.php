<?php
/**
 * Candidate Portal Setup
 * This script initializes the candidate tables in the database
 */

require_once __DIR__ . '/config/database.php';

$tables_created = 0;
$errors = [];

$db = new Database();
$pdo = $db->connect();
if (!$pdo) {
    die('Failed to connect to database. Check config/database.php');
}

try {
    echo "<h2>Setting up Candidate Portal...</h2>";
    
    // Read and execute the schema
    $schema = file_get_contents(__DIR__ . '/database/candidates-schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $tables_created++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                $errors[] = $e->getMessage();
                echo "✗ Error: " . $e->getMessage() . "<br>";
            } else {
                echo "✓ Table already exists<br>";
            }
        }
    }
    
    // Also create payment requests table
    echo "<h3>Setting up Payment System...</h3>";
    $paymentSchema = file_get_contents(__DIR__ . '/database/create-payment-table.sql');
    $paymentStatements = array_filter(array_map('trim', explode(';', $paymentSchema)));
    
    foreach ($paymentStatements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $tables_created++;
            echo "✓ Payment table ready<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                $errors[] = $e->getMessage();
                echo "✗ Error: " . $e->getMessage() . "<br>";
            } else {
                echo "✓ Payment table already exists<br>";
            }
        }
    }

    // Ensure candidate registration tables are created (if separate)
    echo "<h3>Setting up Candidate Registrations...</h3>";
    $regSchemaPath = __DIR__ . '/database/create-candidate-registration-table.sql';
    if (file_exists($regSchemaPath)) {
        $regSchema = file_get_contents($regSchemaPath);
        $regStatements = array_filter(array_map('trim', explode(';', $regSchema)));
        foreach ($regStatements as $statement) {
            if (empty($statement)) continue;
            try {
                $pdo->exec($statement);
                echo "✓ Executed registration statement...<br>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    $errors[] = $e->getMessage();
                    echo "✗ Error: " . $e->getMessage() . "<br>";
                } else {
                    echo "✓ Registration table already exists<br>";
                }
            }
        }
    } else {
        echo "<p style='color:orange;'>Registration schema file not found: database/create-candidate-registration-table.sql</p>";
    }
    
    if (empty($errors)) {
        echo "<h3 style='color: green;'>✓ Candidate portal setup completed successfully!</h3>";
        echo "<p>Candidates can now register at: <a href='public/candidate-register.html'>Candidate Portal</a></p>";
    } else {
        echo "<h3 style='color: orange;'>Setup completed with some warnings</h3>";
        echo "<p>Errors encountered: " . count($errors) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Setup failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Candidate Portal Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        h3 { margin-top: 30px; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <a href="index.html">&lt; Back to Home</a>
</body>
</html>
