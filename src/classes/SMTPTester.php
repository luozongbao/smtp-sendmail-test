<?php

namespace EmailTester\classes;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use EmailTester\config\Constants;

class SMTPTester
{
    private string $host;
    private int $port;
    private string $security;
    private string $username;
    private string $password;
    private array $lastError = [];
    private int $responseTime = 0;

    public function __construct(string $host, int $port, string $security, string $username = '', string $password = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->security = $security;
        $this->username = $username;
        $this->password = $password;
    }

    public function testConnection(): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'message' => '',
            'details' => [],
            'response_time' => 0
        ];

        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->Timeout = Constants::TEST_TIMEOUT;
            
            // Security settings
            switch (strtolower($this->security)) {
                case 'ssl':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    break;
                case 'tls':
                case 'starttls':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                default:
                    $mail->SMTPSecure = false;
            }

            // Authentication
            if (!empty($this->username)) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            }

            // Enable SMTP debug for detailed info
            $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
            $mail->Debugoutput = function($str, $level) use (&$result) {
                $result['details'][] = trim($str);
            };

            // Test the connection by trying to connect
            if ($mail->smtpConnect()) {
                $result['success'] = true;
                $result['message'] = 'SMTP connection successful';
                
                // Get server capabilities
                $capabilities = $this->getServerCapabilities($mail);
                $result['capabilities'] = $capabilities;
                
                $mail->smtpClose();
            } else {
                $result['message'] = 'Failed to connect to SMTP server';
            }

        } catch (Exception $e) {
            $result['message'] = 'SMTP Error: ' . $e->getMessage();
            $this->lastError = [
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }

        $this->responseTime = (int)((microtime(true) - $startTime) * 1000);
        $result['response_time'] = $this->responseTime;

        return $result;
    }

    public function validateAuthentication(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'auth_methods' => []
        ];

        if (empty($this->username) || empty($this->password)) {
            $result['message'] = 'Username and password required for authentication test';
            return $result;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;

            // Set security
            switch (strtolower($this->security)) {
                case 'ssl':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    break;
                case 'tls':
                case 'starttls':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
            }

            if ($mail->smtpConnect()) {
                $result['success'] = true;
                $result['message'] = 'Authentication successful';
                $mail->smtpClose();
            } else {
                $result['message'] = 'Authentication failed';
            }

        } catch (Exception $e) {
            $result['message'] = 'Authentication error: ' . $e->getMessage();
        }

        return $result;
    }

    public function sendTestEmail(string $to, string $subject, string $body, bool $isHtml = true): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'message' => '',
            'message_id' => '',
            'response_time' => 0
        ];

        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            
            // Security
            switch (strtolower($this->security)) {
                case 'ssl':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    break;
                case 'tls':
                case 'starttls':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
            }

            // Authentication
            if (!empty($this->username)) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            }

            // Recipients
            $mail->setFrom($this->username, 'SMTP Test Tool');
            $mail->addAddress($to);

            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            
            if ($isHtml) {
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }

            if ($mail->send()) {
                $result['success'] = true;
                $result['message'] = 'Test email sent successfully';
                $result['message_id'] = $mail->getLastMessageID();
            } else {
                $result['message'] = 'Failed to send test email';
            }

        } catch (Exception $e) {
            $result['message'] = 'Send error: ' . $e->getMessage();
        }

        $result['response_time'] = (int)((microtime(true) - $startTime) * 1000);
        return $result;
    }

    private function getServerCapabilities(PHPMailer $mail): array
    {
        $capabilities = [];
        
        try {
            // Try to get EHLO response which contains capabilities
            $ehloResponse = $mail->getSMTPInstance()->hello();
            if ($ehloResponse) {
                // Parse capabilities from EHLO response
                $capabilities['ehlo'] = true;
                $capabilities['auth_methods'] = $this->parseAuthMethods($mail);
                $capabilities['max_size'] = $this->getMaxMessageSize($mail);
            }
        } catch (Exception $e) {
            $capabilities['error'] = $e->getMessage();
        }

        return $capabilities;
    }

    private function parseAuthMethods(PHPMailer $mail): array
    {
        // This would parse AUTH methods from EHLO response
        return ['LOGIN', 'PLAIN']; // Default methods
    }

    private function getMaxMessageSize(PHPMailer $mail): ?int
    {
        // This would parse SIZE extension from EHLO response
        return null;
    }

    public function getLastError(): array
    {
        return $this->lastError;
    }

    public function getResponseTime(): int
    {
        return $this->responseTime;
    }
}
