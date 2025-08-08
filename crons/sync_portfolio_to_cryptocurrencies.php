<?php
require_once  '/opt/lampp/htdocs/NS/includes/pdo_functions.php';
require_once  '/opt/lampp/htdocs/NS/includes/database.php'; // Added to include getDBConnection
require_once  '/opt/lampp/htdocs/NS/vendor/autoload.php';
function runSync() {
    echo "Starting portfolio to cryptocurrencies sync...\n";
    try {
        $db = getDBConnection();
        if (!$db) {
            echo "Database connection failed.\n";
            return false;
        }

        // Fetch portfolio coins with avg_buy_price
        $query = "SELECT coin_id, avg_buy_price FROM portfolio";
        $stmt = $db->query($query);
        if (!$stmt) {
            echo "Failed to query portfolio: " . $db->errorInfo()[2] . "\n";
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $coinId = trim($row['coin_id']);
            $avgBuyPrice = $row['avg_buy_price'] ?? 0;

            echo "Processing coinId: $coinId with avgBuyPrice: $avgBuyPrice\n";

            // Update price in cryptocurrencies table
            $updateStmt = $db->prepare("UPDATE cryptocurrencies SET price = ?, last_updated = NOW() WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->execute([$avgBuyPrice, $coinId]);
                echo "Updated price for $coinId to $avgBuyPrice\n";
            } else {
                echo "Failed to prepare update statement for $coinId\n";
            }
        }

        echo "Sync completed successfully.\n";
        return true;
    } catch (Exception $e) {
        echo "Exception during sync: " . $e->getMessage() . "\n";
        error_log("Exception during syncPortfolioCoinsToCryptocurrenciesPDO: " . $e->getMessage());
        return false;
    }
}

echo "Debug logs from syncPortfolioCoinsToCryptocurrenciesPDO:\n";
if (function_exists('getSyncDebugLogs')) {
    $logs = getSyncDebugLogs();
    foreach ($logs as $log) {
        echo $log . "\n";
    }
}

runSync();
?>
