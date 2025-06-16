<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Ensure proper JSON encoding
ini_set('default_charset', 'UTF-8');

// Initialize the trading logger
$logger = new TradingLogger();

try {
    // Get trading statistics
    $stats = $logger->getStats('main_strategy');
    
    // Get recent sell events to build performance chart
    $sellEvents = $logger->getFilteredEvents(
        'main_strategy',
        'sell',
        null,
        null,
        null,
        30 // Limit to last 30 sell events
    );
    
    $performanceData = [];
    $cumulativeProfit = 0;
    
    // Process sell events to create performance chart data
    foreach ($sellEvents as $event) {
        $eventData = json_decode($event['event_data'], true);
        if (isset($eventData['profit'])) {
            $cumulativeProfit += $eventData['profit'];
            $performanceData[] = [
                'date' => date('m/d H:i', strtotime($event['event_time'])),
                'symbol' => $eventData['symbol'],
                'profit' => round($eventData['profit'], 2),
                'profit_percentage' => round($eventData['profit_percentage'], 2),
                'cumulative_profit' => round($cumulativeProfit, 2)
            ];
        }
    }
    
    // Return the performance data
    // Handle potentially missing fields in stats
    $response = [
        'success' => true,
        'performance_data' => $performanceData,
        'total_trades' => $stats['trades_executed'] ?? 0,
        'successful_trades' => $stats['successful_trades'] ?? 0,
        'failed_trades' => $stats['failed_trades'] ?? 0,
        'win_rate' => $stats['win_rate'] ?? 0,
        'total_profit' => $stats['total_profit'] ?? 0,
        'avg_profit' => $stats['avg_profit_percentage'] ?? 0
    ];
    
    // Only add best/worst trade data if available
    if (isset($stats['best_trade_profit']) && isset($stats['best_trade_symbol'])) {
        $response['best_trade'] = [
            'profit' => $stats['best_trade_profit'],
            'symbol' => $stats['best_trade_symbol']
        ];
    } else {
        $response['best_trade'] = [
            'profit' => 0,
            'symbol' => 'N/A'
        ];
    }
    
    if (isset($stats['worst_trade_loss']) && isset($stats['worst_trade_symbol'])) {
        $response['worst_trade'] = [
            'profit' => $stats['worst_trade_loss'],
            'symbol' => $stats['worst_trade_symbol']
        ];
    } else {
        $response['worst_trade'] = [
            'profit' => 0,
            'symbol' => 'N/A'
        ];
    }
    
    echo json_encode($response, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
