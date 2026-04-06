<?php
// Database setup script for Blockchain Voting System

require_once __DIR__ . '/config/database.php';

echo "=== Blockchain Voting System - Database Setup ===\n\n";

try {
    $database = new Database();
    $dbName = $database->getDatabaseName();

    echo "Connecting to MySQL server...\n";
    $pdo = $database->connectWithoutDatabase();
    if (!$pdo) {
        throw new RuntimeException('Unable to connect to the database server');
    }
    echo "Connected successfully\n\n";

    echo "Creating database '$dbName'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    echo "Database created/verified\n\n";

    echo "Connecting to application database...\n";
    $pdo = $database->connect();
    if (!$pdo) {
        throw new RuntimeException("Unable to connect to database '$dbName'");
    }
    echo "Connected to $dbName\n\n";

    echo "Reading database schema...\n";
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        echo "Schema file not found at: $schemaFile\n";
        exit(1);
    }

    $schema = file_get_contents($schemaFile);
    $statements = preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $schema);

    $tableCount = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
            if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $statement, $matches)) {
                echo "Created table: {$matches[1]}\n";
                $tableCount++;
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "Error executing statement: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nDatabase setup completed successfully!\n";
    echo "$tableCount tables created/verified\n\n";

    echo "Creating default admin user...\n";
    $adminEmail = 'admin@votingsystem.local';
    $adminPassword = password_hash('Admin123!', PASSWORD_BCRYPT);
    $adminVoterId = 'ADMIN' . strtoupper(substr(md5((string) time()), 0, 8));
    $nationalIdHash = hash('sha256', 'ADMIN_NATIONAL_ID');

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO voters (voter_id, national_id_hash, full_name, email, password_hash, user_type, is_verified)
             VALUES (:voter_id, :national_id_hash, :full_name, :email, :password_hash, 'admin', 1)"
        );
        $stmt->execute([
            ':voter_id' => $adminVoterId,
            ':national_id_hash' => $nationalIdHash,
            ':full_name' => 'Administrator',
            ':email' => $adminEmail,
            ':password_hash' => $adminPassword
        ]);
        echo "Admin user created\n";
        echo "Email: $adminEmail\n";
        echo "Password: Admin123!\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "Admin user already exists\n\n";
        } else {
            echo "Error creating admin user: " . $e->getMessage() . "\n\n";
        }
    }

    echo "=== Setup Complete ===\n";
    echo "You can now access the voting system from your deployed public URL.\n";
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
