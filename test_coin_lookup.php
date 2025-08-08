<?php
// Simple test script to debug coin ID lookup

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get coin ID from query parameter or use default
$coinId = isset($_GET['coinId']) ? $_GET['coinId'] : '995';

// Connect to database
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful<br>";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test direct query
echo "<h2>Testing direct query for coin ID: $coinId</h2>";
try {
    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
    $stmt->execute([$coinId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Found coin with direct query:<br>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "No coin found with direct query<br>";
    }
} catch (Exception $e) {
    echo "Error with direct query: " . $e->getMessage() . "<br>";
}

// Test getUserCoinBalancePDO function
echo "<h2>Testing getUserCoinBalancePDO for coin ID: $coinId</h2>";
try {
    $portfolioData = getUserCoinBalancePDO($coinId);
    
    if (!empty($portfolioData)) {
        echo "Found coin with getUserCoinBalancePDO:<br>";
        echo "<pre>";
        print_r($portfolioData);
        echo "</pre>";
    } else {
        echo "No coin found with getUserCoinBalancePDO<br>";
    }
} catch (Exception $e) {
    echo "Error with getUserCoinBalancePDO: " . $e->getMessage() . "<br>";
}

// Show all portfolio entries
echo "<h2>All portfolio entries</h2>";
try {
    $stmt = $db->query("SELECT * FROM portfolio");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Coin ID</th><th>Amount</th></tr>";
    
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['coin_id'] . "</td>";
        echo "<td>" . $row['amount'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (Exception $e) {
    echo "Error querying all portfolio entries: " . $e->getMessage() . "<br>";
}
