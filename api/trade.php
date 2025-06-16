<?php
// Set JSON content type header
header('Content-Type: application/json');

// Suppress all errors - this is critical to prevent HTML errors from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// This is a simplified version - in production you'd have proper authentication
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$coinId = $input['coinId'] ?? '';
$amount = $input['amount'] ?? 0;

// Special validation for sell action with 'all' amount
if ($action === 'sell' && $amount === 'all') {
    // This is valid, we'll handle it later
} else if (empty($action) || empty($coinId) || $amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

// Get current price (in a real app, this would come from exchange API)
$db = getDBConnection();
$stmt = $db->prepare("SELECT price FROM cryptocurrencies WHERE id = ?");
$stmt->bind_param('s', $coinId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Coin not found'
    ]);
    exit;
}

$coin = $result->fetch_assoc();
$price = $coin['price'];

// Clean any output that might have been generated before this point
ob_clean();

// Simulate trade execution
if ($action === 'buy') {
    $tradeId = executeBuy($coinId, $amount, $price);
    
    if ($tradeId === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to execute buy order'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Buy order executed',
            'tradeId' => $tradeId
        ]);
    }
    
    // End output buffering and flush
    ob_end_flush();
    exit;
} elseif ($action === 'sell') {
    // Get user's current balance for this coin
    $userBalance = getUserCoinBalance($coinId);
    
    // Check if user has any coins to sell
    if ($userBalance <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You don\'t have any coins to sell.'
        ]);
        exit;
    }
    
    // Check if amount is 0 or 'all' to sell entire balance
    if ($amount === 'all') {
        $amount = $userBalance;
    }
    
    // Make sure user isn't trying to sell more than they have
    if ($amount > $userBalance) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "You can't sell more than you own. Your balance: {$userBalance}"
        ]);
        exit;
    }
    
    // Execute the sell operation
    $result = executeSell($coinId, $amount, $price);
    
    // Clean any output that might have been generated
    ob_clean();
    
    // Return the result directly from executeSell
    echo json_encode($result);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
} else {
    // Clean any output that might have been generated
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
}