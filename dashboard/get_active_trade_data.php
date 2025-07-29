<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pdo_functions.php';

$response = [
    'success' => false,
    'message' => 'No active trade found',
    'current_price' => 'N/A',
    'profit_percentage' => '0.00%',
    'raw_profit_percentage' => 0,
    'holding_time' => '0s',
    'last_update' => 'N/A',
    'symbol' => 'N/A'
];

try {
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Find the coin currently being monitored
    $stmt = $db->prepare("SELECT coin_id, apex_price, apex_timestamp, drop_start_timestamp, status, last_checked FROM coin_apex_prices WHERE status = 'monitoring' ORDER BY last_checked DESC LIMIT 1");
    $stmt->execute();
    $monitoredCoin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($monitoredCoin) {
        $coinId = $monitoredCoin['coin_id'];
        $apexPrice = (float)$monitoredCoin['apex_price'];
        $apexTimestamp = $monitoredCoin['apex_timestamp'];
        $lastChecked = $monitoredCoin['last_checked'];

        // Get current price for the coin
        $coinData = getCoinDataPDO($coinId);
        $currentPrice = $coinData['price'] ?? null;

        if ($currentPrice !== null) {
            // Get portfolio data for avg_buy_price
            $portfolioData = getUserCoinBalancePDO($coinId);
            $avgBuyPrice = $portfolioData['avg_buy_price'] ?? $apexPrice; // Fallback to apex price if no portfolio entry

            $profitLoss = ($currentPrice - $avgBuyPrice);
            $profitPercentage = ($avgBuyPrice > 0) ? ($profitLoss / $avgBuyPrice) * 100 : 0;

            // Calculate holding time
            $holdingTime = 'N/A';
            if ($apexTimestamp) {
                $start = new DateTime($apexTimestamp);
                $now = new DateTime();
                $interval = $now->diff($start);
                
                $holdingTimeParts = [];
                if ($interval->y > 0) $holdingTimeParts[] = $interval->y . 'y';
                if ($interval->m > 0) $holdingTimeParts[] = $interval->m . 'm';
                if ($interval->d > 0) $holdingTimeParts[] = $interval->d . 'd';
                if ($interval->h > 0) $holdingTimeParts[] = $interval->h . 'h';
                if ($interval->i > 0) $holdingTimeParts[] = $interval->i . 'm';
                if ($interval->s > 0) $holdingTimeParts[] = $interval->s . 's';
                
                $holdingTime = empty($holdingTimeParts) ? '0s' : implode(' ', array_slice($holdingTimeParts, 0, 2)); // Show up to 2 largest units
            }

            $response = [
                'success' => true,
                'message' => 'Active trade found',
                'current_price' => number_format($currentPrice, 8),
                'profit_percentage' => sprintf('%.2f%%', $profitPercentage),
                'raw_profit_percentage' => $profitPercentage,
                'holding_time' => $holdingTime,
                'last_update' => (new DateTime($lastChecked))->format('H:i:s'),
                'symbol' => $coinId, // Use coin_id as symbol for now
                'apex_price' => number_format($apexPrice, 8), // Add apex_price
                'drop_start_timestamp' => $monitoredCoin['drop_start_timestamp'] // Add drop_start_timestamp
            ];
        } else {
            $response['message'] = "Monitored coin ($coinId) price not found.";
        }
    }

} catch (Exception $e) {
    error_log("Error in get_active_trade_data.php: " . $e->getMessage());
    $response['message'] = "Server error: " . $e->getMessage();
}

echo json_encode($response);
?>