<?php

namespace EmailTester\Classes;

use EmailTester\Config\Database;
use PDO;
use PDOException;

class Installer
{
    private array $config = [];
    private array $errors = [];

    public function testDatabaseConnection(string $host, int $port, string $database, string $username, string $password): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        try {
            // Test connection without specifying database first
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10
            ]);
            
            $result['details']['server_connection'] = 'Success';
            
            // Check if database exists
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$database]);
            $dbExists = $stmt->fetch();
            
            if ($dbExists) {
                $result['details']['database_exists'] = 'Yes';
                
                // Test connection to specific database
                $dsnWithDb = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdoWithDb = new PDO($dsnWithDb, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $result['details']['database_connection'] = 'Success';
            } else {
                $result['details']['database_exists'] = 'No';
                $result['details']['database_connection'] = 'Database does not exist';
            }
            
            // Check user privileges
            $privileges = $this->checkUserPrivileges($pdo, $username, $database);
            $result['details']['privileges'] = $privileges;
            
            $result['success'] = true;
            $result['message'] = 'Database connection test successful';
            
        } catch (PDOException $e) {
            $result['message'] = 'Database connection failed: ' . $e->getMessage();
            $this->errors[] = $e->getMessage();
        }

        return $result;
    }

    public function createDatabase(string $host, int $port, string $database, string $username, string $password): array
    {
        $result = [
            'success' => false,
            'message' => ''
        ];

        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            $stmt = $pdo->prepare("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            if ($stmt->execute()) {
                $result['success'] = true;
                $result['message'] = "Database '{$database}' created successfully";
            } else {
                $result['message'] = 'Failed to create database';
            }
            
        } catch (PDOException $e) {
            $result['message'] = 'Database creation failed: ' . $e->getMessage();
            $this->errors[] = $e->getMessage();
        }

        return $result;
    }

    public function runMigrations(string $host, int $port, string $database, string $username, string $password): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'tables_created' => []
        ];

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Read and execute schema file
            $schemaFile = __DIR__ . '/../../database/schema.sql';
            if (!file_exists($schemaFile)) {
                $result['message'] = 'Schema file not found';
                return $result;
            }

            $sql = file_get_contents($schemaFile);
            
            // Remove comments and split by semicolon
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE)/i', $statement)) {
                    $pdo->exec($statement);
                    
                    // Track table creation
                    if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                        $result['tables_created'][] = $matches[1];
                    }
                }
            }

            $result['success'] = true;
            $result['message'] = 'Database schema created successfully';
            
        } catch (PDOException $e) {
            $result['message'] = 'Migration failed: ' . $e->getMessage();
            $this->errors[] = $e->getMessage();
        }

        return $result;
    }

    public function createConfigFiles(array $config): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'files_created' => []
        ];

        try {
            // Create .env file
            $envContent = $this->generateEnvContent($config);
            $envPath = __DIR__ . '/../../.env';
            
            if (file_put_contents($envPath, $envContent)) {
                $result['files_created'][] = '.env';
            } else {
                throw new \Exception('Failed to create .env file');
            }

            // Create config.php file
            $configContent = $this->generateConfigContent($config);
            $configPath = __DIR__ . '/../Config/config.php';
            
            if (file_put_contents($configPath, $configContent)) {
                $result['files_created'][] = 'config.php';
            } else {
                throw new \Exception('Failed to create config.php file');
            }

            $result['success'] = true;
            $result['message'] = 'Configuration files created successfully';
            
        } catch (\Exception $e) {
            $result['message'] = 'Configuration file creation failed: ' . $e->getMessage();
            $this->errors[] = $e->getMessage();
        }

        return $result;
    }

    public function generateSecurityKeys(): array
    {
        $result = [
            'success' => true,
            'message' => 'Security keys generated',
            'keys' => []
        ];

        try {
            $result['keys']['app_key'] = $this->generateRandomKey(32);
            $result['keys']['csrf_secret'] = $this->generateRandomKey(32);
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['message'] = 'Key generation failed: ' . $e->getMessage();
        }

        return $result;
    }

    public function finalizeInstallation(array $config): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'steps_completed' => []
        ];

        try {
            // Mark installation as completed in database
            $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'installation_completed'");
            $stmt->execute(['true']);
            $result['steps_completed'][] = 'Installation status updated';

            // Set application URL
            $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute(['app_url', $config['app_url']]);
            $result['steps_completed'][] = 'Application URL configured';

            // Set admin email
            $stmt->execute(['admin_email', $config['admin_email']]);
            $result['steps_completed'][] = 'Admin email configured';

            // Create log file with proper permissions
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/application.log';
            if (!file_exists($logFile)) {
                file_put_contents($logFile, "Installation completed at " . date('Y-m-d H:i:s') . "\n");
                chmod($logFile, 0644);
                $result['steps_completed'][] = 'Log file initialized';
            }

            $result['success'] = true;
            $result['message'] = 'Installation finalized successfully';
            
        } catch (\Exception $e) {
            $result['message'] = 'Installation finalization failed: ' . $e->getMessage();
            $this->errors[] = $e->getMessage();
        }

        return $result;
    }

    public function checkRequirements(): array
    {
        $requirements = [
            'php_version' => [
                'required' => '8.0.0',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.0.0', '>=')
            ],
            'extensions' => []
        ];

        $requiredExtensions = ['mysqli', 'pdo', 'pdo_mysql', 'curl', 'openssl', 'mbstring', 'json'];
        $optionalExtensions = ['imap'];

        foreach ($requiredExtensions as $ext) {
            $requirements['extensions'][$ext] = [
                'required' => true,
                'loaded' => extension_loaded($ext),
                'status' => extension_loaded($ext)
            ];
        }

        foreach ($optionalExtensions as $ext) {
            $requirements['extensions'][$ext] = [
                'required' => false,
                'loaded' => extension_loaded($ext),
                'status' => true // Optional, so always pass
            ];
        }

        // Check file permissions
        $requirements['permissions'] = $this->checkPermissions();

        return $requirements;
    }

    private function checkUserPrivileges(PDO $pdo, string $username, string $database): array
    {
        $privileges = [];
        
        try {
            $stmt = $pdo->prepare("SHOW GRANTS FOR ?@'%'");
            $stmt->execute([$username]);
            $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $hasCreate = false;
            $hasInsert = false;
            $hasUpdate = false;
            $hasDelete = false;
            $hasSelect = false;
            
            foreach ($grants as $grant) {
                if (stripos($grant, 'ALL PRIVILEGES') !== false) {
                    $hasCreate = $hasInsert = $hasUpdate = $hasDelete = $hasSelect = true;
                    break;
                }
                
                if (stripos($grant, 'CREATE') !== false) $hasCreate = true;
                if (stripos($grant, 'INSERT') !== false) $hasInsert = true;
                if (stripos($grant, 'UPDATE') !== false) $hasUpdate = true;
                if (stripos($grant, 'DELETE') !== false) $hasDelete = true;
                if (stripos($grant, 'SELECT') !== false) $hasSelect = true;
            }
            
            $privileges = [
                'CREATE' => $hasCreate,
                'INSERT' => $hasInsert,
                'UPDATE' => $hasUpdate,
                'DELETE' => $hasDelete,
                'SELECT' => $hasSelect
            ];
            
        } catch (PDOException $e) {
            $privileges['error'] = 'Could not check privileges: ' . $e->getMessage();
        }
        
        return $privileges;
    }

    private function checkPermissions(): array
    {
        $paths = [
            'logs' => __DIR__ . '/../../logs',
            'config' => __DIR__ . '/../Config',
            'root' => __DIR__ . '/../..'
        ];

        $permissions = [];
        
        foreach ($paths as $name => $path) {
            $permissions[$name] = [
                'path' => $path,
                'exists' => file_exists($path),
                'readable' => is_readable($path),
                'writable' => is_writable($path)
            ];
        }

        return $permissions;
    }

    private function generateEnvContent(array $config): string
    {
        $keys = $this->generateSecurityKeys();
        
        return "# Database Configuration
DB_HOST={$config['db_host']}
DB_PORT={$config['db_port']}
DB_DATABASE={$config['db_name']}
DB_USERNAME={$config['db_user']}
DB_PASSWORD={$config['db_password']}

# Application Configuration
APP_NAME=\"SMTP Test Tool\"
APP_URL={$config['app_url']}
APP_KEY={$keys['keys']['app_key']}
ADMIN_EMAIL={$config['admin_email']}

# Security
CSRF_SECRET={$keys['keys']['csrf_secret']}
SESSION_LIFETIME=120

# Logging
LOG_LEVEL=info
LOG_FILE=logs/application.log
";
    }

    private function generateConfigContent(array $config): string
    {
        return "<?php

// Configuration file generated during installation

require_once __DIR__ . '/../../vendor/autoload.php';

\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
\$dotenv->load();

return [
    'database' => [
        'host' => \$_ENV['DB_HOST'],
        'port' => (int)\$_ENV['DB_PORT'],
        'database' => \$_ENV['DB_DATABASE'],
        'username' => \$_ENV['DB_USERNAME'],
        'password' => \$_ENV['DB_PASSWORD'],
    ],
    
    'app' => [
        'name' => \$_ENV['APP_NAME'],
        'url' => \$_ENV['APP_URL'],
        'key' => \$_ENV['APP_KEY'],
        'admin_email' => \$_ENV['ADMIN_EMAIL'],
    ],
    
    'security' => [
        'csrf_secret' => \$_ENV['CSRF_SECRET'],
        'session_lifetime' => (int)\$_ENV['SESSION_LIFETIME'],
    ],
    
    'logging' => [
        'level' => \$_ENV['LOG_LEVEL'],
        'file' => \$_ENV['LOG_FILE'],
    ],
];
";
    }

    private function generateRandomKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }
}
