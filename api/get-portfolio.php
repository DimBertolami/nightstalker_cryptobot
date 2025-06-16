<?php
// Set JSON content type header
header('Content-Type: application/json');

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

try {
    // Get user ID from session or use default for testing
    $userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 for testing
    
    // Get user portfolio
    $db = getDBConnection();
    
    // Query to get balance for each coin (buys - sells)
    $portfolioQuery = "SELECT 
                        t.coin_id,
                        c.name,
                        c.symbol,
                        c.price,
                        SUM(CASE WHEN t.trade_type = 'buy' THEN t.amount ELSE 0 END) - 
                        SUM(CASE WHEN t.trade_type = 'sell' THEN t.amount ELSE 0 END) as balance 
                      FROM trades t
                      JOIN cryptocurrencies c ON t.coin_id = c.id
                      GROUP BY t.coin_id, c.name, c.symbol, c.price
                      HAVING balance > 0";
    
    $portfolioResult = $db->query($portfolioQuery);
    $portfolio = [];
    
    if ($portfolioResult) {
        while ($row = $portfolioResult->fetch_assoc()) {
            $portfolio[] = [
                'coin_id' => $row['coin_id'],
                'name' => $row['name'],
                'symbol' => $row['symbol'],
                'amount' => (float)$row['balance'],
                'price' => (float)$row['price'],
                'value' => (float)$row['balance'] * (float)$row['price']
            ];
        }
    }
    
    // Clean any output that might have been generated
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'portfolio' => $portfolio,
        'timestamp' => time()
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
    
} catch (Exception $e) {
    // Clean any output that might have been generated
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get portfolio data: ' . $e->getMessage()
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
}
