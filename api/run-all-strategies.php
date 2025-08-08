<?php
/**
 * Run All Strategies API Endpoint
 * 
 * Executes all active trading strategies for the Night Stalker cryptobot
 */

header('Content-Type: application/json');

// Enable error reporting but don't display errors to the client
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/htdocs/NS/logs/php-error.log');

// Include configuration
require_once '../includes/config.php';

// Direct database connection to avoid circular dependencies
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    return $db;
}

// Function to execute a strategy using the auto-trade.php endpoint
function executeStrategy($strategyName) {
    $url = 'http://localhost/NS/api/auto-trade.php';
    
    $data = [
        'strategy' => $strategyName
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        throw new Exception("Failed to execute strategy: $strategyName");
    }
    
    return json_decode($result, true);
}

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get all active strategies
    $stmt = $db->prepare("SELECT name FROM strategies WHERE is_active = TRUE ORDER BY name");
    $stmt->execute();
    $strategies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($strategies)) {
        echo json_encode([
            'success' => true,
            'message' => 'No active strategies found',
            'results' => []
        ]);
        exit;
    }
    
    // Execute each active strategy
    $results = [];
    foreach ($strategies as $strategy) {
        try {
            $strategyName = $strategy['name'];
            $result = executeStrategy($strategyName);
            
            // Update last_run timestamp
            $stmt = $db->prepare("UPDATE strategies SET last_run = CURRENT_TIMESTAMP WHERE name = :name");
            $stmt->bindParam(':name', $strategyName);
            $stmt->execute();
            
            $results[] = $result;
        } catch (Exception $e) {
            $results[] = [
                'success' => false,
                'strategy' => $strategy['name'],
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'All active strategies executed',
        'results' => $results
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
