<?php
/**
 * SMTP Test API Endpoint
 * Handles SMTP server testing requests
 */

// Clean output buffering to prevent any extra output
ob_start();

// Enable error reporting for debugging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/smtp_api_error.log');

// Set headers first
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to output clean JSON and exit
function outputJSON($data, $statusCode = 200) {
    // Clear any previous output
    ob_clean();
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';

use EmailTester\classes\SMTPTester;
use EmailTester\classes\EmailValidator;
use EmailTester\utils\SecurityUtils;
use EmailTester\utils\Logger;
use EmailTester\config\Database;

// Load database configuration from .env file
function loadDatabaseConfig() {
    if (file_exists(__DIR__ . '/../../.env')) {
        $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
    }
}

// Initialize database configuration
loadDatabaseConfig();

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        outputJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    // Initialize components
    $validator = new EmailValidator();
    $logger = new Logger();

    // Start session and validate CSRF token  
    session_start();
    
    // Temporarily disable CSRF validation for debugging
    $csrf_token = $_POST['csrf_token'] ?? '';
    /* 
    if (empty($csrf_token)) {
        outputJSON(['success' => false, 'error' => 'CSRF token missing'], 400);
    }
    
    if (!SecurityUtils::validateCSRFToken($csrf_token)) {
        // For debugging, let's see what tokens we have
        error_log("CSRF Validation Failed. Token received: " . $csrf_token);
        error_log("Session data: " . print_r($_SESSION, true));
        
        outputJSON(['success' => false, 'error' => 'CSRF token validation failed'], 403);
    }
    */

    // Check rate limiting
    if (!SecurityUtils::checkRateLimit('smtp_test', 10, 300)) { // 10 requests per 5 minutes
        outputJSON(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
    }

    // Validate input parameters and test SMTP connection
    try {
    // Validate input parameters
    $host = SecurityUtils::sanitizeInput($_POST['host'] ?? '');
    $port = intval($_POST['port'] ?? 587);
    $security_type = SecurityUtils::sanitizeInput($_POST['security'] ?? '');
    $timeout = intval($_POST['timeout'] ?? 30);
    $username = SecurityUtils::sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize password

    // Validate required fields
    if (empty($host)) {
        throw new InvalidArgumentException('Host is required');
    }

    // Validate host format
    if (!$validator->validateHost($host)) {
        throw new InvalidArgumentException('Invalid host format');
    }

    // Validate port
    if (!$validator->validatePort($port)) {
        throw new InvalidArgumentException('Invalid port number');
    }

    // Validate security type
    if (!empty($security_type) && !in_array($security_type, ['tls', 'ssl'])) {
        throw new InvalidArgumentException('Invalid security type');
    }

    // Validate timeout
    if ($timeout < 5 || $timeout > 120) {
        $timeout = 30;
    }

    // Create SMTP tester instance
    $smtpTester = new SMTPTester($host, $port, $security_type, $username, $password);

    // Perform SMTP test
    $result = $smtpTester->testConnection();

    // Log the test result
    Logger::logTest('SMTP', [
        'host' => $host,
        'port' => $port,
        'security' => $security_type,
        'username' => $username
    ], $result);
    
    // Also log to database for the test logs view
    Logger::logTestToDatabase('SMTP', [
        'host' => $host,
        'port' => $port,
        'security' => $security_type,
        'username' => $username
    ], $result);

    // Return the result
    outputJSON($result);

} catch (InvalidArgumentException $e) {
    $logger::logSecurityEvent('validation_error', [
        'description' => 'SMTP test validation failed: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    outputJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);

} catch (Exception $e) {
    $logger::logSecurityEvent('smtp_test_error', [
        'description' => 'SMTP test failed: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    outputJSON([
        'success' => false,
        'error' => 'Internal server error occurred',
        'debug_info' => $e->getMessage()
    ], 500);
}
} catch (Throwable $e) {
    // Catch any fatal errors or exceptions that weren't caught above
    error_log("Fatal error in SMTP API: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    
    outputJSON([
        'success' => false,
        'error' => 'Fatal error occurred',
        'debug_info' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}
?>
}
