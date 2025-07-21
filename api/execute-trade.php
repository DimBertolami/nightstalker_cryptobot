<?php
/**
 * Execute Trade API Endpoint
 * 
 * Handles buy and sell operations for the Night Stalker cryptobot
 * Supports autonomous trading based on selected strategies
 */

header('Content-Type: application/json');

// Enable error reporting but don't display errors to the client
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/../logs/php-error.log');

// Include configuration
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/pdo_functions.php';
require_once __DIR__.'/../includes/database.php';


// Function to validate request parameters
function validateRequest($requiredParams) {
    foreach ($requiredParams as $param) {
        if (!isset($_POST[$param]) || empty($_POST[$param])) {
            return false;
        }
    }
    return true;
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
    
    // Get action type (buy or sell)
    $action = isset($_POST['action']) ? strtolower($_POST['action']) : '';
    
    // Validate action
    if (!in_array($action, ['buy', 'sell'])) {
        throw new Exception('Invalid action. Must be "buy" or "sell"');
    }
    
    // Get and validate required parameters
    $requiredParams = ['coin_id', 'symbol', 'amount', 'price'];
    if (!validateRequest($requiredParams)) {
        throw new Exception('Missing required parameters');
    }
    
    $coinId = $_POST['coin_id'];
    $symbol = $_POST['symbol'];
    $amount = $_POST['amount'];
    $price = $_POST['price'];
    
    // Debug the incoming data
    error_log("Execute trade - Coin ID: $coinId, Symbol: $symbol, Amount: $amount, Price: $price");
    
    // Validate numeric values
    if ($amount !== 'all') {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('Amount must be a positive number');
        }
    }
    
    if (!is_numeric($price) || $price <= 0) {
        throw new Exception('Price must be a positive number');
    }
    
    // Check if portfolio table exists, create if not
    $stmt = $db->prepare("SHOW TABLES LIKE 'portfolio'");
    $stmt->execute();
    $hasPortfolioTable = $stmt->rowCount() > 0;
    
    if (!$hasPortfolioTable) {
        // Create portfolio table with all required columns
        $db->exec("CREATE TABLE portfolio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT 1,
            coin_id INT NOT NULL,
            amount DECIMAL(32,12) NOT NULL,
            buy_price DECIMAL(32,12) NOT NULL,
            avg_buy_price DECIMAL(32,12) DEFAULT 0,
            bought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Process the trade based on action
    if ($action === 'buy') {
        $tradeId = executeBuyPDO($coinId, $amount, $price);
        if ($tradeId) {
            $message = "Successfully bought $amount of $symbol";
        } else {
            throw new Exception("Failed to execute buy trade.");
        }
    } else { // Sell action
        $result = executeSellPDO($coinId, $amount, $price);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            throw new Exception($result['message']);
        }
    }
    
    // Log the trade
    $tradeType = $action === 'buy' ? 'BUY' : 'SELL';
    
    // Create a trade log entry in a simple format that doesn't depend on existing schema
    // This ensures we have a record of the trade even if the database structure varies
    try {
        // Check if trade_log table exists, create if not
        $stmt = $db->prepare("SHOW TABLES LIKE 'trade_log'");
        $stmt->execute();
        $hasTradeLogTable = $stmt->rowCount() > 0;
        
        if (!$hasTradeLogTable) {
            // Create a simple trade_log table that will work in any environment
            $db->exec("CREATE TABLE trade_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coin_id VARCHAR(50) NOT NULL,
                symbol VARCHAR(20) NOT NULL,
                amount DECIMAL(18,8) NOT NULL,
                price DECIMAL(18,8) NOT NULL,
                action VARCHAR(10) NOT NULL,
                strategy VARCHAR(50) DEFAULT 'manual',
                trade_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        // Default strategy if not provided
        $strategy = isset($_POST['strategy']) ? $_POST['strategy'] : 'manual';
        
        // Log the trade
        $logStmt = $db->prepare("INSERT INTO trade_log (coin_id, symbol, amount, price, action, strategy) 
            VALUES (:coin_id, :symbol, :amount, :price, :action, :strategy)");
        
        $logStmt->bindParam(':coin_id', $coinId);
        $logStmt->bindParam(':symbol', $symbol);
        $logStmt->bindParam(':amount', $amount);
        $logStmt->bindParam(':price', $price);
        $logStmt->bindParam(':action', $action);
        $logStmt->bindParam(':strategy', $strategy);
        $logStmt->execute();
    } catch (Exception $e) {
        // Log the error but don't fail the trade if logging fails
        error_log("Failed to log trade: " . $e->getMessage());
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'action' => $action,
            'coin_id' => $coinId,
            'symbol' => $symbol,
            'amount' => $amount,
            'price' => $price,
            'total' => $amount * $price
        ]
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
