<?php

namespace EmailTester\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static ?MonologLogger $instance = null;
    private static string $logPath = '';

    public static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            self::initialize();
        }
        return self::$instance;
    }

    public static function initialize(string $logPath = '', string $level = 'info'): void
    {
        if (empty($logPath)) {
            $logPath = __DIR__ . '/../../logs/application.log';
        }
        
        self::$logPath = $logPath;
        self::$instance = new MonologLogger('smtp-test-tool');

        // Create log directory if it doesn't exist
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Add rotating file handler
        $fileHandler = new RotatingFileHandler($logPath, 30, self::getLogLevel($level));
        $fileHandler->setFormatter(new LineFormatter(
            "[%datetime%] %level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        ));
        self::$instance->pushHandler($fileHandler);

        // Add console handler for CLI usage
        if (php_sapi_name() === 'cli') {
            $consoleHandler = new StreamHandler('php://stdout', self::getLogLevel($level));
            $consoleHandler->setFormatter(new LineFormatter(
                "%level_name%: %message%\n"
            ));
            self::$instance->pushHandler($consoleHandler);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    public static function logTest(string $testType, array $config, array $result): void
    {
        $context = [
            'test_type' => $testType,
            'host' => $config['host'] ?? 'unknown',
            'port' => $config['port'] ?? 0,
            'security' => $config['security'] ?? 'none',
            'success' => $result['success'] ?? false,
            'response_time' => $result['response_time'] ?? 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        if ($result['success'] ?? false) {
            self::info("Test successful: {$testType}", $context);
        } else {
            self::warning("Test failed: {$testType} - " . ($result['message'] ?? 'Unknown error'), $context);
        }
    }

    public static function logEmailSent(array $emailData, array $result): void
    {
        $context = [
            'to' => $emailData['to'] ?? 'unknown',
            'subject' => $emailData['subject'] ?? 'No subject',
            'success' => $result['success'] ?? false,
            'message_id' => $result['message_id'] ?? null,
            'response_time' => $result['response_time'] ?? 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        if ($result['success'] ?? false) {
            self::info('Test email sent successfully', $context);
        } else {
            self::error('Test email failed: ' . ($result['message'] ?? 'Unknown error'), $context);
        }
    }

    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $defaultContext = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        self::warning("Security event: {$event}", array_merge($defaultContext, $context));
    }

    public static function logInstallation(string $step, bool $success, string $message = '', array $context = []): void
    {
        $logContext = array_merge([
            'installation_step' => $step,
            'success' => $success,
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);

        if ($success) {
            self::info("Installation step completed: {$step} - {$message}", $logContext);
        } else {
            self::error("Installation step failed: {$step} - {$message}", $logContext);
        }
    }

    private static function getLogLevel(string $level): int
    {
        $levels = [
            'debug' => MonologLogger::DEBUG,
            'info' => MonologLogger::INFO,
            'notice' => MonologLogger::NOTICE,
            'warning' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
            'critical' => MonologLogger::CRITICAL,
            'alert' => MonologLogger::ALERT,
            'emergency' => MonologLogger::EMERGENCY,
        ];

        return $levels[strtolower($level)] ?? MonologLogger::INFO;
    }

    public static function getLogPath(): string
    {
        return self::$logPath;
    }

    public static function getRecentLogs(int $lines = 100): array
    {
        $logFile = self::getLogPath();
        
        if (!file_exists($logFile)) {
            return [];
        }

        $command = "tail -n {$lines} " . escapeshellarg($logFile);
        $output = shell_exec($command);
        
        if ($output === null) {
            return [];
        }

        return array_filter(explode("\n", $output));
    }

    public static function clearLogs(): bool
    {
        $logFile = self::getLogPath();
        
        if (file_exists($logFile)) {
            return file_put_contents($logFile, '') !== false;
        }
        
        return true;
    }
}
