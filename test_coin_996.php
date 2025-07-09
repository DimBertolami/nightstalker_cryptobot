<?php
// Test script to debug coin ID 996 lookup

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Coin ID to test
$coinId = "996";

echo "<h1>Testing Coin ID: $coinId</h1>";

// Test 1: Direct database query
echo "<h2>Test 1: Direct Database Query</h2>";
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
    $stmt->execute([$coinId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<p>Found coin in portfolio:</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "<p>No coin found with direct query</p>";
    }
} catch (Exception $e) {
    echo "<p>Error with direct query: " . $e->getMessage() . "</p>";
}

// Test 2: getUserCoinBalancePDO function
echo "<h2>Test 2: getUserCoinBalancePDO Function</h2>";
try {
    $portfolioData = getUserCoinBalancePDO($coinId);
    
    if (!empty($portfolioData)) {
        echo "<p>Found coin with getUserCoinBalancePDO:</p>";
        echo "<pre>";
        print_r($portfolioData);
        echo "</pre>";
    } else {
        echo "<p>No coin found with getUserCoinBalancePDO</p>";
        
        // Debug the function
        echo "<h3>Debugging getUserCoinBalancePDO</h3>";
        
        // Check function definition
        $funcDef = new ReflectionFunction('getUserCoinBalancePDO');
        echo "<p>Function defined in: " . $funcDef->getFileName() . " on line " . $funcDef->getStartLine() . "</p>";
        
        // Try with different parameter types
        echo "<h4>Testing with different parameter types</h4>";
        
        echo "<p>Testing with string: '" . $coinId . "'</p>";
        $result = getUserCoinBalancePDO($coinId);
        echo "<pre>Result: " . print_r($result, true) . "</pre>";
        
        echo "<p>Testing with integer: " . intval($coinId) . "</p>";
        $result = getUserCoinBalancePDO(intval($coinId));
        echo "<pre>Result: " . print_r($result, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p>Error with getUserCoinBalancePDO: " . $e->getMessage() . "</p>";
}

// Test 3: executeSellPDO function
echo "<h2>Test 3: executeSellPDO Function</h2>";
try {
    // Don't actually execute the sell, just check if it can find the coin
    echo "<p>Checking if executeSellPDO can find the coin...</p>";
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
    $stmt->execute([$coinId]);
    $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($directResult) {
        echo "<p>Found coin directly in portfolio table:</p>";
        echo "<pre>";
        print_r($directResult);
        echo "</pre>";
        
        $portfolioData = [
            'amount' => (float)$directResult['amount'],
            'avg_buy_price' => (float)$directResult['avg_buy_price'],
            'coin_id' => $directResult['coin_id']
        ];
        
        echo "<p>Portfolio data that would be used by executeSellPDO:</p>";
        echo "<pre>";
        print_r($portfolioData);
        echo "</pre>";
    } else {
        echo "<p>No coin found with direct query in executeSellPDO</p>";
    }
} catch (Exception $e) {
    echo "<p>Error with executeSellPDO test: " . $e->getMessage() . "</p>";
}
