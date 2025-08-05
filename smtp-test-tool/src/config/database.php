<?php

namespace EmailTester\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }
        return self::$connection;
    }

    private static function connect(): void
    {
        $host = self::$config['host'] ?? 'localhost';
        $port = self::$config['port'] ?? 3306;
        $database = self::$config['database'] ?? '';
        $username = self::$config['username'] ?? '';
        $password = self::$config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public static function testConnection(string $host, int $port, string $database, string $username, string $password): bool
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function createDatabase(string $host, int $port, string $database, string $username, string $password): bool
    {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            $stmt = $pdo->prepare("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}
