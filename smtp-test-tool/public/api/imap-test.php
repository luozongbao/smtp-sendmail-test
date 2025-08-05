<?php
/**
 * IMAP Test API Endpoint
 * Handles IMAP server testing requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/config/database.php';

use EmailTester\Classes\IMAPTester;
use EmailTester\Classes\EmailValidator;
use EmailTester\Utils\SecurityUtils;
use EmailTester\Utils\Logger;

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Initialize components
$validator = new EmailValidator();
$logger = new Logger();

// Start session and validate CSRF token
session_start();
if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
    exit();
}

// Check rate limiting
if (!SecurityUtils::checkRateLimit('imap_test', 10, 300)) { // 10 requests per 5 minutes
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

try {
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
    $logger->logTest([
        'test_type' => 'IMAP',
        'target_host' => $host,
        'target_port' => $port,
        'status' => $result['success'] ? 'success' : 'failed',
        'result_data' => json_encode($result),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // Return the result
    echo json_encode($result);

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
