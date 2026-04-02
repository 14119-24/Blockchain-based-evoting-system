<?php
// Database setup script for Blockchain Voting System

echo "=== Blockchain Voting System - Database Setup ===\n\n";

// Database credentials
$host = "localhost";
$username = "root";
$password = "";
$db_name = "voting_system";

try {
    // Connect to MySQL without database
    echo "Connecting to MySQL server...\n";
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected successfully\n\n";
    
    // Create database
    echo "Creating database '$db_name'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
    echo "✓ Database created/verified\n\n";
    
    // Connect to the voting_system database
    echo "Connecting to voting_system database...\n";
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to voting_system\n\n";
    
    // Read and execute schema
    echo "Reading database schema...\n";
    $schema_file = __DIR__ . '/database/schema.sql';
    
    if (!file_exists($schema_file)) {
        echo "✗ Schema file not found at: $schema_file\n";
        exit(1);
    }
    
    $schema = file_get_contents($schema_file);
    
    // Split SQL statements and execute them
    $statements = preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $schema);
    
    $table_count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $statement, $matches)) {
                    echo "✓ Created table: {$matches[1]}\n";
                    $table_count++;
                }
            } catch (PDOException $e) {
                // Skip if table already exists
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "✗ Error executing statement: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "\n✓ Database setup completed successfully!\n";
    echo "✓ $table_count tables created/verified\n\n";
    
    // Create admin user
    echo "Creating default admin user...\n";
    $admin_email = "admin@votingsystem.local";
    $admin_password = password_hash("Admin123!", PASSWORD_BCRYPT);
    $admin_voter_id = "ADMIN" . strtoupper(substr(md5(time()), 0, 8));
    $national_id_hash = hash('sha256', "ADMIN_NATIONAL_ID");
    
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
        echo "✓ Admin user created\n";
        echo "  Email: $admin_email\n";
        echo "  Password: Admin123!\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "✓ Admin user already exists\n\n";
        } else {
            echo "✗ Error creating admin user: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "=== Setup Complete ===\n";
    echo "You can now access the voting system at: http://localhost/voting_system/public/index.html\n";
    
} catch (PDOException $e) {
    echo "✗ Connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
