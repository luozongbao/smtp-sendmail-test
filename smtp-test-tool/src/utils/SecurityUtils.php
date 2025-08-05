<?php

namespace EmailTester\Utils;

class SecurityUtils
{
    private static array $rateLimitData = [];
    private static string $sessionKey = 'smtp_test_tool_session';

    public static function sanitizeInput(string $input): string
    {
        // Remove null bytes and control characters
        $input = str_replace("\0", '', $input);
        
        // Strip HTML tags and encode special characters
        $input = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        
        return $input;
    }

    public static function sanitizeArray(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $cleanKey = self::sanitizeInput((string)$key);
            
            if (is_array($value)) {
                $sanitized[$cleanKey] = self::sanitizeArray($value);
            } else {
                $sanitized[$cleanKey] = self::sanitizeInput((string)$value);
            }
        }
        
        return $sanitized;
    }

    public static function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    public static function checkRateLimit(string $identifier, int $maxRequests = 60, int $timeWindow = 60): bool
    {
        $currentTime = time();
        $windowStart = $currentTime - $timeWindow;
        
        // Clean old entries
        if (isset(self::$rateLimitData[$identifier])) {
            self::$rateLimitData[$identifier] = array_filter(
                self::$rateLimitData[$identifier],
                function($timestamp) use ($windowStart) {
                    return $timestamp > $windowStart;
                }
            );
        } else {
            self::$rateLimitData[$identifier] = [];
        }
        
        // Check if limit exceeded
        if (count(self::$rateLimitData[$identifier]) >= $maxRequests) {
            Logger::logSecurityEvent('Rate limit exceeded', [
                'identifier' => $identifier,
                'requests' => count(self::$rateLimitData[$identifier]),
                'max_requests' => $maxRequests,
                'time_window' => $timeWindow
            ]);
            return false;
        }
        
        // Add current request
        self::$rateLimitData[$identifier][] = $currentTime;
        
        return true;
    }

    public static function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidHost(string $host): bool
    {
        // Check if it's a valid IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        // Check if it's a valid hostname
        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    public static function isValidPort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }

    public static function preventSMTPInjection(string $input): string
    {
        // Remove characters that could be used for SMTP injection
        $dangerous = ["\r", "\n", "%0a", "%0d", "Content-Type:", "bcc:", "cc:", "to:"];
        
        return str_ireplace($dangerous, '', $input);
    }

    public static function encryptSensitiveData(string $data, string $key): string
    {
        $cipher = 'AES-256-CBC';
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    public static function decryptSensitiveData(string $encryptedData, string $key): ?string
    {
        $cipher = 'AES-256-CBC';
        $data = base64_decode($encryptedData);
        
        if ($data === false) {
            return null;
        }
        
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
        
        return $decrypted !== false ? $decrypted : null;
    }

    public static function generateSecurePassword(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Strict');
            
            session_name(self::$sessionKey);
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    public static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            
            session_destroy();
        }
    }

    public static function validateUserAgent(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Basic validation to prevent empty or suspicious user agents
        if (empty($userAgent) || strlen($userAgent) < 10 || strlen($userAgent) > 500) {
            return false;
        }
        
        // Check for common bot patterns that might be malicious
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/scanner/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                Logger::logSecurityEvent('Suspicious user agent detected', [
                    'user_agent' => $userAgent
                ]);
                return false;
            }
        }
        
        return true;
    }

    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $securityContext = array_merge([
            'ip_address' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        Logger::logSecurityEvent($event, $securityContext);
    }

    public static function checkSecurityHeaders(): array
    {
        $headers = [];
        
        // Security headers to check
        $securityHeaders = [
            'X-Frame-Options',
            'X-Content-Type-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security',
            'Content-Security-Policy'
        ];
        
        foreach ($securityHeaders as $header) {
            $headers[$header] = isset($_SERVER['HTTP_' . str_replace('-', '_', strtoupper($header))]);
        }
        
        return $headers;
    }

    public static function setSecurityHeaders(): void
    {
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}
