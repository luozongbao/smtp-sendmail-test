<?php
/**
 * Clear Logs API Endpoint
 * Clears test logs from the database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/config/database.php';

use EmailTester\Utils\SecurityUtils;
use EmailTester\Utils\Logger;

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Initialize components
$logger = new Logger();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Start session and validate CSRF token
session_start();
if (!SecurityUtils::validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
    exit();
}

// Check rate limiting
if (!SecurityUtils::checkRateLimit('clear_logs', 2, 300)) { // 2 requests per 5 minutes
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

try {
    // Load configuration
    if (!file_exists(__DIR__ . '/../../config.php')) {
        throw new Exception('Configuration file not found');
    }
    
    require_once __DIR__ . '/../../config.php';

    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Get count before clearing for logging
    $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM test_logs");
    $total_logs = $count_stmt->fetch()['total'];

    // Clear the logs
    $stmt = $pdo->prepare("DELETE FROM test_logs");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();

    // Log the clear action
    $logger->logSecurity([
        'event_type' => 'logs_cleared',
        'description' => "User cleared $deleted_count test logs",
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Successfully cleared $deleted_count test logs",
        'deleted_count' => $deleted_count
    ]);

} catch (PDOException $e) {
    error_log('Database error in clear-logs.php: ' . $e->getMessage());
    
    $logger->logSecurity([
        'event_type' => 'clear_logs_error',
        'description' => 'Failed to clear logs: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);

} catch (Exception $e) {
    error_log('Error in clear-logs.php: ' . $e->getMessage());
    
    $logger->logSecurity([
        'event_type' => 'clear_logs_error',
        'description' => 'Failed to clear logs: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error occurred'
    ]);
}
