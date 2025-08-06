<?php
/**
 * Port Scan API Endpoint
 * Handles port scanning requests
 */

// Clean output buffering to prevent any extra output
ob_start();

// Enable error reporting for debugging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/port_scan_error.log');

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
require_once __DIR__ . '/../../src/Config/config/database.php';

use EmailTester\Classes\PortScanner;
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
session_start();
if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    outputJSON(['success' => false, 'error' => 'CSRF token validation failed']);
    exit();
}

// Check rate limiting - more restrictive for port scanning
if (!SecurityUtils::checkRateLimit('port_scan', 5, 300)) { // 5 requests per 5 minutes
    http_response_code(429);
    outputJSON(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

try {
    // Validate input parameters
    $host = SecurityUtils::sanitizeInput($_POST['host'] ?? '');
    $port_type = SecurityUtils::sanitizeInput($_POST['port_type'] ?? 'common');
    $timeout = intval($_POST['timeout'] ?? 5);
    $start_port = intval($_POST['start_port'] ?? 20);
    $end_port = intval($_POST['end_port'] ?? 1000);

    // Validate required fields
    if (empty($host)) {
        throw new InvalidArgumentException('Host is required');
    }

    // Validate host format
    if (!$validator->validateHost($host)) {
        throw new InvalidArgumentException('Invalid host format');
    }

    // Validate timeout
    if ($timeout < 1 || $timeout > 30) {
        $timeout = 5;
    }

    // Validate port type and ranges
    if (!in_array($port_type, ['common', 'custom'])) {
        throw new InvalidArgumentException('Invalid port type');
    }

        // Create port scanner instance
    $result = null;

    if ($port_type === 'common') {
        // Scan common email ports
        $portScanner = new PortScanner($host, [], $timeout);
        $commonResult = $portScanner->scanCommonEmailPorts();
        
        // Convert common port result to standard format
        $result = [
            'success' => $commonResult['success'],
            'message' => $commonResult['message'],
            'host' => $commonResult['host'],
            'total_ports' => count($commonResult['services']),
            'open_ports' => [],
            'closed_ports' => [],
            'scan_time' => 0,
            'details' => []
        ];
        
        $startTime = microtime(true);
        foreach ($commonResult['services'] as $port => $portData) {
            $result['details'][$port] = $portData;
            if ($portData['open']) {
                $result['open_ports'][] = $port;
            } else {
                $result['closed_ports'][] = $port;
            }
        }
        $result['scan_time'] = (int)((microtime(true) - $startTime) * 1000);
        
    } else {
        // Validate custom port range
        if ($start_port < 1 || $start_port > 65535) {
            throw new InvalidArgumentException('Invalid start port');
        }
        if ($end_port < 1 || $end_port > 65535) {
            throw new InvalidArgumentException('Invalid end port');
        }
        if ($start_port > $end_port) {
            throw new InvalidArgumentException('Start port must be less than or equal to end port');
        }
        
        // Limit port range to prevent abuse
        $port_range = $end_port - $start_port + 1;
        if ($port_range > 1000) {
            throw new InvalidArgumentException('Port range too large. Maximum 1000 ports allowed.');
        }

        // Create port range array
        $ports = range($start_port, $end_port);
        $portScanner = new PortScanner($host, $ports, $timeout);
        
        $result = $portScanner->scanPorts();
    }

    // Log the test result
    $logger->logTest('Port Scan', [
        'host' => $host,
        'port' => $port_type === 'common' ? 0 : $start_port . '-' . $end_port,
        'security' => 'none'
    ], $result);

    // Return the result
    outputJSON($result);

} catch (InvalidArgumentException $e) {
    $logger->logSecurity([
        'event_type' => 'validation_error',
        'description' => 'Port scan validation failed: ' . $e->getMessage(),
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
        'event_type' => 'port_scan_error',
        'description' => 'Port scan failed: ' . $e->getMessage(),
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
