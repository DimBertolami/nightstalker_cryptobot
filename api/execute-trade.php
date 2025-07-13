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
    if (!is_numeric($amount) || $amount <= 0) {
        throw new Exception('Amount must be a positive number');
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
        // First, try to find the coin in the database by symbol to get the correct coin_id
        $stmt = $db->prepare("SELECT id FROM coins WHERE symbol = :symbol LIMIT 1");
        $stmt->bindParam(':symbol', $symbol);
        $stmt->execute();
        $coinRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If we found the coin in the database, use its ID
        if ($coinRecord) {
            $coinId = $coinRecord['id'];
            error_log("Found coin in database with ID: $coinId");
        } else {
            error_log("Could not find coin with symbol: $symbol in database");
        }
        
        // Check if coin already exists in portfolio
        $stmt = $db->prepare("SELECT id, amount, avg_buy_price FROM portfolio WHERE coin_id = :coin_id");
        $stmt->bindParam(':coin_id', $coinId);
        $stmt->execute();
        $existingCoin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Default user_id (assuming single user system for now)
        $userId = 1;
        
        if ($existingCoin) {
            // Update existing position
            $newAmount = $existingCoin['amount'] + $amount;
            $newAvgPrice = (($existingCoin['avg_buy_price'] * $existingCoin['amount']) + ($price * $amount)) / $newAmount;
            
            $stmt = $db->prepare("UPDATE portfolio SET 
                amount = :amount,
                avg_buy_price = :avg_price
                WHERE id = :id");
            $stmt->bindParam(':amount', $newAmount);
            $stmt->bindParam(':avg_price', $newAvgPrice);
            $stmt->bindParam(':id', $existingCoin['id']);
            $stmt->execute();
            
            $message = "Added $amount $symbol to your portfolio";
        } else {
            // Create new position
            $stmt = $db->prepare("INSERT INTO portfolio (user_id, coin_id, amount, buy_price, avg_buy_price) 
                VALUES (:user_id, :coin_id, :amount, :price, :price)");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':coin_id', $coinId);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':price', $price);
            $stmt->execute();
            
            $message = "Bought $amount $symbol for your portfolio";
        }
    } else { // Sell action
        // First, try to find the coin in the database by symbol to get the correct coin_id
        $stmt = $db->prepare("SELECT id FROM coins WHERE symbol = :symbol LIMIT 1");
        $stmt->bindParam(':symbol', $symbol);
        $stmt->execute();
        $coinRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If we found the coin in the database, use its ID
        if ($coinRecord) {
            $coinId = $coinRecord['id'];
            error_log("Found coin in database with ID: $coinId");
        } else {
            error_log("Could not find coin with symbol: $symbol in database");
        }
        
        // Check if coin exists in portfolio
        $stmt = $db->prepare("SELECT id, amount FROM portfolio WHERE coin_id = :coin_id");
        $stmt->bindParam(':coin_id', $coinId);
        $stmt->execute();
        $existingCoin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingCoin) {
            throw new Exception("You don't own any $symbol to sell");
        }
        
        // Check if user has enough to sell
        if ($amount > $existingCoin['amount']) {
            throw new Exception("You only have {$existingCoin['amount']} $symbol available to sell");
        }
        
        // Calculate remaining amount
        $remainingAmount = $existingCoin['amount'] - $amount;
        
        if ($remainingAmount > 0) {
            // Update existing position
            $stmt = $db->prepare("UPDATE portfolio SET amount = :amount WHERE id = :id");
            $stmt->bindParam(':amount', $remainingAmount);
            $stmt->bindParam(':id', $existingCoin['id']);
            $stmt->execute();
            
            $message = "Sold $amount $symbol from your portfolio";
        } else {
            // Remove position completely
            $stmt = $db->prepare("DELETE FROM portfolio WHERE id = :id");
            $stmt->bindParam(':id', $existingCoin['id']);
            $stmt->execute();
            
            $message = "Sold all your $symbol from your portfolio";
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
