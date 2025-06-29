<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Order.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'timestamp' => time()
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['symbol', 'side', 'type', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: {$field}",
            'timestamp' => time()
        ]);
        exit;
    }
}

// Get exchange from input or use default
$exchangeId = $input['exchange'] ?? 'binance';

try {
    // Initialize order service
    $orderService = new Order($exchangeId);
    
    // Create order
    $order = $orderService->create([
        'symbol' => strtoupper($input['symbol']),
        'side' => strtolower($input['side']),
        'type' => strtolower($input['type']),
        'amount' => (float)$input['amount'],
        'price' => isset($input['price']) ? (float)$input['price'] : null,
        'stopPrice' => isset($input['stopPrice']) ? (float)$input['stopPrice'] : null,
        'params' => $input['params'] ?? []
    ]);
    
    echo json_encode([
        'success' => true,
        'order' => $order,
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
