<?php
/**
 * SMTP Test Tool - Main Application Interface
 * 
 * @author SMTP Test Tool Team
 * @version 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config/config/database.php';

use EmailTester\Utils\SecurityUtils;
use EmailTester\Utils\Logger;

// Initialize security and logging
$logger = new Logger();

// Check for installation cleanup message
$cleanupMessage = '';
if (isset($_GET['install_cleanup_failed']) && $_GET['install_cleanup_failed'] === '1') {
    $cleanupMessage = '<div class="alert alert-warning">Installation completed successfully, but the install.php file could not be automatically renamed. Please manually delete or rename the install.php file for security.</div>';
}

// Check if installation is complete
if (!file_exists(__DIR__ . '/../src/Config/config.php')) {
    // Check if install.php exists, otherwise check for backup
    if (file_exists(__DIR__ . '/install.php')) {
        header('Location: install.php');
    } elseif (file_exists(__DIR__ . '/install.php.bak')) {
        // Installation was completed but config file is missing
        echo '<h1>Configuration Error</h1>';
        echo '<p>Installation appears to have been completed, but configuration file is missing.</p>';
        echo '<p>Please restore install.php.bak to install.php and run the installation again.</p>';
    } else {
        echo '<h1>Installation Required</h1>';
        echo '<p>Please upload the installation files and run the installation wizard.</p>';
    }
    exit();
}

// Load configuration
require_once __DIR__ . '/../src/Config/config.php';

// Start session and validate CSRF token if POST request
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF token validation failed');
    }
}

// Generate CSRF token for forms
$csrfToken = SecurityUtils::generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test Tool - Email Server Testing Suite</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="container">
                <h1><i class="fas fa-envelope-circle-check"></i> SMTP Test Tool</h1>
                <p>Comprehensive Email Server Testing Suite</p>
            </div>
        </header>

        <!-- Cleanup Message -->
        <?php if (!empty($cleanupMessage)): ?>
            <?= $cleanupMessage ?>
        <?php endif; ?>

        <!-- Navigation -->
        <nav class="app-nav">
            <div class="container">
                <div class="nav-buttons">
                    <button class="nav-btn active" data-tab="smtp-test">
                        <i class="fas fa-paper-plane"></i> SMTP Test
                    </button>
                    <button class="nav-btn" data-tab="imap-test">
                        <i class="fas fa-inbox"></i> IMAP Test
                    </button>
                    <button class="nav-btn" data-tab="port-scan">
                        <i class="fas fa-network-wired"></i> Port Scan
                    </button>
                    <button class="nav-btn" data-tab="email-send">
                        <i class="fas fa-envelope"></i> Send Email
                    </button>
                    <button class="nav-btn" data-tab="test-logs">
                        <i class="fas fa-history"></i> Test Logs
                    </button>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="app-main">
            <div class="container">
                <!-- SMTP Test Tab -->
                <div id="smtp-test" class="tab-content active">
                    <div class="panel">
                        <h2><i class="fas fa-server"></i> SMTP Server Test</h2>
                        <p>Test SMTP server connectivity, authentication, and functionality.</p>
                        
                        <form id="smtp-test-form" class="test-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="smtp-host">SMTP Host</label>
                                    <input type="text" id="smtp-host" name="host" placeholder="smtp.gmail.com" required>
                                </div>
                                <div class="form-group">
                                    <label for="smtp-port">Port</label>
                                    <select id="smtp-port" name="port">
                                        <option value="25">25 (SMTP)</option>
                                        <option value="587" selected>587 (STARTTLS)</option>
                                        <option value="465">465 (SSL/TLS)</option>
                                        <option value="2525">2525 (Alternative)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="smtp-security">Security Type</label>
                                    <select id="smtp-security" name="security">
                                        <option value="">None</option>
                                        <option value="tls" selected>STARTTLS</option>
                                        <option value="ssl">SSL/TLS</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="smtp-timeout">Timeout (seconds)</label>
                                    <input type="number" id="smtp-timeout" name="timeout" value="30" min="5" max="120">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="smtp-username">Username (optional)</label>
                                    <input type="text" id="smtp-username" name="username" placeholder="user@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="smtp-password">Password (optional)</label>
                                    <input type="password" id="smtp-password" name="password" placeholder="Password">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Test SMTP Connection
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm('smtp-test-form')">
                                    <i class="fas fa-eraser"></i> Clear Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- IMAP Test Tab -->
                <div id="imap-test" class="tab-content">
                    <div class="panel">
                        <h2><i class="fas fa-download"></i> IMAP Server Test</h2>
                        <p>Test IMAP server connectivity and retrieve mailbox information.</p>
                        
                        <form id="imap-test-form" class="test-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="imap-host">IMAP Host</label>
                                    <input type="text" id="imap-host" name="host" placeholder="imap.gmail.com" required>
                                </div>
                                <div class="form-group">
                                    <label for="imap-port">Port</label>
                                    <select id="imap-port" name="port">
                                        <option value="143">143 (IMAP)</option>
                                        <option value="993" selected>993 (IMAPS)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="imap-security">Security Type</label>
                                    <select id="imap-security" name="security">
                                        <option value="">None</option>
                                        <option value="tls">STARTTLS</option>
                                        <option value="ssl" selected>SSL/TLS</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="imap-timeout">Timeout (seconds)</label>
                                    <input type="number" id="imap-timeout" name="timeout" value="30" min="5" max="120">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="imap-username">Username</label>
                                    <input type="text" id="imap-username" name="username" placeholder="user@example.com" required>
                                </div>
                                <div class="form-group">
                                    <label for="imap-password">Password</label>
                                    <input type="password" id="imap-password" name="password" placeholder="Password" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Test IMAP Connection
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm('imap-test-form')">
                                    <i class="fas fa-eraser"></i> Clear Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Port Scan Tab -->
                <div id="port-scan" class="tab-content">
                    <div class="panel">
                        <h2><i class="fas fa-search"></i> Port Scanner</h2>
                        <p>Scan email server ports to check availability and service detection.</p>
                        
                        <form id="port-scan-form" class="test-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="scan-host">Host</label>
                                    <input type="text" id="scan-host" name="host" placeholder="mail.example.com" required>
                                </div>
                                <div class="form-group">
                                    <label for="scan-timeout">Timeout (seconds)</label>
                                    <input type="number" id="scan-timeout" name="timeout" value="5" min="1" max="30">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Port Selection</label>
                                <div class="port-options">
                                    <label class="checkbox-label">
                                        <input type="radio" name="port_type" value="common" checked>
                                        Common Email Ports (25, 587, 465, 143, 993, 995, 110)
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="radio" name="port_type" value="custom">
                                        Custom Port Range
                                    </label>
                                </div>
                            </div>

                            <div id="custom-ports" class="form-row" style="display: none;">
                                <div class="form-group">
                                    <label for="port-start">Start Port</label>
                                    <input type="number" id="port-start" name="start_port" value="20" min="1" max="65535">
                                </div>
                                <div class="form-group">
                                    <label for="port-end">End Port</label>
                                    <input type="number" id="port-end" name="end_port" value="1000" min="1" max="65535">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Start Port Scan
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm('port-scan-form')">
                                    <i class="fas fa-eraser"></i> Clear Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Send Email Tab -->
                <div id="email-send" class="tab-content">
                    <div class="panel">
                        <h2><i class="fas fa-paper-plane"></i> Send Test Email</h2>
                        <p>Send test emails to verify SMTP server functionality.</p>
                        
                        <form id="email-send-form" class="test-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-section">
                                <h3>SMTP Server Configuration</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email-smtp-host">SMTP Host</label>
                                        <input type="text" id="email-smtp-host" name="smtp_host" placeholder="smtp.gmail.com" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email-smtp-port">Port</label>
                                        <select id="email-smtp-port" name="smtp_port">
                                            <option value="587" selected>587 (STARTTLS)</option>
                                            <option value="465">465 (SSL/TLS)</option>
                                            <option value="25">25 (SMTP)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email-smtp-username">Username</label>
                                        <input type="text" id="email-smtp-username" name="smtp_username" placeholder="user@example.com" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email-smtp-password">Password</label>
                                        <input type="password" id="email-smtp-password" name="smtp_password" placeholder="Password" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Email Content</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email-from">From Email</label>
                                        <input type="email" id="email-from" name="from_email" placeholder="sender@example.com" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email-to">To Email</label>
                                        <input type="email" id="email-to" name="to_email" placeholder="recipient@example.com" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email-subject">Subject</label>
                                    <input type="text" id="email-subject" name="subject" placeholder="Test Email from SMTP Tool" required>
                                </div>

                                <div class="form-group">
                                    <label for="email-body">Message Body</label>
                                    <textarea id="email-body" name="body" rows="6" placeholder="This is a test email sent from the SMTP Test Tool..." required></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-envelope"></i> Send Test Email
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm('email-send-form')">
                                    <i class="fas fa-eraser"></i> Clear Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Test Logs Tab -->
                <div id="test-logs" class="tab-content">
                    <div class="panel">
                        <h2><i class="fas fa-history"></i> Test History & Logs</h2>
                        <p>View previous test results and system logs.</p>
                        
                        <div class="logs-controls">
                            <button class="btn btn-secondary" onclick="loadTestLogs()">
                                <i class="fas fa-refresh"></i> Refresh Logs
                            </button>
                            <button class="btn btn-warning" onclick="clearLogs()">
                                <i class="fas fa-trash"></i> Clear All Logs
                            </button>
                        </div>

                        <div id="logs-container" class="logs-container">
                            <div class="loading-message">
                                <i class="fas fa-spinner fa-spin"></i> Loading test logs...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Results Modal -->
        <div id="results-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">Test Results</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="modal-results"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="loading-overlay">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Processing test...</p>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
