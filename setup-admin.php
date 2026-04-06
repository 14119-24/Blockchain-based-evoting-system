<?php

require_once __DIR__ . '/config/admin_database.php';

echo "=== Admin Database Setup ===\n\n";

try {
    $adminDatabase = new AdminDatabase();
    $dbName = $adminDatabase->getDatabaseName();

    echo "Connecting to MySQL server...\n";
    $pdo = $adminDatabase->connectWithoutDatabase();
    if (!$pdo) {
        throw new RuntimeException('Unable to connect to the admin database server');
    }
    echo "Connected successfully\n\n";

    echo "Creating database '$dbName'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    echo "Database created/verified\n\n";

    echo "Loading admin schema...\n";
    $schemaFile = __DIR__ . '/database/admin_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Admin schema file not found: $schemaFile");
    }

    $schema = file_get_contents($schemaFile);
    $statements = preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $schema);

    $pdo = $adminDatabase->connect();
    if (!$pdo) {
        throw new RuntimeException("Unable to connect to admin database '$dbName'");
    }

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '' && strtoupper($statement) !== "USE `ADMIN`" && strtoupper($statement) !== "CREATE DATABASE IF NOT EXISTS `ADMIN`") {
            $pdo->exec($statement);
        }
    }
    echo "Admin schema loaded\n\n";

    echo "Seeding default admin account...\n";
    $adminCode = 'ADM001';
    $adminEmail = 'admin@votingsystem.local';
    $adminPassword = 'Admin123!';
    $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO admins (admin_code, full_name, email, password_hash, role, is_active)
        VALUES (:admin_code, :full_name, :email, :password_hash, 'super_admin', 1)
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            password_hash = VALUES(password_hash),
            role = VALUES(role),
            is_active = VALUES(is_active)
    ");
    $stmt->execute([
        ':admin_code' => $adminCode,
        ':full_name' => 'Administrator',
        ':email' => $adminEmail,
        ':password_hash' => $passwordHash
    ]);

    echo "Default admin ready\n";
    echo "Admin email: $adminEmail\n";
    echo "Admin password: $adminPassword\n\n";

    echo "=== Admin database setup complete ===\n";
} catch (Throwable $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
