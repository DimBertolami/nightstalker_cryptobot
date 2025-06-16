<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';
require_once __DIR__ . '/../includes/BitvavoTrader.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Ensure proper JSON encoding
ini_set('default_charset', 'UTF-8');

// Initialize the trading logger
$logger = new TradingLogger();

// Get selected strategy from session or default to new_coin_strategy
session_start();
$selectedStrategy = $_SESSION['selected_strategy'] ?? 'new_coin_strategy';

// Get trading statistics
$stats = $logger->getStats($selectedStrategy);

// Check if there's an active trade
if (empty($stats['active_trade_symbol'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No active trade'
    ]);
    exit;
}

// Format time duration
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . "s";
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . "m " . ($seconds % 60) . "s";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return $hours . "h " . $minutes . "m " . $secs . "s";
    }
}

try {
    // Get the current price of the active trade
    $symbol = $stats['active_trade_symbol'];
    $buyPrice = $stats['active_trade_buy_price'];
    
    // Initialize the trader in test mode (we're just fetching prices, not trading)
    $trader = new BitvavoTrader(true);
    
    // Get the current price
    $tradingPair = $symbol . '/EUR'; // Assuming EUR is the quote currency
    $ticker = $trader->fetchTicker($tradingPair);
    $currentPrice = $ticker['last'];
    
    // Calculate profit/loss
    $profitLoss = (($currentPrice - $buyPrice) / $buyPrice) * 100;
    
    // Calculate holding time
    $holdingTime = time() - strtotime($stats['active_trade_time']);
    
    echo json_encode([
        'success' => true,
        'symbol' => $symbol,
        'buy_price' => number_format($buyPrice, 8),
        'current_price' => number_format($currentPrice, 8),
        'profit_percentage' => number_format($profitLoss, 2) . '%',
        'raw_profit_percentage' => $profitLoss,
        'holding_time' => formatDuration($holdingTime),
        'holding_seconds' => $holdingTime,
        'last_update' => date('H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
