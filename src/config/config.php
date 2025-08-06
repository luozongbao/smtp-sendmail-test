<?php

// Configuration file generated during installation

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

return [
    'database' => [
        'host' => $_ENV['DB_HOST'],
        'port' => (int)$_ENV['DB_PORT'],
        'database' => $_ENV['DB_DATABASE'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
    ],
    
    'app' => [
        'name' => $_ENV['APP_NAME'],
        'url' => $_ENV['APP_URL'],
        'key' => $_ENV['APP_KEY'],
        'admin_email' => $_ENV['ADMIN_EMAIL'],
    ],
    
    'security' => [
        'csrf_secret' => $_ENV['CSRF_SECRET'],
        'session_lifetime' => (int)$_ENV['SESSION_LIFETIME'],
    ],
    
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'],
        'file' => $_ENV['LOG_FILE'],
    ],
];
