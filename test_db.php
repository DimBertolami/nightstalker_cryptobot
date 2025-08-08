<?php
// Test script to debug database connection and queries

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Test</h1>";

// Test database connection
try {
    $db = getDBConnection();
    echo "<p>Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Test direct query to portfolio table
echo "<h2>Portfolio Table Contents</h2>";
try {
    $stmt = $db->query("SELECT * FROM portfolio");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Coin ID</th><th>Amount</th><th>Avg Buy Price</th><th>Last Updated</th></tr>";
    
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['coin_id'] . "</td>";
        echo "<td>" . $row['amount'] . "</td>";
        echo "<td>" . $row['avg_buy_price'] . "</td>";
        echo "<td>" . $row['last_updated'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (Exception $e) {
    echo "<p>Error querying portfolio table: " . $e->getMessage() . "</p>";
}

// Test specific coin ID query
$coinId = "995";
echo "<h2>Testing Query for Coin ID: $coinId</h2>";

try {
    // Test 1: Direct query with string parameter
    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = :coinId");
    $stmt->bindValue(':coinId', $coinId, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Test 1: Direct Query with String Parameter</h3>";
    if ($result) {
        echo "<p>Found coin with ID $coinId:</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p>No coin found with ID $coinId using direct query with string parameter</p>";
    }
    
    // Test 2: Direct query with exact match (no parameter binding)
    $stmt = $db->query("SELECT * FROM portfolio WHERE coin_id = '$coinId'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Test 2: Direct Query with Exact Match</h3>";
    if ($result) {
        echo "<p>Found coin with ID $coinId:</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p>No coin found with ID $coinId using direct query with exact match</p>";
    }
    
    // Test 3: Using getUserCoinBalancePDO function
    echo "<h3>Test 3: Using getUserCoinBalancePDO Function</h3>";
    $portfolioData = getUserCoinBalancePDO($coinId);
    
    if (!empty($portfolioData)) {
        echo "<p>Found coin with ID $coinId using getUserCoinBalancePDO:</p>";
        echo "<pre>" . print_r($portfolioData, true) . "</pre>";
    } else {
        echo "<p>No coin found with ID $coinId using getUserCoinBalancePDO</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error testing coin ID query: " . $e->getMessage() . "</p>";
}
