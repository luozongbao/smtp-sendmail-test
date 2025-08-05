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
require_once __DIR__ . '/../../src/config/database.php';

use EmailTester\Utils\SecurityUtils;

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
    // Load configuration
    if (!file_exists(__DIR__ . '/../../config.php')) {
        throw new Exception('Configuration file not found');
    }
    
    require_once __DIR__ . '/../../config.php';

    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

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
                target_host,
                target_port,
                status,
                result_data,
                user_ip,
                created_at
            FROM test_logs 
            $where_clause
            ORDER BY created_at DESC 
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
            'target_host' => $log['target_host'],
            'target_port' => $log['target_port'],
            'status' => $log['status'],
            'created_at' => $log['created_at'],
            'user_ip' => $log['user_ip']
        ];

        // Parse result_data if it's JSON
        if (!empty($log['result_data'])) {
            $result_data = json_decode($log['result_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Extract meaningful information from result data
                if (isset($result_data['error'])) {
                    $formatted_log['result_data'] = 'Error: ' . $result_data['error'];
                } elseif (isset($result_data['connection_info'])) {
                    $formatted_log['result_data'] = $result_data['connection_info'];
                } elseif (isset($result_data['open_ports'])) {
                    $open_count = count($result_data['open_ports']);
                    $formatted_log['result_data'] = "Found $open_count open port(s)";
                } else {
                    $formatted_log['result_data'] = 'Test completed successfully';
                }
            } else {
                $formatted_log['result_data'] = $log['result_data'];
            }
        } else {
            $formatted_log['result_data'] = 'No result data';
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
