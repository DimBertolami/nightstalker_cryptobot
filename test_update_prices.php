<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    $db = connectDB();
    
    // Test getting current prices
    $coins = $db->query("SELECT symbol, price FROM cryptocurrencies LIMIT 5");
    echo "Current Prices:\n";
    while ($coin = $coins->fetch_assoc()) {
        echo "{$coin['symbol']}: {$coin['price']}\n";
    }
    
    // Test price history
    $history = $db->query("
        SELECT c.symbol, ph.price, ph.recorded_at 
        FROM price_history ph
        JOIN cryptocurrencies c ON ph.coin_id = c.id
        ORDER BY ph.recorded_at DESC LIMIT 5
    ");
    
    echo "\nRecent Price History:\n";
    while ($entry = $history->fetch_assoc()) {
        echo "{$entry['symbol']} @ {$entry['recorded_at']}: {$entry['price']}\n";
    }
    
    closeDB($db);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
