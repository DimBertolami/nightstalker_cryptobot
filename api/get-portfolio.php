<?php
/**
 * Get Portfolio API Endpoint
 * 
 * Returns the user's cryptocurrency portfolio with current values and profit/loss calculations
 * Part of the Night Stalker cryptobot platform
 */

header('Content-Type: application/json');

// Enable error reporting but log instead of display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/htdocs/NS/logs/php-error.log');

// Include configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Default user ID (can be updated later for multi-user support)
    $userId = 1;

    // Get portfolio data with coin information
    $query = "SELECT 
        portfolio.*, 
        COALESCE(c.current_price, cr.price) as current_price,
        COALESCE(c.current_price, cr.price) as current_price_usd,
        COALESCE(c.symbol, cr.symbol) as symbol,
        COALESCE(c.coin_name, cr.name, portfolio.coin_id) as name
    FROM portfolio 
    LEFT JOIN coins c ON portfolio.coin_id = c.id
    LEFT JOIN cryptocurrencies cr ON (portfolio.coin_id = cr.id OR portfolio.coin_id = CONCAT('COIN_', cr.id))
    WHERE portfolio.user_id = :user_id 
    AND portfolio.amount > 0
    ORDER BY portfolio.amount DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals and format data
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

        // Update totals
        $totalValue += $item['current_value'];
        $totalInvested += $item['invested_value'];
    }

    echo json_encode([
        'success' => true,
        'portfolio' => $portfolio,
        'summary' => [
            'total_value' => $totalValue,
            'total_invested' => $totalInvested,
            'total_profit_loss' => $totalValue - $totalInvested,
            'total_profit_loss_percent' => $totalInvested > 0 ? 
                (($totalValue - $totalInvested) / $totalInvested) * 100 : 0,
            'item_count' => count($portfolio)
        ]
    ]);

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