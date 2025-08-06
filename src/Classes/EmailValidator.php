<?php

namespace EmailTester\Classes;

class EmailValidator
{
    private array $errors = [];

    public function validateEmail(string $email): bool
    {
        $this->errors = [];

        // Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Invalid email format';
            return false;
        }

        // Check for common issues
        if (strlen($email) > 254) {
            $this->errors[] = 'Email address too long (max 254 characters)';
            return false;
        }

        // Split email into local and domain parts
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            $this->errors[] = 'Email must contain exactly one @ symbol';
            return false;
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Validate local part
        if (!$this->validateLocalPart($local)) {
            return false;
        }

        // Validate domain part
        if (!$this->validateDomainPart($domain)) {
            return false;
        }

        return true;
    }

    public function validateHost(string $host): bool
    {
        $this->errors = [];

        // Check if it's an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check if it's a valid hostname
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/', $host)) {
            $this->errors[] = 'Invalid hostname format';
            return false;
        }

        // Check length
        if (strlen($host) > 253) {
            $this->errors[] = 'Hostname too long (max 253 characters)';
            return false;
        }

        // Check individual labels
        $labels = explode('.', $host);
        foreach ($labels as $label) {
            if (strlen($label) > 63) {
                $this->errors[] = 'Hostname label too long (max 63 characters per label)';
                return false;
            }
            if (empty($label)) {
                $this->errors[] = 'Empty hostname label';
                return false;
            }
        }

        return true;
    }

    public function validatePort(int $port): bool
    {
        $this->errors = [];

        if ($port < 1 || $port > 65535) {
            $this->errors[] = 'Port must be between 1 and 65535';
            return false;
        }

        return true;
    }

    public function validateSecurityType(string $security): bool
    {
        $this->errors = [];
        $validTypes = ['none', 'ssl', 'tls', 'starttls'];

        if (!in_array(strtolower($security), $validTypes)) {
            $this->errors[] = 'Invalid security type. Must be: ' . implode(', ', $validTypes);
            return false;
        }

        return true;
    }

    public function validateSMTPConfig(array $config): array
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Required fields
        $required = ['host', 'port', 'security'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                $result['errors'][] = "Field '{$field}' is required";
                $result['valid'] = false;
            }
        }

        if (!$result['valid']) {
            return $result;
        }

        // Validate individual fields
        if (!$this->validateHost($config['host'])) {
            $result['errors'] = array_merge($result['errors'], $this->getErrors());
            $result['valid'] = false;
        }

        if (!$this->validatePort((int)$config['port'])) {
            $result['errors'] = array_merge($result['errors'], $this->getErrors());
            $result['valid'] = false;
        }

        if (!$this->validateSecurityType($config['security'])) {
            $result['errors'] = array_merge($result['errors'], $this->getErrors());
            $result['valid'] = false;
        }

        // Validate credentials if provided
        if (!empty($config['username'])) {
            if (!$this->validateEmail($config['username'])) {
                $result['errors'] = array_merge($result['errors'], $this->getErrors());
                $result['valid'] = false;
            }
        }

        // Port-specific validations
        $port = (int)$config['port'];
        $security = strtolower($config['security']);

        if ($port === 465 && $security !== 'ssl') {
            $result['errors'][] = 'Port 465 typically requires SSL security';
        }

        if ($port === 587 && $security === 'none') {
            $result['errors'][] = 'Port 587 typically requires TLS or STARTTLS security';
        }

        return $result;
    }

    public function validateIMAPConfig(array $config): array
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Required fields
        $required = ['host', 'port', 'security'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                $result['errors'][] = "Field '{$field}' is required";
                $result['valid'] = false;
            }
        }

        if (!$result['valid']) {
            return $result;
        }

        // Validate individual fields
        if (!$this->validateHost($config['host'])) {
            $result['errors'] = array_merge($result['errors'], $this->getErrors());
            $result['valid'] = false;
        }

        if (!$this->validatePort((int)$config['port'])) {
            $result['errors'] = array_merge($result['errors'], $this->getErrors());
            $result['valid'] = false;
        }

        if (!$this->validateSecurityType($config['security'])) {
            $result['errors'] = array_merge($result['errors'], $this->getErrors());
            $result['valid'] = false;
        }

        // Validate credentials if provided
        if (!empty($config['username'])) {
            if (!$this->validateEmail($config['username'])) {
                $result['errors'] = array_merge($result['errors'], $this->getErrors());
                $result['valid'] = false;
            }
        }

        // Port-specific validations
        $port = (int)$config['port'];
        $security = strtolower($config['security']);

        if ($port === 993 && $security !== 'ssl') {
            $result['errors'][] = 'Port 993 typically requires SSL security';
        }

        if ($port === 143 && $security === 'ssl') {
            $result['errors'][] = 'Port 143 typically does not use SSL (use 993 for SSL)';
        }

        return $result;
    }

    public function validateEmailContent(array $content): array
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Check required fields
        if (empty($content['to'])) {
            $result['errors'][] = 'Recipient email address is required';
            $result['valid'] = false;
        } else {
            if (!$this->validateEmail($content['to'])) {
                $result['errors'] = array_merge($result['errors'], $this->getErrors());
                $result['valid'] = false;
            }
        }

        if (empty($content['subject'])) {
            $result['errors'][] = 'Email subject is required';
            $result['valid'] = false;
        }

        if (empty($content['body'])) {
            $result['errors'][] = 'Email body is required';
            $result['valid'] = false;
        }

        // Check content length limits
        if (isset($content['subject']) && strlen($content['subject']) > 255) {
            $result['errors'][] = 'Subject line too long (max 255 characters)';
            $result['valid'] = false;
        }

        if (isset($content['body']) && strlen($content['body']) > 1048576) { // 1MB
            $result['errors'][] = 'Email body too long (max 1MB)';
            $result['valid'] = false;
        }

        return $result;
    }

    private function validateLocalPart(string $local): bool
    {
        if (strlen($local) > 64) {
            $this->errors[] = 'Local part of email too long (max 64 characters)';
            return false;
        }

        if (empty($local)) {
            $this->errors[] = 'Local part of email cannot be empty';
            return false;
        }

        // Check for invalid characters
        if (!preg_match('/^[a-zA-Z0-9._%+-]+$/', $local)) {
            $this->errors[] = 'Local part contains invalid characters';
            return false;
        }

        // Check for consecutive dots
        if (strpos($local, '..') !== false) {
            $this->errors[] = 'Local part cannot contain consecutive dots';
            return false;
        }

        // Check for leading/trailing dots
        if ($local[0] === '.' || $local[strlen($local) - 1] === '.') {
            $this->errors[] = 'Local part cannot start or end with a dot';
            return false;
        }

        return true;
    }

    private function validateDomainPart(string $domain): bool
    {
        if (strlen($domain) > 253) {
            $this->errors[] = 'Domain part too long (max 253 characters)';
            return false;
        }

        if (empty($domain)) {
            $this->errors[] = 'Domain part cannot be empty';
            return false;
        }

        // Check if it's an IP address in brackets
        if ($domain[0] === '[' && $domain[strlen($domain) - 1] === ']') {
            $ip = substr($domain, 1, -1);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->errors[] = 'Invalid IP address in domain part';
                return false;
            }
            return true;
        }

        // Validate as hostname
        return $this->validateHost($domain);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getLastError(): ?string
    {
        return end($this->errors) ?: null;
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }
}
