<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/vendor/autoload.php';
// Get database connection
$db = getDBConnection();
if (!$db) {
    die("Database connection failed\n");
}

// Check cryptocurrencies table
echo "CRYPTOCURRENCIES TABLE DATA:\n";
$result = $db->query("SELECT id, symbol, name FROM cryptocurrencies ORDER BY symbol LIMIT 20");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']} - Symbol: {$row['symbol']} - Name: {$row['name']}\n";
        
        // Calculate what the MD5 would be
        $md5_id = md5($row['symbol']);
        echo "  MD5 of symbol would be: $md5_id\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}

// Check if any price history records exist
echo "\nPRICE_HISTORY COUNT:\n";
$result = $db->query("SELECT COUNT(*) as count FROM price_history");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total records: {$row['count']}\n";
} else {
    echo "Error: " . $db->error . "\n";
}

// Check if our update query is working
echo "\nTESTING UPDATE QUERY:\n";
$symbol = "BTC";
$crypto_id = md5($symbol);
echo "Symbol: $symbol, Generated ID: $crypto_id\n";

// Try to update the cryptocurrencies table with this ID
$stmt = $db->prepare("INSERT INTO cryptocurrencies 
    (id, symbol, name, price, price_change_24h, market_cap, volume, last_updated, created_at) 
    VALUES (?, ?, ?, 0, 0, 0, 0, NOW(), NOW()) 
    ON DUPLICATE KEY UPDATE 
        name=VALUES(name), 
        price=VALUES(price), 
        price_change_24h=VALUES(price_change_24h), 
        market_cap=VALUES(market_cap), 
        volume=VALUES(volume), 
        last_updated=NOW()");

if (!$stmt) {
    echo "Prepare failed: " . $db->error . "\n";
    exit;
}

$name = "Bitcoin";
$stmt->bind_param('sss', $crypto_id, $symbol, $name);

if ($stmt->execute()) {
    echo "Update successful\n";
} else {
    echo "Update failed: " . $stmt->error . "\n";
}

// Now check if we can insert into price_history
echo "\nTESTING PRICE_HISTORY INSERT:\n";
$price = 50000;
$volume = 1000000;
$market_cap = 1000000000;

$stmt = $db->prepare("INSERT INTO price_history 
    (coin_id, price, volume, market_cap) 
    VALUES (?, ?, ?, ?)");

if (!$stmt) {
    echo "Prepare failed: " . $db->error . "\n";
    exit;
}

$stmt->bind_param('sddd', $crypto_id, $price, $volume, $market_cap);

if ($stmt->execute()) {
    echo "Insert successful\n";
} else {
    echo "Insert failed: " . $stmt->error . "\n";
}
