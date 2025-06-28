<?php
// Start output buffering at the very beginning
while (ob_get_level()) ob_end_clean();
ob_start();

// Set error handling before any other code
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-error.log');

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    // Clear any previous output
    while (ob_get_level()) ob_end_clean();
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $json = json_encode($data);
    if ($json === false) {
        // JSON encoding failed, send error
        $json = json_encode([
            'success' => false,
            'message' => 'JSON encoding error: ' . json_last_error_msg(),
            'data' => null
        ]);
    }
    
    echo $json;
    exit;
}

// Handle any uncaught exceptions
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendJsonResponse([
        'success' => false,
        'message' => 'An unexpected error occurred',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
});

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}, E_ALL);

// Set JSON content type header
header('Content-Type: application/json; charset=utf-8');

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'portfolio' => [],
    'debug' => []
];

try {
    // Get user ID from session or use default for testing
    $userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 for testing
    
    // Get database connection
    require_once __DIR__ . '/../includes/database.php';
    $db = connectToDatabase();
    if (!$db || $db->connect_error) {
        throw new Exception("Database connection failed: " . ($db->connect_error ?? 'Unknown error'));
    }
    
    // Verify required tables exist
    $requiredTables = ['portfolio', 'price_history'];
    foreach ($requiredTables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            throw new Exception("Required table '$table' does not exist");
        }
    }
    
    // Debug info
    $response['debug'] = [
        'user_id' => $userId,
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'request_time' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'memory_usage' => memory_get_usage(true) / 1024 / 1024 . 'MB'
    ];
    
    // Get portfolio data
    $portfolioQuery = "
        SELECT 
            t.coin_id,
            t.amount,
            t.avg_buy_price,
            COALESCE((SELECT price FROM price_history WHERE coin_id = t.coin_id ORDER BY recorded_at DESC LIMIT 1), 0) as current_price
        FROM portfolio t
        WHERE t.user_id = ? AND t.amount > 0
        ORDER BY t.amount DESC
    ";
    
    $stmt = $db->prepare($portfolioQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Get result failed: " . $db->error);
    }
    
    // Process the portfolio data
    $portfolio = [];
    $totalValue = 0;
    $totalInvested = 0;
    
    while ($row = $result->fetch_assoc()) {
        $amount = (float)$row['amount'];
        $currentPrice = (float)$row['current_price'];
        $avgBuyPrice = (float)$row['avg_buy_price'];
        $currentValue = $amount * $currentPrice;
        $investedValue = $amount * $avgBuyPrice;
        
        $portfolio[] = [
            'coin_id' => $row['coin_id'],
            'symbol' => str_replace('COIN_', '', $row['coin_id']),
            'amount' => $amount,
            'current_price' => $currentPrice,
            'current_value' => $currentValue,
            'avg_buy_price' => $avgBuyPrice,
            'invested_value' => $investedValue,
            'profit_loss' => $currentValue - $investedValue,
            'profit_loss_percent' => $avgBuyPrice > 0 ? (($currentPrice - $avgBuyPrice) / $avgBuyPrice) * 100 : 0
        ];
        
        $totalValue += $currentValue;
        $totalInvested += $investedValue;
    }
    
    // Success response
    $response = [
        'success' => true,
        'message' => 'Portfolio loaded successfully',
        'portfolio' => $portfolio,
        'summary' => [
            'total_value' => $totalValue,
            'total_invested' => $totalInvested,
            'total_profit_loss' => $totalValue - $totalInvested,
            'total_profit_loss_percent' => $totalInvested > 0 ? (($totalValue - $totalInvested) / $totalInvested) * 100 : 0,
            'item_count' => count($portfolio)
        ]
    ];
    
} catch (Exception $e) {
    error_log("Error in get-portfolio.php: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
}

// Send the JSON response
sendJsonResponse($response);