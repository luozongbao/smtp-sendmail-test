<?php

// Configuration template - copy this to config.php during installation

return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'smtp_test_tool',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
    
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'SMTP Test Tool',
        'url' => $_ENV['APP_URL'] ?? 'http://localhost',
        'key' => $_ENV['APP_KEY'] ?? '',
        'admin_email' => $_ENV['ADMIN_EMAIL'] ?? '',
    ],
    
    'security' => [
        'csrf_secret' => $_ENV['CSRF_SECRET'] ?? '',
        'session_lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120),
    ],
    
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'file' => $_ENV['LOG_FILE'] ?? 'logs/application.log',
    ],
];
