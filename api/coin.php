<?php
// Set JSON content type header
header('Content-Type: application/json');

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

$coinId = $_GET['id'] ?? '';
if (empty($coinId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Coin ID is required'
    ]);
    exit;
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM cryptocurrencies WHERE id = ?");
$stmt->execute([$coinId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Coin not found'
    ]);
    exit;
}

$coin = $result;

// Get price history
$stmt = $db->prepare("
    SELECT * FROM price_history 
    WHERE coin_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 24
");
$stmt->execute([$coinId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'coin' => $coin,
    'history' => $history
]);
exit;
