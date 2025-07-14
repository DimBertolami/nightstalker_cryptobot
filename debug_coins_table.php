<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Coins Table Debug</h1>";

try {
    $db = getDBConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check table structure
    echo "<h2>Table Structure</h2>";
    $tableInfo = $db->query("DESCRIBE coins");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $tableInfo->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count total coins
    $countStmt = $db->query("SELECT COUNT(*) as count FROM coins");
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h2>Total Coins: $totalCount</h2>";
    
    // Check coins with price > 0
    $priceStmt = $db->query("SELECT COUNT(*) as count FROM coins WHERE price > 0");
    $priceCount = $priceStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>Coins with price > 0: $priceCount</h3>";
    
    // Check coins with current_price > 0
    $currentPriceStmt = $db->query("SELECT COUNT(*) as count FROM coins WHERE current_price > 0");
    $currentPriceCount = $currentPriceStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>Coins with current_price > 0: $currentPriceCount</h3>";
    
    // Show sample coins
    echo "<h2>Sample Coins (10 most recent)</h2>";
    $sampleStmt = $db->query("SELECT id, coin_name, symbol, price, current_price, last_updated FROM coins ORDER BY id DESC LIMIT 10");
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Symbol</th><th>Price</th><th>Current Price</th><th>Last Updated</th></tr>";
    while ($row = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['coin_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['symbol']) . "</td>";
        echo "<td>" . htmlspecialchars($row['price']) . "</td>";
        echo "<td>" . htmlspecialchars($row['current_price']) . "</td>";
        echo "<td>" . htmlspecialchars($row['last_updated']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if the binanceFromCMC4NS.py script has been run recently
    echo "<h2>Last Binance Update</h2>";
    $lastUpdateStmt = $db->query("SELECT MAX(last_updated) as last_update FROM coins WHERE exchange_id = (SELECT id FROM exchanges WHERE exchange_name = 'Binance' LIMIT 1)");
    $lastUpdate = $lastUpdateStmt->fetch(PDO::FETCH_ASSOC)['last_update'];
    echo "<p>Last Binance update: " . ($lastUpdate ?: 'Never') . "</p>";
    
} catch (PDOException $e) {
    echo "<h2>Database Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<h2>General Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
