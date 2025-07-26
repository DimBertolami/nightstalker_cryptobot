<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$coinId = $_GET['coin_id'] ?? null;

if (!$coinId) {
    echo json_encode(['error' => 'Coin ID is required.']);
    exit();
}

try {
    $db = getDbConnection();

    // Fetch historical price data
    $stmt = $db->prepare("SELECT recorded_at, price FROM price_history WHERE coin_id = ? ORDER BY recorded_at ASC");
    $stmt->execute([$coinId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedHistory = [];
    foreach ($history as $row) {
        $formattedHistory[] = [
            'time' => strtotime($row['recorded_at']) * 1000, // Convert to milliseconds for JavaScript
            'price' => (float)$row['price']
        ];
    }

    // Placeholder for apex, purchase_time, etc. - these would typically come from other tables or calculations
    $apex = null;
    $purchaseTime = null;
    $latestRecordedTime = null;
    $coinStatus = 'active'; // or 'sold'
    $dropStartTimestamp = null;

    // Example: If you had a way to determine apex from history
    if (!empty($formattedHistory)) {
        $maxPrice = 0;
        $apexTimestamp = 0;
        foreach ($formattedHistory as $point) {
            if ($point['price'] > $maxPrice) {
                $maxPrice = $point['price'];
                $apexTimestamp = $point['time'];
            }
        }
        $apex = [
            'price' => $maxPrice,
            'timestamp' => $apexTimestamp
        ];
        $latestRecordedTime = end($formattedHistory)['time'];
    }

    echo json_encode([
        'history' => $formattedHistory,
        'apex' => $apex,
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