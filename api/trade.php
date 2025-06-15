<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';

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
$db = connectDB();
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
    // In a real app, you'd need to know which buy trade this is selling
    $result = executeSell($coinId, $amount, $price, 1); // Using 1 as dummy buy trade ID
    echo json_encode([
        'success' => true,
        'message' => 'Sell order executed',
        'profitLoss' => $result['profit_loss'],
        'profitPercentage' => $result['profit_percentage']
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
