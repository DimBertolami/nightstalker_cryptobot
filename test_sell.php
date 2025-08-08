<?php
// Test script to debug sell functionality

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get coin ID from query parameter or use default
$coinId = isset($_GET['coinId']) ? $_GET['coinId'] : '995';
$amount = isset($_GET['amount']) ? $_GET['amount'] : 'all';
$price = isset($_GET['price']) ? $_GET['price'] : 0.02;

echo "<h1>Sell Test for Coin ID: $coinId</h1>";

// Step 1: Check if the coin exists in the portfolio
echo "<h2>Step 1: Check Portfolio</h2>";
try {
    // Connect to database
    $db = getDBConnection();
    if (!$db) {
        echo "<p>Database connection failed</p>";
        exit;
    }
    
    // Direct database query
    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
    $stmt->execute([$coinId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<p>Found coin in portfolio:</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // If amount is 'all', set it to the available balance
        if ($amount === 'all') {
            $amount = $result['amount'];
            echo "<p>Setting amount to full balance: $amount</p>";
        }
    } else {
        echo "<p>No coin found with ID $coinId in portfolio table</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p>Error checking portfolio: " . $e->getMessage() . "</p>";
    exit;
}

// Step 2: Test executeSellPDO function
echo "<h2>Step 2: Test executeSellPDO</h2>";
try {
    if (function_exists('executeSellPDO')) {
        echo "<p>Calling executeSellPDO($coinId, $amount, $price)</p>";
        $result = executeSellPDO($coinId, $amount, $price);
        echo "<p>Result:</p>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "<p>executeSellPDO function does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p>Error executing sell: " . $e->getMessage() . "</p>";
}

// Step 3: Manual sell implementation
echo "<h2>Step 3: Manual Sell Implementation</h2>";
try {
    // Get user's current balance
    $stmt = $db->prepare("SELECT amount, avg_buy_price FROM portfolio WHERE coin_id = ?");
    $stmt->execute([$coinId]);
    $portfolioData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$portfolioData) {
        echo "<p>Could not find coin in portfolio</p>";
        exit;
    }
    
    $userBalance = $portfolioData['amount'];
    $avgBuyPrice = $portfolioData['avg_buy_price'];
    
    echo "<p>User balance: $userBalance</p>";
    echo "<p>Average buy price: $avgBuyPrice</p>";
    
    // Calculate profit/loss
    $sellTotal = $amount * $price;
    $buyTotal = $amount * $avgBuyPrice;
    $profitLoss = $sellTotal - $buyTotal;
    $profitPercentage = ($buyTotal > 0) ? (($sellTotal - $buyTotal) / $buyTotal) * 100 : 0;
    
    echo "<p>Sell total: $sellTotal</p>";
    echo "<p>Buy total: $buyTotal</p>";
    echo "<p>Profit/Loss: $profitLoss</p>";
    echo "<p>Profit percentage: $profitPercentage%</p>";
    
    // Update portfolio balance
    $newBalance = $userBalance - $amount;
    
    echo "<p>New balance would be: $newBalance</p>";
    
    if ($newBalance <= 0) {
        echo "<p>Would remove coin from portfolio (balance would be zero or negative)</p>";
    } else {
        echo "<p>Would update portfolio with new balance</p>";
    }
    
    // Record the trade
    echo "<p>Would record trade in trades table</p>";
    
} catch (Exception $e) {
    echo "<p>Error in manual sell: " . $e->getMessage() . "</p>";
}
