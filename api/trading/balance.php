<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Wallet.php';

// Get exchange from query parameter or use default
$exchangeId = $_GET['exchange'] ?? 'binance';
$forceUpdate = isset($_GET['force_update']) && $_GET['force_update'] === 'true';

try {
    // Initialize wallet
    $wallet = new Wallet($exchangeId);
    
    // Get all balances
    $balances = $wallet->getAllBalances($forceUpdate);
    
    // Filter out zero balances if requested
    if (empty($_GET['show_zero']) || $_GET['show_zero'] !== 'true') {
        $balances = array_filter($balances, function($balance) {
            return $balance['total_balance'] > 0;
        });
    }
    
    echo json_encode([
        'success' => true,
        'exchange' => $exchangeId,
        'balances' => array_values($balances),
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
