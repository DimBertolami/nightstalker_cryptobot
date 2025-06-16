<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

// This is a simplified version - in production you'd have proper authentication
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$coinId = $input['coinId'] ?? '';
$amount = $input['amount'] ?? 0;

if (empty($action) || empty($coinId) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Get current price (in a real app, this would come from exchange API)
$db = getDBConnection();
$stmt = $db->prepare("SELECT price FROM cryptocurrencies WHERE id = ?");
$stmt->bind_param('s', $coinId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Coin not found']);
    exit();
}

$coin = $result->fetch_assoc();
$price = $coin['price'];

// Simulate trade execution
if ($action === 'buy') {
    $tradeId = executeBuy($coinId, $amount, $price);
    echo json_encode([
        'success' => true,
        'message' => 'Buy order executed',
        'tradeId' => $tradeId
    ]);
} elseif ($action === 'sell') {
    // Check if amount is 0 or 'all' to sell entire balance
    if ($amount === 'all') {
        $amount = getUserCoinBalance($coinId);
    }
    
    // Execute the sell operation
    $result = executeSell($coinId, $amount, $price);
    
    // Return the result directly from executeSell
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
