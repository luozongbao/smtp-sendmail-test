<?php
/**
 * IMAP Test API Endpoint
 * Handles IMAP server testing requests
 */

// Clean output buffering to prevent any extra output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Enable error reporting for debugging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/imap_api_error.log');

// Suppress all IMAP warnings and errors
ini_set('error_reporting', E_ERROR | E_PARSE);

// Set headers first
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to output clean JSON and exit
function outputJSON($data, $statusCode = 200) {
    // Clear ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh buffer
    ob_start();
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // Flush and exit
    ob_end_flush();
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Config/config/database.php';

use EmailTester\Classes\IMAPTester;
use EmailTester\Classes\EmailValidator;
use EmailTester\Utils\SecurityUtils;
use EmailTester\Utils\Logger;

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    outputJSON(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Initialize components
$validator = new EmailValidator();
$logger = new Logger();

// Start session and validate CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    outputJSON(['success' => false, 'error' => 'CSRF token validation failed'], 403);
}

// Check rate limiting
if (!SecurityUtils::checkRateLimit('imap_test', 10, 300)) { // 10 requests per 5 minutes
    outputJSON(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
}

try {
    // Check for IMAP extension first
    if (!extension_loaded('imap')) {
        outputJSON([
            'success' => false,
            'status' => 'error',
            'message' => 'PHP IMAP extension is not installed. Please install php-imap extension.',
            'details' => 'To install on Ubuntu/Debian: sudo apt-get install php-imap && sudo systemctl restart nginx',
            'response_time' => 0
        ]);
    }

    // Validate input parameters
    $host = SecurityUtils::sanitizeInput($_POST['host'] ?? '');
    $port = intval($_POST['port'] ?? 993);
    $security_type = SecurityUtils::sanitizeInput($_POST['security'] ?? 'ssl');
    $timeout = intval($_POST['timeout'] ?? 30);
    $username = SecurityUtils::sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize password

    // Validate required fields
    if (empty($host)) {
        throw new InvalidArgumentException('Host is required');
    }

    if (empty($username)) {
        throw new InvalidArgumentException('Username is required for IMAP testing');
    }

    if (empty($password)) {
        throw new InvalidArgumentException('Password is required for IMAP testing');
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

    // Create IMAP tester instance
    $imapTester = new IMAPTester($host, $port, $security_type, $username, $password);

    // Perform IMAP test
    $result = $imapTester->testConnection();

    // Log the test result
    $logger->logTest('IMAP', [
        'host' => $host,
        'port' => $port,
        'security' => $security_type,
        'username' => $username
    ], $result);

    // Return the result
    outputJSON($result);

} catch (InvalidArgumentException $e) {
    $logger->logSecurity([
        'event_type' => 'validation_error',
        'description' => 'IMAP test validation failed: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

} catch (Exception $e) {
    $logger->logSecurity([
        'event_type' => 'imap_test_error',
        'description' => 'IMAP test failed: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error occurred',
        'debug_info' => $e->getMessage()
    ]);
}
