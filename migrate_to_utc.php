#!/usr/bin/env php
<?php
/**
 * Migration Runner for UTC Timestamp Update
 * Run this script to update existing database to use UTC timestamps
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EmailTester\config\Database;

// Color output for CLI
function colorOutput($text, $color = 'white') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function logMessage($message, $color = 'white') {
    echo colorOutput('[' . date('Y-m-d H:i:s') . '] ' . $message, $color) . PHP_EOL;
}

try {
    logMessage('Starting UTC timestamp migration...', 'blue');
    
    // Load database configuration from .env file
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        throw new Exception('Configuration file (.env) not found');
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    // Configure database connection
    Database::configure([
        'host' => $config['DB_HOST'] ?? 'localhost',
        'port' => $config['DB_PORT'] ?? 3306,
        'database' => $config['DB_DATABASE'] ?? 'smtp_test_tool',
        'username' => $config['DB_USERNAME'] ?? 'smtp_user',
        'password' => $config['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4'
    ]);
    
    $pdo = Database::getConnection();
    logMessage('Database connection established', 'green');
    
    // Check if migration is needed
    $stmt = $pdo->query("SHOW COLUMNS FROM test_logs LIKE 'test_timestamp'");
    $column = $stmt->fetch();
    
    if (!$column) {
        logMessage('Migration not needed - test_timestamp column not found', 'yellow');
        exit(0);
    }
    
    // Check if already using UTC_TIMESTAMP default
    if (strpos($column['Default'], 'utc_timestamp') !== false || 
        strpos(strtolower($column['Default']), 'utc_timestamp') !== false) {
        logMessage('Migration already applied - columns already use UTC_TIMESTAMP', 'yellow');
        exit(0);
    }
    
    logMessage('Migration needed - updating timestamp columns to UTC format...', 'blue');
    
    // Read and execute migration script
    $migrationFile = __DIR__ . '/../database/migrations/update_timestamps_to_utc.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception('Migration file not found: ' . $migrationFile);
    }
    
    $migrationSQL = file_get_contents($migrationFile);
    
    // Split the migration into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $migrationSQL)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $pdo->beginTransaction();
    
    try {
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                logMessage('Executing: ' . substr(trim($statement), 0, 50) . '...', 'white');
                $pdo->exec($statement);
            }
        }
        
        $pdo->commit();
        logMessage('Migration completed successfully!', 'green');
        
        // Verify the migration
        $stmt = $pdo->query("SHOW COLUMNS FROM test_logs LIKE 'test_timestamp'");
        $newColumn = $stmt->fetch();
        
        if ($newColumn && (strpos(strtolower($newColumn['Default']), 'utc_timestamp') !== false)) {
            logMessage('Verification successful - columns now use UTC_TIMESTAMP', 'green');
        } else {
            logMessage('Warning: Verification failed - please check column definitions manually', 'yellow');
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    logMessage('Migration failed: ' . $e->getMessage(), 'red');
    exit(1);
}

logMessage('Migration process completed!', 'green');
logMessage('All timestamps are now stored in UTC format and will display in browser local time.', 'blue');
?>
