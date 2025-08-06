<?php
/**
 * Get Logs API Endpoint
 * Retrieves test logs from the database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../vendor/autoload.php';

use EmailTester\config\Database;
use EmailTester\utils\SecurityUtils;

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Check rate limiting
if (!SecurityUtils::checkRateLimit('get_logs', 20, 300)) { // 20 requests per 5 minutes
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

try {
    // Load database configuration from .env file
    if (file_exists(__DIR__ . '/../../.env')) {
        $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        
        // Configure database connection
        Database::configure([
            'host' => $config['DB_HOST'] ?? 'localhost',
            'port' => $config['DB_PORT'] ?? 3306,
            'database' => $config['DB_DATABASE'] ?? 'smtp_test_tool',
            'username' => $config['DB_USERNAME'] ?? 'smtp_user',
            'password' => $config['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4'
        ]);
    } else {
        throw new Exception('Configuration file (.env) not found');
    }

    // Get database connection
    $pdo = Database::getConnection();

    // Get query parameters
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    $test_type = SecurityUtils::sanitizeInput($_GET['test_type'] ?? '');

    // Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }

    // Validate offset
    if ($offset < 0) {
        $offset = 0;
    }

    // Build query
    $where_clause = '';
    $params = [];

    if (!empty($test_type)) {
        $where_clause = 'WHERE test_type = :test_type';
        $params['test_type'] = $test_type;
    }

    $sql = "SELECT 
                id,
                test_type,
                server_host,
                server_port,
                security_type,
                username,
                test_result,
                error_message,
                response_time,
                test_timestamp,
                ip_address
            FROM test_logs 
            $where_clause
            ORDER BY test_timestamp DESC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $logs = $stmt->fetchAll();

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM test_logs $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    
    foreach ($params as $key => $value) {
        $count_stmt->bindValue(':' . $key, $value);
    }
    
    $count_stmt->execute();
    $total_count = $count_stmt->fetch()['total'];

    // Format the logs for display
    $formatted_logs = [];
    foreach ($logs as $log) {
        $formatted_log = [
            'id' => $log['id'],
            'test_type' => $log['test_type'],
            'target_host' => $log['server_host'],
            'target_port' => $log['server_port'],
            'status' => $log['test_result'],
            'created_at' => $log['test_timestamp'] . ' UTC', // Explicitly mark as UTC
            'created_at_utc' => $log['test_timestamp'], // Raw UTC timestamp for JavaScript
            'user_ip' => $log['ip_address']
        ];

        // Parse result_data if it's JSON
        if (!empty($log['error_message'])) {
            $formatted_log['result_data'] = 'Error: ' . $log['error_message'];
        } elseif ($log['test_result'] === 'success') {
            $formatted_log['result_data'] = 'Test completed successfully';
        } else {
            $formatted_log['result_data'] = 'Test failed';
        }

        // Add response time if available
        if (!empty($log['response_time'])) {
            $formatted_log['response_time'] = $log['response_time'] . 'ms';
        }

        $formatted_logs[] = $formatted_log;
    }

    // Return the results
    echo json_encode([
        'success' => true,
        'logs' => $formatted_logs,
        'pagination' => [
            'total' => (int)$total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ]
    ]);

} catch (PDOException $e) {
    error_log('Database error in get-logs.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);

} catch (Exception $e) {
    error_log('Error in get-logs.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error occurred'
    ]);
}
