<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/vendor/autoload.php';
// Get database connection
$db = getDBConnection();
if (!$db) {
    die("Database connection failed\n");
}

// Check cryptocurrencies table structure
echo "CRYPTOCURRENCIES TABLE STRUCTURE:\n";
$result = $db->query("DESCRIBE cryptocurrencies");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']} - {$row['Key']}\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}

echo "\nPRICE_HISTORY TABLE STRUCTURE:\n";
$result = $db->query("DESCRIBE price_history");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']} - {$row['Key']}\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}

// Check sample data
echo "\nSAMPLE DATA FROM CRYPTOCURRENCIES:\n";
$result = $db->query("SELECT id, symbol FROM cryptocurrencies LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']} - Symbol: {$row['symbol']}\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}

echo "\nSAMPLE DATA FROM PRICE_HISTORY:\n";
$result = $db->query("SELECT id, coin_id FROM price_history LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']} - Coin ID: {$row['coin_id']}\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}
