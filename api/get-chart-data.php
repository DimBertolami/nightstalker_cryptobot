<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$coinId = $_GET['coin_id'] ?? null;

if (!$coinId) {
    echo json_encode(['success' => false, 'error' => 'Coin ID is required.']);
    exit();
}

try {
    $db = getDbConnection();

    // Fetch historical price data
    try {
        $stmt = $db->prepare("SELECT recorded_at, price FROM price_history WHERE coin_id = ? ORDER BY recorded_at ASC");
        $stmt->execute([$coinId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PDO Error fetching price history for $coinId: " . $e->getMessage());
        throw $e; // Re-throw to be caught by the outer catch block
    }

    $formattedHistory = [];
    foreach ($history as $row) {
        $formattedHistory[] = [
            'time' => strtotime($row['recorded_at']) * 1000, // Convert to milliseconds for JavaScript
            'price' => (float)$row['price']
        ];
    }

    // Fetch apex data from coin_apex_prices table
    $apexData = null;
    try {
        $stmtApex = $db->prepare("SELECT apex_price, apex_timestamp, drop_start_timestamp, status FROM coin_apex_prices WHERE coin_id = ?");
        $stmtApex->execute([$coinId]);
        $apexResult = $stmtApex->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PDO Error fetching apex data for $coinId: " . $e->getMessage());
        throw $e; // Re-throw
    }

    if ($apexResult) {
        $apexData = [
            'price' => (float)$apexResult['apex_price'],
            'timestamp' => strtotime($apexResult['apex_timestamp']) * 1000
        ];
        $coinStatus = $apexResult['status'];
        $dropStartTimestamp = $apexResult['drop_start_timestamp'] ? strtotime($apexResult['drop_start_timestamp']) * 1000 : null;
    } else {
        $coinStatus = 'not_monitored';
    }

    $purchaseTime = null;
    $latestRecordedTime = null;

    if (!empty($formattedHistory)) {
        // Assuming the first entry in history is close to purchase time for simplicity
        $purchaseTime = $formattedHistory[0]['time'];
        $latestRecordedTime = end($formattedHistory)['time'];
    }

    error_log("Chart Data - History: " . json_encode($formattedHistory));
    error_log("Chart Data - Apex: " . json_encode($apexData));
    error_log("Chart Data - Purchase Time: " . $purchaseTime);
    error_log("Chart Data - Latest Recorded Time: " . $latestRecordedTime);
    error_log("Chart Data - Coin Status: " . $coinStatus);
    error_log("Chart Data - Drop Start Timestamp: " . $dropStartTimestamp);

    echo json_encode([
        'history' => $formattedHistory,
        'apex' => $apexData,
        'purchase_time' => $purchaseTime,
        'latest_recorded_time' => $latestRecordedTime,
        'coin_status' => $coinStatus,
        'drop_start_timestamp' => $dropStartTimestamp
    ]);

} catch (PDOException $e) {
    error_log("Error fetching chart data: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error fetching chart data: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>