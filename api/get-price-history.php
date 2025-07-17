<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $symbol = $_GET['symbol'] ?? '';
    $limit = $_GET['limit'] ?? 50; // Default to 50 data points

    if (empty($symbol)) {
        throw new Exception('Coin symbol is required');
    }

    // First, get the coin_id from the coins table using the symbol
    $stmt = $db->prepare("SELECT id FROM coins WHERE symbol = ?");
    $stmt->execute([$symbol]);
    $coin_id = $stmt->fetchColumn();

    if (!$coin_id) {
        echo json_encode(['success' => false, 'message' => 'Coin not found']);
        exit();
    }

    // Fetch price history for the given coin_id
    $stmt = $db->prepare("SELECT price, recorded_at FROM price_history WHERE coin_id = ? ORDER BY recorded_at DESC LIMIT ?");
    $stmt->bindParam(1, $coin_id);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reverse the array to have oldest data first for charting
    $history = array_reverse($history);

    echo json_encode(['success' => true, 'data' => $history]);

} catch (Exception $e) {
    error_log("Error in get-price-history.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>