<?php
require_once __DIR__ . '/includes/pdo_functions.php';
require_once __DIR__ . '/includes/database.php'; // Added to include getDBConnection

function runSync() {
    echo "Starting portfolio to cryptocurrencies sync...\n";
    $result = syncPortfolioCoinsToCryptocurrenciesPDO();
    if ($result) {
        echo "Sync completed successfully.\n";
    } else {
        echo "Sync failed. Check logs for details.\n";
    }
}

runSync();
?>
