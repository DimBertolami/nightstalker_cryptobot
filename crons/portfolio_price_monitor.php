<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/cmc_utils.php'; // Assuming this has price fetching logic

// Set to run indefinitely
set_time_limit(0);

function get_portfolio_symbols($db) {
    $stmt = $db->query("SELECT DISTINCT c.symbol FROM portfolio p JOIN coins c ON p.coin_id = c.id WHERE p.amount > 0");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function fetch_price_from_binance($symbol) {
    // Placeholder for actual Binance API call
    // In a real implementation, you would use the Binance API library
    // For now, we'll simulate a price fetch
    return fetch_coin_price_from_cmc($symbol); // Using CMC as a stand-in
}

function store_price_history($db, $coin_id, $price) {
    $stmt = $db->prepare("INSERT INTO price_history (coin_id, price) VALUES (?, ?)");
    $stmt->execute([$coin_id, $price]);
}

function get_coin_id_by_symbol($db, $symbol) {
    $stmt = $db->prepare("SELECT id FROM coins WHERE symbol = ?");
    $stmt->execute([$symbol]);
    return $stmt->fetchColumn();
}

$db = getDBConnection();
if (!$db) {
    die("Database connection failed");
}

echo "Starting portfolio price monitor...\n";

while (true) {
    $symbols = get_portfolio_symbols($db);
    if (empty($symbols)) {
        echo "Portfolio is empty. Waiting...\n";
        sleep(60); // Wait longer if portfolio is empty
        continue;
    }

    foreach ($symbols as $symbol) {
        try {
            $priceData = fetch_price_from_binance($symbol);
            if ($priceData['success'] && isset($priceData['price'])) {
                $price = $priceData['price'];
                $coin_id = get_coin_id_by_symbol($db, $symbol);
                if ($coin_id) {
                    store_price_history($db, $coin_id, $price);
                    echo date('Y-m-d H:i:s') . ": Stored price for {$symbol}: {$price}\n";
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching price for {$symbol}: " . $e->getMessage());
        }
        sleep(1); // Small delay between symbols
    }

    echo "Completed a cycle. Waiting 3 seconds...\n";
    sleep(3); // As defined in settings
}
