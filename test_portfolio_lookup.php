<?php
// Debug script to test portfolio lookup for specific coin IDs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Function to test portfolio lookup with different methods
function testCoinLookup($coinId) {
    echo "<h2>Testing lookup for coin ID: $coinId</h2>";
    
    // Method 1: Direct database query
    echo "<h3>Method 1: Direct database query</h3>";
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
        $stmt->execute([$coinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        if ($result) {
            echo "FOUND: " . print_r($result, true);
        } else {
            echo "NOT FOUND";
            
            // Try with different user_id values
            echo "\n\nTrying with different user_id values:\n";
            $stmt = $db->query("SELECT DISTINCT user_id FROM portfolio");
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($userIds as $userId) {
                $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ? AND user_id = ?");
                $stmt->execute([$coinId, $userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "User ID $userId: ";
                if ($result) {
                    echo "FOUND\n";
                } else {
                    echo "NOT FOUND\n";
                }
            }
        }
        echo "</pre>";
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
    }
    
    // Method 2: getUserCoinBalancePDO function
    echo "<h3>Method 2: getUserCoinBalancePDO function</h3>";
    try {
        if (function_exists('getUserCoinBalancePDO')) {
            $result = getUserCoinBalancePDO($coinId);
            
            echo "<pre>";
            if (!empty($result)) {
                echo "FOUND: " . print_r($result, true);
            } else {
                echo "NOT FOUND";
            }
            echo "</pre>";
        } else {
            echo "<p style='color:red'>Function getUserCoinBalancePDO not found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
    }
    
    // Method 3: Check portfolio table structure
    echo "<h3>Method 3: Portfolio table structure</h3>";
    try {
        $db = getDBConnection();
        $stmt = $db->query("DESCRIBE portfolio");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
    }
    
    // Method 4: List all coins in portfolio
    echo "<h3>Method 4: All coins in portfolio</h3>";
    try {
        $db = getDBConnection();
        $stmt = $db->query("SELECT * FROM portfolio LIMIT 10");
        $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($coins);
        echo "</pre>";
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
    }
}

// Get coin ID from query parameter or use default
$coinId = isset($_GET['coinId']) ? $_GET['coinId'] : '1009';

// Test with the specified coin ID
testCoinLookup($coinId);

// Show form to test other coin IDs
echo "<hr>";
echo "<form method='get'>";
echo "<label for='coinId'>Test another coin ID:</label>";
echo "<input type='text' name='coinId' id='coinId' value='$coinId'>";
echo "<button type='submit'>Test</button>";
echo "</form>";

// Quick links to test common coin IDs
echo "<hr>";
echo "<h3>Quick links:</h3>";
echo "<ul>";
$testIds = ['996', '995', '1009', '1017', '1021', '982'];
foreach ($testIds as $id) {
    echo "<li><a href='?coinId=$id'>Test coin ID: $id</a></li>";
}
echo "</ul>";
?>
