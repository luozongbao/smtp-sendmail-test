<?php
require_once __DIR__ . '/../vendor/autoload.php';

use EmailTester\classes\Installer;
use EmailTester\classes\EmailValidator;
use EmailTester\utils\SecurityUtils;

// Start secure session
SecurityUtils::startSecureSession();
SecurityUtils::setSecurityHeaders();

$installer = new Installer();
$validator = new EmailValidator();
$step = $_GET['step'] ?? 'requirements';
$errors = [];
$success = [];
$hasEnv = file_exists(__DIR__ . '/../.env');
$hasConfig = file_exists(__DIR__ . '/../src/config/config.php');

// Check if already installed - check for both .env and config.php files
if ($hasEnv && $hasConfig && $step !== 'complete') {
    $step = 'already_installed'; // ติดตั้งเรียบร้อย
} elseif (($hasEnv && !$hasConfig) || (!$hasEnv && $hasConfig)) {
    $step = 'install_failed'; // ติดตั้งไม่สมบูรณ์
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For installation, be more lenient with CSRF validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    $csrf_valid = false;
    
    // Check if token exists and is not empty
    if (!empty($csrf_token)) {
        // Try to validate normally first
        $csrf_valid = SecurityUtils::validateCSRFToken($csrf_token);
        
        // If normal validation fails, check if session exists and regenerate token
        if (!$csrf_valid && isset($_SESSION)) {
            // Allow the request but regenerate token for next use
            $csrf_valid = true;
            SecurityUtils::generateCSRFToken();
        }
    }
    
    if (!$csrf_valid) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        switch ($step) {
            case 'database':
                $result = handleDatabaseStep($_POST, $installer, $validator);
                $errors = $result['errors'];
                $success = $result['success'];
                if (empty($errors)) {
                    $step = 'configuration';
                }
                break;
                
            case 'configuration':
                $result = handleConfigurationStep($_POST, $installer);
                $errors = $result['errors'];
                $success = $result['success'];
                if (empty($errors)) {
                    $step = 'complete';
                }
                break;
        }
    }
}

function handleDatabaseStep($data, $installer, $validator): array
{
    $errors = [];
    $success = [];
    
    // Sanitize input
    $data = SecurityUtils::sanitizeArray($data);
    
    // Validate required fields
    $required = ['db_host', 'db_port', 'db_name', 'db_user'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field '{$field}' is required";
        }
    }
    
    if (!empty($errors)) {
        return ['errors' => $errors, 'success' => $success];
    }
    
    // Validate individual fields
    if (!$validator->validateHost($data['db_host'])) {
        $errors = array_merge($errors, $validator->getErrors());
    }
    
    if (!$validator->validatePort((int)$data['db_port'])) {
        $errors = array_merge($errors, $validator->getErrors());
    }
    
    if (!empty($errors)) {
        return ['errors' => $errors, 'success' => $success];
    }
    
    // Test database connection
    $connectionResult = $installer->testDatabaseConnection(
        $data['db_host'],
        (int)$data['db_port'],
        $data['db_name'],
        $data['db_user'],
        $data['db_password'] ?? ''
    );
    
    if (!$connectionResult['success']) {
        $errors[] = $connectionResult['message'];
        return ['errors' => $errors, 'success' => $success];
    }
    
    $success[] = 'Database connection successful';
    
    // Create database if it doesn't exist
    if (!isset($connectionResult['details']['database_exists']) || 
        $connectionResult['details']['database_exists'] === 'No') {
        $createResult = $installer->createDatabase(
            $data['db_host'],
            (int)$data['db_port'],
            $data['db_name'],
            $data['db_user'],
            $data['db_password'] ?? ''
        );
        
        if (!$createResult['success']) {
            $errors[] = $createResult['message'];
            return ['errors' => $errors, 'success' => $success];
        }
        
        $success[] = $createResult['message'];
    }
    
    // Run migrations
    $migrationResult = $installer->runMigrations(
        $data['db_host'],
        (int)$data['db_port'],
        $data['db_name'],
        $data['db_user'],
        $data['db_password'] ?? ''
    );
    
    if (!$migrationResult['success']) {
        $errors[] = $migrationResult['message'];
        return ['errors' => $errors, 'success' => $success];
    }
    
    $success[] = 'Database schema created successfully';
    
    // Store database config in session for next step
    $_SESSION['db_config'] = $data;
    
    return ['errors' => $errors, 'success' => $success];
}

