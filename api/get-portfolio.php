<?php
// Set JSON content type header
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Log script execution
error_log("=== get-portfolio.php started at " . date('Y-m-d H:i:s') . " ===");
error_log("Session data: " . print_r($_SESSION, true));
error_log("GET params: " . print_r($_GET, true));
error_log("POST params: " . print_r($_POST, true));

try {
    // Get user ID from session or use default for testing
    $userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 for testing
    
    // Get user portfolio
    $db = getDBConnection();
    
    // Debug: Log the user ID being used
    error_log("Fetching portfolio for user ID: " . $userId);
    
    // First, check if the user has any trades
    $checkTradesQuery = "SELECT COUNT(*) as trade_count FROM trades WHERE user_id = ?";
    $checkStmt = $db->prepare($checkTradesQuery);
    if (!$checkStmt) {
        throw new Exception("Prepare check trades failed: " . $db->error);
    }
    
    $checkStmt->bind_param('i', $userId);
    if (!$checkStmt->execute()) {
        throw new Exception("Execute check trades failed: " . $checkStmt->error);
    }
    
    $checkResult = $checkStmt->get_result();
    $tradesCount = $checkResult->fetch_assoc()['trade_count'];
    error_log("User $userId has $tradesCount trades in the database");
    
    // Get all trades for the user, including those with zero or negative balances for debugging
    $portfolioQuery = "
        SELECT 
            t.coin_id,
            SUM(CASE WHEN t.trade_type = 'buy' THEN t.amount ELSE -t.amount END) as balance,
            SUM(CASE WHEN t.trade_type = 'buy' THEN t.amount * t.price ELSE -t.amount * t.price END) as total_invested,
            SUM(CASE WHEN t.trade_type = 'buy' THEN t.amount ELSE 0 END) as total_bought,
            SUM(CASE WHEN t.trade_type = 'sell' THEN t.amount ELSE 0 END) as total_sold,
            COUNT(*) as trade_count,
            GROUP_CONCAT(CONCAT(t.trade_type, ' ', t.amount, ' @ ', t.price, ' on ', t.trade_time) SEPARATOR ' | ') as trade_details
        FROM trades t
        WHERE t.user_id = ?
        GROUP BY t.coin_id
        ORDER BY balance DESC
    ";
    
    // Execute the query to get user's portfolio
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
    $totalProfitLoss = 0;
    
    while ($row = $result->fetch_assoc()) {
        $coinId = $row['coin_id'];
        $balance = (float)$row['balance'];
        $totalInvestedForCoin = (float)$row['total_invested'];
        
        // Get the latest price for this coin
        $priceQuery = "SELECT price FROM price_history WHERE coin_id = ? ORDER BY recorded_at DESC LIMIT 1";
        $priceStmt = $db->prepare($priceQuery);
        $priceStmt->bind_param('s', $coinId);
        $priceStmt->execute();
        $priceResult = $priceStmt->get_result();
        
        $currentPrice = 0;
        if ($priceResult && $priceResult->num_rows > 0) {
            $priceData = $priceResult->fetch_assoc();
            $currentPrice = (float)$priceData['price'];
        }
        
        $currentValue = $balance * $currentPrice;
        $profitLoss = $currentValue - $totalInvestedForCoin;
        $profitLossPercent = $totalInvestedForCoin > 0 ? ($profitLoss / $totalInvestedForCoin) * 100 : 0;
        
        $portfolioItem = [
            'coin_id' => $coinId,
            'name' => $coinId,  // Using coin_id as name since we don't have a name mapping
            'symbol' => substr($coinId, 0, 8),  // Use first 8 chars of coin_id as symbol
            'current_price' => $currentPrice,
            'amount' => $balance,
            'total_invested' => $totalInvestedForCoin,
            'total_bought' => (float)$row['total_bought'],
            'total_sold' => (float)$row['total_sold'],
            'trade_count' => (int)$row['trade_count'],
            'current_value' => $currentValue,
            'profit_loss' => $profitLoss,
            'profit_loss_percent' => $profitLossPercent
        ];
        
        $portfolio[] = $portfolioItem;
        $totalValue += $currentValue;
        $totalInvested += $totalInvestedForCoin;
        $totalProfitLoss += $profitLoss;
    }
    
    // Calculate total profit/loss percentage
    $totalProfitLossPercent = $totalInvested > 0 ? ($totalProfitLoss / $totalInvested) * 100 : 0;
    
    // Prepare the response
    $response = [
        'success' => true,
        'portfolio' => $portfolio,
        'totals' => [
            'total_value' => $totalValue,
            'total_invested' => $totalInvested,
            'total_profit_loss' => $totalProfitLoss,
            'total_profit_loss_percent' => $totalProfitLossPercent
        ],
        'timestamp' => time(),
        'debug' => [
            'user_id' => $userId,
            'trades_count' => $tradesCount,
            'portfolio_items' => count($portfolio),
            'server' => $_SERVER['SERVER_NAME']
        ]
    ];
    
    // Clean any output that might have been generated
    ob_clean();
    
    // Output the response
    echo json_encode($response, JSON_PRETTY_PRINT);
    
    // Log the response for debugging
    error_log("Portfolio API Response: " . json_encode($response, JSON_PRETTY_PRINT));
    
    // End output buffering and flush
    ob_end_flush();
    exit;
    
    error_log("Executing portfolio query: " . $portfolioQuery);
    
    $stmt = $db->prepare($portfolioQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    // Bind the user_id parameter
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $portfolioResult = $stmt->get_result();
    if ($portfolioResult === false) {
        throw new Exception("Get result failed: " . $db->error);
    }
    
    // Debug: Log the query and result count
    $numRows = $portfolioResult ? $portfolioResult->num_rows : 0;
    error_log("Portfolio query executed. Rows found: " . $numRows);
    
    $portfolio = [];
    $totalValue = 0;
    $totalInvested = 0;
    $totalProfitLoss = 0;
    
    if ($portfolioResult && $numRows > 0) {
        while ($row = $portfolioResult->fetch_assoc()) {
            error_log("Processing portfolio row: " . print_r($row, true));
            
            $amount = (float)$row['amount'];
            $currentPrice = (float)$row['current_price'];
            $currentValue = $amount * $currentPrice;
            $coinInvested = (float)$row['total_invested'];
            $profitLoss = $currentValue - $coinInvested;
            $profitLossPercent = $coinInvested > 0 ? ($profitLoss / $coinInvested) * 100 : 0;
            
            // Add to totals
            $totalValue += $currentValue;
            $totalInvested += $coinInvested;
            $totalProfitLoss += $profitLoss;
            
            $portfolioItem = [
                'coin_id' => $row['coin_id'],
                'name' => $row['name'] ?: 'Unknown',
                'symbol' => $row['symbol'] ?: 'UNKNOWN',
                'amount' => $amount,
                'price' => $currentPrice,
                'value' => $currentValue,
                'total_invested' => $coinInvested,  // Changed from $totalInvested to $coinInvested
                'profit_loss' => $profitLoss,
                'profit_loss_percent' => $profitLossPercent,
                'current_value' => $currentValue
            ];
            
            $portfolio[] = $portfolioItem;
            
            error_log("Added to portfolio: " . json_encode($portfolioItem));
        }
    } else {
        error_log("No portfolio items found or empty result set");
    }
    
    // Calculate totals
    $totalProfitLossPercent = $totalInvested > 0 ? ($totalProfitLoss / $totalInvested) * 100 : 0;
    
    $response = [
        'success' => true,
        'portfolio' => $portfolio,
        'totals' => [
            'total_value' => $totalValue,
            'total_invested' => $totalInvested,
            'total_profit_loss' => $totalProfitLoss,
            'total_profit_loss_percent' => $totalProfitLossPercent
        ],
        'timestamp' => time(),
        'debug' => [
            'user_id' => $userId,
            'trades_count' => $tradesCount,
            'portfolio_items' => count($portfolio),
            'query' => $portfolioQuery,
            'query_params' => [$userId],
            'server' => $_SERVER['SERVER_NAME']
        ]
    ];
    
    // Clean any output that might have been generated
    ob_clean();
    
    // Output the response
    echo json_encode($response, JSON_PRETTY_PRINT);
    
    // Log the response for debugging
    error_log("Portfolio API Response: " . json_encode($response, JSON_PRETTY_PRINT));
    
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
