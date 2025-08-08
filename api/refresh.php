<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

// This would be replaced with actual CoinGecko API calls
// For now, we'll just return the current database state

$db = getDBConnection();

// Get all coins
$coins = $db->query("
    SELECT * FROM cryptocurrencies 
    WHERE age_hours <= " . MAX_COIN_AGE . " 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get trending coins
$trending = $db->query("
    SELECT * FROM cryptocurrencies 
    WHERE volume >= " . MIN_VOLUME_THRESHOLD . " 
    AND is_trending = TRUE 
    ORDER BY volume DESC
")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'coins' => $coins,
    'trending' => $trending,
    'last_updated' => date('Y-m-d H:i:s')
]);