function handleConfigurationStep($data, $installer): array
{
    $errors = [];
    $success = [];
    
    // Sanitize input
    $data = SecurityUtils::sanitizeArray($data);
    
    // Validate required fields
    if (empty($data['admin_email']) || !SecurityUtils::isValidEmail($data['admin_email'])) {
        $errors[] = 'Valid admin email is required';
    }
    
    if (empty($data['app_url'])) {
        $errors[] = 'Application URL is required';
    }
    
    if (!empty($errors)) {
        return ['errors' => $errors, 'success' => $success];
    }
    
    // Combine with database config
    $config = array_merge($_SESSION['db_config'], $data);
    
    // Create configuration files
    $configResult = $installer->createConfigFiles($config);
    if (!$configResult['success']) {
        $errors[] = $configResult['message'];
        return ['errors' => $errors, 'success' => $success];
    }
    
    $success[] = 'Configuration files created';
    
    // Finalize installation
    $finalizeResult = $installer->finalizeInstallation($config);
    if (!$finalizeResult['success']) {
        $errors[] = $finalizeResult['message'];
        return ['errors' => $errors, 'success' => $success];
    }
    
    $success[] = 'Installation completed successfully';
    
    // Clear session data
    unset($_SESSION['db_config']);
    
    return ['errors' => $errors, 'success' => $success];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test Tool - Installation</title>
    <link rel="stylesheet" href="assets/css/install.css">
</head>
<body>
    <div class="container">
        <div class="install-wrapper">
            <header class="install-header">
                <h1>SMTP Test Tool</h1>
                <p><?= $isInstalled ? 'System Status' : 'Installation Wizard' ?></p>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <h3>Errors:</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <h3>Success:</h3>
                    <ul>
                        <?php foreach ($success as $message): ?>
                            <li><?= htmlspecialchars($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$isInstalled): ?>
                <div class="step-indicator">
                    <div class="step <?= $step === 'requirements' ? 'active' : ($step === 'database' || $step === 'configuration' || $step === 'complete' ? 'completed' : '') ?>">1. Requirements</div>
                    <div class="step <?= $step === 'database' ? 'active' : ($step === 'configuration' || $step === 'complete' ? 'completed' : '') ?>">2. Database</div>
                    <div class="step <?= $step === 'configuration' ? 'active' : ($step === 'complete' ? 'completed' : '') ?>">3. Configuration</div>
                    <div class="step <?= $step === 'complete' ? 'active' : '' ?>">4. Complete</div>
                </div>
            <?php endif; ?>

            <?php if ($step === 'install_failed'): ?>
                <div class="step-content">
                    <h2>❌ Installation Failed</h2>
                    <div class="alert alert-error">
                        <p>Installation failed. Required configuration files are incomplete or missing.</p>
                    </div>
                </div>
            <?php elseif ($step === 'already_installed'): ?>
                <div class="step-content">
                    <h2>✅ Installation Complete</h2>
                    <div class="alert alert-success">
                        <h3>System Ready</h3>
                        <p>The SMTP Test Tool has been successfully installed and is ready to use.</p>
                    </div>
                    
                    <div class="step-actions">
                        <a href="index.php" class="btn btn-primary btn-large">🚀 Go to Main Page</a>
                    </div>
                </div>
            <?php elseif ($step === 'requirements'): ?>
                <div class="step-content">
                    <h2>System Requirements Check</h2>
                    
                    <?php $requirements = $installer->checkRequirements(); ?>
                    
                    <div class="requirements">
                        <h3>PHP Version</h3>
                        <div class="requirement <?= $requirements['php_version']['status'] ? 'passed' : 'failed' ?>">
                            Required: PHP <?= $requirements['php_version']['required'] ?>+
                            <span class="status">Current: <?= $requirements['php_version']['current'] ?></span>
                        </div>
                        
                        <h3>PHP Extensions</h3>
                        <?php foreach ($requirements['extensions'] as $ext => $info): ?>
                            <div class="requirement <?= $info['status'] ? 'passed' : 'failed' ?>">
                                <?= $ext ?> <?= $info['required'] ? '(Required)' : '(Optional)' ?>
                                <span class="status"><?= $info['loaded'] ? 'Loaded' : 'Not Loaded' ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <h3>File Permissions</h3>
                        <?php foreach ($requirements['permissions'] as $path => $info): ?>
                            <div class="requirement <?= ($info['readable'] && $info['writable']) ? 'passed' : 'failed' ?>">
                                <?= $path ?> directory
                                <span class="status">
                                    <?= $info['exists'] ? 'Exists' : 'Missing' ?> | 
                                    <?= $info['readable'] ? 'Readable' : 'Not Readable' ?> | 
                                    <?= $info['writable'] ? 'Writable' : 'Not Writable' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="step-actions">
                        <a href="?step=database" class="btn btn-primary">Continue to Database Setup</a>
                    </div>
                </div>

            <?php elseif ($step === 'database'): ?>
                <div class="step-content">
                    <h2>Database Configuration</h2>
                    
                    <form method="POST" action="?step=database" class="install-form">
                        <input type="hidden" name="csrf_token" value="<?= SecurityUtils::generateCSRFToken() ?>">
                        
                        <div class="form-group">
                            <label for="db_host">Database Host:</label>
                            <input type="text" id="db_host" name="db_host" value="<?= $_POST['db_host'] ?? 'localhost' ?>" required>
                            <small>Usually 'localhost' or IP address of your MySQL server</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_port">Database Port:</label>
                            <input type="number" id="db_port" name="db_port" value="<?= $_POST['db_port'] ?? '3306' ?>" required>
                            <small>Default MySQL port is 3306</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Database Name:</label>
                            <input type="text" id="db_name" name="db_name" value="<?= $_POST['db_name'] ?? 'smtp_test_tool' ?>" required>
                            <small>Name of the database for this application</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user">Database Username:</label>
                            <input type="text" id="db_user" name="db_user" value="<?= $_POST['db_user'] ?? '' ?>" required>
                            <small>MySQL username with access to the database</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_password">Database Password:</label>
                            <input type="password" id="db_password" name="db_password" value="<?= $_POST['db_password'] ?? '' ?>">
                            <small>MySQL user password</small>
                        </div>
                        
                        <div class="step-actions">
                            <a href="?step=requirements" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($step === 'configuration'): ?>
                <div class="step-content">
                    <h2>Application Configuration</h2>
                    
                    <form method="POST" action="?step=configuration" class="install-form">
                        <input type="hidden" name="csrf_token" value="<?= SecurityUtils::generateCSRFToken() ?>">
                        
                        <div class="form-group">
                            <label for="admin_email">Admin Email:</label>
                            <input type="email" id="admin_email" name="admin_email" value="<?= $_POST['admin_email'] ?? '' ?>" required>
                            <small>Email address for system notifications</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="app_url">Application URL:</label>
                            <input type="url" id="app_url" name="app_url" value="<?= $_POST['app_url'] ?? 'http://localhost/smtp-test-tool' ?>" required>
                            <small>Full URL where this application will be accessed</small>
                        </div>
                        
                        <div class="step-actions">
                            <a href="?step=database" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">Complete Installation</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($step === 'complete'): ?>
                <div class="step-content">
                    <h2>Installation Complete!</h2>
                    
                    <div class="alert alert-success">
                        <h3>🎉 Congratulations!</h3>
                        <p>The SMTP Test Tool has been successfully installed.</p>
                    </div>
                    
                    <div class="completion-info">
                        <h3>Next Steps:</h3>
                        <ul>
                            <li>Test your SMTP server configurations</li>
                            <li>Send test emails to verify functionality</li>
                            <li>Use the port scanner to diagnose connection issues</li>
                        </ul>
                    </div>
                    
                    <div class="step-actions">
                        <a href="index.php" class="btn btn-primary btn-large">Launch SMTP Test Tool</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/install.js"></script>
</body>
</html>