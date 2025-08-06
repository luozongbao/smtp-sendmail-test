<?php
// Start output buffering
ob_start();

/**
 * Send Email API Endpoint
 * Handles email sending requests
 */


// Function to output clean JSON and exit
function outputJSON($data, $statusCode = 200) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../vendor/autoload.php';

use EmailTester\Classes\SMTPTester;
use EmailTester\Classes\EmailValidator;
use EmailTester\Utils\SecurityUtils;
use EmailTester\Utils\Logger;
use EmailTester\Config\Database;

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

// Check rate limiting - more restrictive for email sending
if (!SecurityUtils::checkRateLimit('send_email', 3, 300)) { // 3 emails per 5 minutes
    http_response_code(429);
    outputJSON(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

try {
    // Validate SMTP configuration
    $smtp_host = SecurityUtils::sanitizeInput($_POST['smtp_host'] ?? '');
    $smtp_port = intval($_POST['smtp_port'] ?? 587);
    $smtp_username = SecurityUtils::sanitizeInput($_POST['smtp_username'] ?? '');
    $smtp_password = $_POST['smtp_password'] ?? ''; // Don't sanitize password

    // Validate email content
    $from_email = SecurityUtils::sanitizeInput($_POST['from_email'] ?? '');
    $to_email = SecurityUtils::sanitizeInput($_POST['to_email'] ?? '');
    $subject = SecurityUtils::sanitizeInput($_POST['subject'] ?? '');
    $body = SecurityUtils::sanitizeInput($_POST['body'] ?? '');

    // Validate required SMTP fields
    if (empty($smtp_host)) {
        throw new InvalidArgumentException('SMTP host is required');
    }

    if (empty($smtp_username)) {
        throw new InvalidArgumentException('SMTP username is required');
    }

    if (empty($smtp_password)) {
        throw new InvalidArgumentException('SMTP password is required');
    }

    // Validate required email fields
    if (empty($from_email)) {
        throw new InvalidArgumentException('From email is required');
    }

    if (empty($to_email)) {
        throw new InvalidArgumentException('To email is required');
    }

    if (empty($subject)) {
        throw new InvalidArgumentException('Subject is required');
    }

    if (empty($body)) {
        throw new InvalidArgumentException('Message body is required');
    }

    // Validate host format
    if (!$validator->validateHost($smtp_host)) {
        throw new InvalidArgumentException('Invalid SMTP host format');
    }

    // Validate port
    if (!$validator->validatePort($smtp_port)) {
        throw new InvalidArgumentException('Invalid SMTP port number');
    }

    // Validate email addresses
    if (!$validator->validateEmail($from_email)) {
        throw new InvalidArgumentException('Invalid from email address');
    }

    if (!$validator->validateEmail($to_email)) {
        throw new InvalidArgumentException('Invalid to email address');
    }

    // Validate subject and body length
    if (strlen($subject) > 200) {
        throw new InvalidArgumentException('Subject too long (maximum 200 characters)');
    }

    if (strlen($body) > 10000) {
        throw new InvalidArgumentException('Message body too long (maximum 10,000 characters)');
    }

    // Create SMTP tester instance
    $smtpTester = new SMTPTester($smtp_host, $smtp_port, $smtp_port == 465 ? 'ssl' : 'tls', $smtp_username, $smtp_password);

    // Send the email using sendTestEmail method
    $result = $smtpTester->sendTestEmail($to_email, $subject, $body, true);
    
    // Add email_sent flag for frontend compatibility
    if ($result['success']) {
        $result['email_sent'] = true;
    }

    // Log the test result
    $logger->logTest('Email Send', [
        'host' => $smtp_host,
        'port' => $smtp_port,
        'security' => $smtp_security,
        'from' => $from_email,
        'to' => $to_email,
        'subject' => $subject
    ], $result);

    // Return the result
    outputJSON($result);

} catch (InvalidArgumentException $e) {
    $logger::logSecurityEvent('validation_error', [
        'description' => 'Email send validation failed: ' . $e->getMessage(),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

} catch (Exception $e) {
    $logger::logSecurityEvent('email_send_error', [
        'description' => 'Email send failed: ' . $e->getMessage(),
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
