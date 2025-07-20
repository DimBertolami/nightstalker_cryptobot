<?php
/**
 * Public Price Updates API Endpoint
 * Returns latest price updates for portfolio coins without requiring authentication
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enable for debugging
ob_start();

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../includes/database.php';
    require_once __DIR__ . '/../../includes/pdo_functions.php';

    header('Content-Type: application/json');

    $exchange = $_GET['exchange'] ?? 'binance';

    $db = getDbConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Get distinct coin_ids from price_history
    $stmt = $db->prepare("SELECT DISTINCT coin_id FROM price_history");
    $stmt->execute();
    $availableCoins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($availableCoins)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No price history data available'
        ]);
        exit;
    }

    $priceUpdates = [];

    foreach ($availableCoins as $coinId) {
        $stmt = $db->prepare("
            SELECT price, recorded_at
            FROM price_history
            WHERE coin_id = ?
            ORDER BY recorded_at DESC
            LIMIT 1
        ");
        $stmt->execute([$coinId]);
        $latestData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$latestData) {
            continue;
        }

        $currentPrice = (float)$latestData['price'];

        // Add coin price update data
        $priceUpdates[] = [
            'symbol' => $coinId,
            'current_price' => $currentPrice,
            'recorded_at' => $latestData['recorded_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $priceUpdates,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log("Public price updates API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving price updates: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>
