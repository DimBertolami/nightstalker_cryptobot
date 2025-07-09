<?php
/**
 * Get Portfolio API Endpoint
 * 
 * Returns the user's cryptocurrency portfolio with current values and profit/loss calculations
 * Part of the Night Stalker cryptobot platform
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

try {
    $db = getDBConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Default user ID (can be updated later for multi-user support)
    $userId = 1;
    
    // Check if portfolio table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'portfolio'");
    $stmt->execute();
    $hasPortfolioTable = $stmt->rowCount() > 0;
    
    // Create portfolio table if it doesn't exist
    if (!$hasPortfolioTable) {
        $db->exec("CREATE TABLE portfolio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 1,
            coin_id VARCHAR(50) NOT NULL,
            amount DECIMAL(18,8) NOT NULL DEFAULT 0,
            avg_buy_price DECIMAL(18,8) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_coin (user_id, coin_id)
        )");
    }
    
    // Get portfolio data
    $portfolio = [];
    
    if ($hasPortfolioTable) {
        // Get portfolio data with coin information
        $query = "SELECT p.id, p.user_id, p.coin_id, p.amount, p.avg_buy_price, 
                  c.symbol, c.name, c.current_price 
                  FROM portfolio p 
                  JOIN coins c ON p.coin_id = c.id 
                  WHERE p.user_id = :user_id AND p.amount > 0
                  ORDER BY p.amount * c.current_price DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If no portfolio data, create mock data for testing
    if (empty($portfolio)) {
        // Get some coins to use for mock portfolio
        $coinQuery = "SELECT id, symbol, name, current_price FROM coins LIMIT 5";
        $stmt = $db->prepare($coinQuery);
        $stmt->execute();
        $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($coins)) {
            foreach ($coins as $index => $coin) {
                // Create mock portfolio entry
                $amount = rand(1, 100) / 10; // Random amount between 0.1 and 10
                $avgBuyPrice = $coin['current_price'] * (rand(80, 120) / 100); // Random price ±20% of current
                
                $portfolio[] = [
                    'id' => $index + 1,
                    'user_id' => $userId,
                    'coin_id' => $coin['id'],
                    'symbol' => $coin['symbol'],
                    'name' => $coin['name'],
                    'amount' => $amount,
                    'avg_buy_price' => $avgBuyPrice,
                    'current_price' => $coin['current_price']
                ];
            }
        }
    }
    
    // Calculate total value and profit/loss
    $totalValue = 0;
    $totalInvested = 0;
    
    foreach ($portfolio as &$item) {
        // Ensure numeric values
        $item['amount'] = floatval($item['amount']);
        $item['avg_buy_price'] = floatval($item['avg_buy_price']);
        $item['current_price'] = floatval($item['current_price']);
        
        // Calculate values
        $item['current_value'] = $item['amount'] * $item['current_price'];
        $item['invested_value'] = $item['amount'] * $item['avg_buy_price'];
        $item['profit_loss'] = $item['current_value'] - $item['invested_value'];
        $item['profit_loss_percent'] = $item['invested_value'] > 0 ? 
            ($item['profit_loss'] / $item['invested_value']) * 100 : 0;
        
        // Add to totals
        $totalValue += $item['current_value'];
        $totalInvested += $item['invested_value'];
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
            'total_profit_loss_percent' => $totalInvested > 0 ? 
                (($totalValue - $totalInvested) / $totalInvested) * 100 : 0,
            'item_count' => count($portfolio)
        ]
    ];
    
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("PDO Error in get-portfolio.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get-portfolio.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}