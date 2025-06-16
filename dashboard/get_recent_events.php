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

// Get recent events
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$strategy = isset($_GET['strategy']) ? $_GET['strategy'] : 'main_strategy';

try {
    $events = $logger->getRecentEvents($strategy, $limit);
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
