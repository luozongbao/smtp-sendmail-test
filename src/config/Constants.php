<?php

namespace EmailTester\config;

class Constants
{
    // Application settings
    public const APP_NAME = 'SMTP Test Tool';
    public const APP_VERSION = '1.0.0';
    
    // Security settings
    public const SESSION_LIFETIME = 7200; // 2 hours
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const RATE_LIMIT_REQUESTS = 60; // per minute
    
    // Email settings
    public const DEFAULT_SMTP_PORTS = [25, 465, 587, 2525];
    public const DEFAULT_IMAP_PORTS = [143, 993];
    public const DEFAULT_POP3_PORTS = [110, 995];
    
    // Test limits
    public const MAX_TEST_HISTORY = 1000;
    public const TEST_TIMEOUT = 30; // seconds
    public const MAX_EMAIL_SIZE = 25 * 1024 * 1024; // 25MB
    
    // Security types
    public const SECURITY_TYPES = [
        'none' => 'None',
        'ssl' => 'SSL',
        'tls' => 'TLS',
        'starttls' => 'STARTTLS'
    ];
    
    // Test result types
    public const TEST_RESULTS = [
        'success' => 'Success',
        'failure' => 'Failure',
        'partial' => 'Partial'
    ];
    
    // Test types
    public const TEST_TYPES = [
        'smtp' => 'SMTP Test',
        'imap' => 'IMAP Test',
        'email' => 'Email Send Test',
        'port' => 'Port Scan Test'
    ];
    
    // Common email providers
    public const EMAIL_PROVIDERS = [
        'gmail' => [
            'name' => 'Gmail',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_security' => 'tls',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_security' => 'ssl'
        ],
        'outlook' => [
            'name' => 'Outlook.com',
            'smtp_host' => 'smtp-mail.outlook.com',
            'smtp_port' => 587,
            'smtp_security' => 'starttls',
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_security' => 'ssl'
        ],
        'yahoo' => [
            'name' => 'Yahoo Mail',
            'smtp_host' => 'smtp.mail.yahoo.com',
            'smtp_port' => 587,
            'smtp_security' => 'tls',
            'imap_host' => 'imap.mail.yahoo.com',
            'imap_port' => 993,
            'imap_security' => 'ssl'
        ]
    ];
}
