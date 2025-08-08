<?php
// Test script to debug trade API issues

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to simulate API call
function simulateTradeApi($action, $coinId, $amount) {
    echo "<h2>Simulating Trade API Call</h2>";
    echo "<p>Action: $action, CoinId: $coinId, Amount: $amount</p>";
    
    // Get database connection
    $db = getDBConnection();
    
    // Step 1: Check if coin exists in portfolio
    echo "<h3>Step 1: Check Portfolio</h3>";
    
    // Convert coin ID to string to ensure consistent type handling
    $coinId = (string)$coinId;
    echo "<p>Coin ID (as string): '$coinId'</p>";
    
    // Direct database query
    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
    $stmt->execute([$coinId]);
    $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Direct database query result:</p>";
    echo "<pre>";
    print_r($directResult);
    echo "</pre>";
    
    // Try getUserCoinBalancePDO function
    if (function_exists('getUserCoinBalancePDO')) {
        echo "<h3>Step 2: getUserCoinBalancePDO Function</h3>";
        
        // Debug the function call
        echo "<p>Calling getUserCoinBalancePDO('$coinId')</p>";
        
        // Get portfolio data
        $portfolioData = getUserCoinBalancePDO($coinId);
        
        echo "<p>Portfolio data returned:</p>";
        echo "<pre>";
        print_r($portfolioData);
        echo "</pre>";
        
        // Check if portfolio data is empty
        if (empty($portfolioData)) {
            echo "<p style='color:red'>ERROR: Portfolio data is empty!</p>";
            
            // Try with different parameter types
            echo "<h4>Testing with different parameter types</h4>";
            
            echo "<p>Testing with integer: " . intval($coinId) . "</p>";
            $result = getUserCoinBalancePDO(intval($coinId));
            echo "<pre>Result: " . print_r($result, true) . "</pre>";
            
            // Try case-insensitive search
            echo "<p>Testing with case-insensitive LIKE query</p>";
            $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id LIKE ?");
            $stmt->execute([$coinId]);
            $likeResult = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre>Result: " . print_r($likeResult, true) . "</pre>";
            
            // Check if coin_id column is actually a string
            echo "<p>Checking portfolio table schema</p>";
            $stmt = $db->prepare("DESCRIBE portfolio");
            $stmt->execute();
            $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            foreach ($schema as $column) {
                if ($column['Field'] === 'coin_id') {
                    echo "coin_id column type: " . $column['Type'] . "\n";
                }
            }
            echo "</pre>";
        }
    }
    
    // Step 3: Test executeSellPDO function
    if ($action === 'sell' && function_exists('executeSellPDO')) {
        echo "<h3>Step 3: executeSellPDO Function</h3>";
        
        // Don't actually execute the sell, just check the function
        echo "<p>Function definition:</p>";
        $funcDef = new ReflectionFunction('executeSellPDO');
        echo "<p>Function defined in: " . $funcDef->getFileName() . " on line " . $funcDef->getStartLine() . "</p>";
        
        // Check function parameters
        echo "<p>Function parameters:</p>";
        $params = $funcDef->getParameters();
        echo "<ul>";
        foreach ($params as $param) {
            echo "<li>" . $param->getName() . "</li>";
        }
        echo "</ul>";
        
        // Test the function with a small amount
        if (!empty($directResult)) {
            echo "<p>Testing executeSellPDO with a small amount (0.1)</p>";
            try {
                $price = 0.01; // Small test price
                $testAmount = 0.1; // Small test amount
                
                // Make sure we're not selling more than we have
                if ($directResult['amount'] < $testAmount) {
                    $testAmount = $directResult['amount'] / 2; // Half of what we have
                }
                
                echo "<p>Test parameters: coinId=$coinId, amount=$testAmount, price=$price</p>";
                
                // Call the function but don't commit the transaction
                // We'll modify the function temporarily for testing
                $db->beginTransaction();
                
                // Simulate the function call steps
                echo "<p>Simulating executeSellPDO steps:</p>";
                
                // Get portfolio data
                $portfolioData = getUserCoinBalancePDO($coinId);
                echo "<p>Portfolio data: " . json_encode($portfolioData) . "</p>";
                
                if (empty($portfolioData)) {
                    echo "<p style='color:red'>ERROR: Portfolio data is empty in executeSellPDO simulation</p>";
                    
                    // Try direct query
                    $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
                    $stmt->execute([$coinId]);
                    $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($directResult) {
                        echo "<p>Direct query found the coin:</p>";
                        echo "<pre>";
                        print_r($directResult);
                        echo "</pre>";
                        
                        $portfolioData = [
                            'amount' => (float)$directResult['amount'],
                            'avg_buy_price' => (float)$directResult['avg_buy_price'],
                            'coin_id' => $directResult['coin_id']
                        ];
                        
                        echo "<p>Created portfolio data from direct query:</p>";
                        echo "<pre>";
                        print_r($portfolioData);
                        echo "</pre>";
                    } else {
                        echo "<p style='color:red'>ERROR: Direct query also failed to find the coin</p>";
                    }
                }
                
                // Rollback any changes
                $db->rollBack();
                echo "<p>Test transaction rolled back (no actual sell performed)</p>";
                
            } catch (Exception $e) {
                $db->rollBack();
                echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color:red'>Cannot test executeSellPDO because coin was not found in portfolio</p>";
        }
    }
}

// Get coin ID from query parameter or use default
$coinId = isset($_GET['coinId']) ? $_GET['coinId'] : '996';
$action = isset($_GET['action']) ? $_GET['action'] : 'sell';
$amount = isset($_GET['amount']) ? $_GET['amount'] : '10';

echo "<h1>Trade API Test for $action of Coin ID: $coinId</h1>";

// Show all coins in portfolio
echo "<h2>All Coins in Portfolio</h2>";
try {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM portfolio");
    $allCoins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($allCoins) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Coin ID</th><th>Amount</th><th>Avg Buy Price</th><th>Last Updated</th></tr>";
        
        foreach ($allCoins as $coin) {
            echo "<tr>";
            echo "<td>" . $coin['id'] . "</td>";
            echo "<td>" . $coin['user_id'] . "</td>";
            echo "<td>" . $coin['coin_id'] . "</td>";
            echo "<td>" . $coin['amount'] . "</td>";
            echo "<td>" . $coin['avg_buy_price'] . "</td>";
            echo "<td>" . $coin['last_updated'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No coins found in portfolio</p>";
    }
} catch (Exception $e) {
    echo "<p>Error retrieving portfolio: " . $e->getMessage() . "</p>";
}

// Run the simulation
simulateTradeApi($action, $coinId, $amount);
