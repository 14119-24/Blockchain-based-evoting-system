<?php
/**
 * XAMPP Connection Verification Script
 * Run this to confirm XAMPP integration is working
 */

echo "=== Blockchain Voting System - XAMPP Integration Check ===\n\n";

// Check 1: Database Configuration
echo "1. DATABASE CONFIGURATION CHECK\n";
echo "   Host: localhost\n";
echo "   Database: voting_system\n";
echo "   Username: root\n";
echo "   Password: (empty - XAMPP default)\n\n";

// Check 2: Attempt Connection
echo "2. DATABASE CONNECTION TEST\n";
try {
    $dsn = "mysql:host=localhost";
    $pdo = new PDO($dsn, "root", "");
    echo "   ✓ Connected to MySQL server\n";
    
    // Check if database exists
    $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'voting_system'");
    $database_exists = $result->fetch();
    
    if ($database_exists) {
        echo "   ✓ 'voting_system' database exists\n";
        
        // Connect to the database
        $pdo = new PDO("mysql:host=localhost;dbname=voting_system", "root", "");
        
        // Check tables
        echo "\n3. DATABASE TABLES CHECK\n";
        $tables = ['voters', 'elections', 'candidates', 'votes', 'blockchain_blocks'];
        $missing_tables = [];
        
        foreach ($tables as $table) {
            $result = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($result->fetch()) {
                echo "   ✓ Table '$table' exists\n";
            } else {
                echo "   ✗ Table '$table' MISSING\n";
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            echo "\n✅ ALL CHECKS PASSED - System is ready!\n";
            echo "\nAccess the application at: http://localhost/voting_system/public/\n";
        } else {
            echo "\n⚠️  MISSING TABLES DETECTED\n";
            echo "Run database setup at: http://localhost/voting_system/public/setup.html\n";
        }
        
    } else {
        echo "   ✗ 'voting_system' database does NOT exist\n";
        echo "\n⚠️  DATABASE NOT INITIALIZED\n";
        echo "Run setup at: http://localhost/voting_system/public/setup.html\n";
    }
    
} catch (PDOException $e) {
    echo "   ✗ Connection failed: " . $e->getMessage() . "\n";
    echo "\n❌ XAMPP MySQL may not be running\n";
    echo "Start MySQL from XAMPP Control Panel and try again\n";
}

echo "\n=== End of Check ===\n";
?>
