<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

$coinId = $_GET['id'] ?? '';
if (empty($coinId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Coin ID is required']);
    exit();
}

$db = connectDB();
$stmt = $db->prepare("SELECT * FROM cryptocurrencies WHERE id = ?");
$stmt->bind_param('s', $coinId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Coin not found']);
    exit();
}

$coin = $result->fetch_assoc();

// Get price history
$stmt = $db->prepare("
    SELECT * FROM price_history 
    WHERE coin_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT 24
");
$stmt->bind_param('s', $coinId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'coin' => $coin,
    'history' => $history
]);
