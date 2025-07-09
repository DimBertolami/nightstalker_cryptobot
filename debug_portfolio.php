<?php
// Debug script to investigate portfolio lookup issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Function to test portfolio lookup
function testPortfolioLookup($coinId) {
    echo "<h3>Testing portfolio lookup for coin ID: $coinId</h3>";
    
    // Method 1: Direct database query
    echo "<h4>Method 1: Direct database query</h4>";
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
        $stmt->execute([$coinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "<p style='color:green'>SUCCESS: Found coin in portfolio via direct query</p>";
            echo "<pre>" . print_r($result, true) . "</pre>";
        } else {
            echo "<p style='color:red'>FAILED: Coin not found in portfolio via direct query</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
    }
    
    // Method 2: getUserCoinBalancePDO function
    echo "<h4>Method 2: getUserCoinBalancePDO function</h4>";
    try {
        if (function_exists('getUserCoinBalancePDO')) {
            $portfolioData = getUserCoinBalancePDO($coinId);
            
            if (!empty($portfolioData)) {
                echo "<p style='color:green'>SUCCESS: Found coin in portfolio via getUserCoinBalancePDO</p>";
                echo "<pre>" . print_r($portfolioData, true) . "</pre>";
            } else {
                echo "<p style='color:red'>FAILED: Coin not found in portfolio via getUserCoinBalancePDO</p>";
                
                // Try with different parameter types
                echo "<h5>Testing with different parameter types</h5>";
                
                // Test with integer
                $intCoinId = intval($coinId);
                echo "<p>Testing with integer: $intCoinId</p>";
                $portfolioData = getUserCoinBalancePDO($intCoinId);
                if (!empty($portfolioData)) {
                    echo "<p style='color:green'>SUCCESS with integer type</p>";
                    echo "<pre>" . print_r($portfolioData, true) . "</pre>";
                } else {
                    echo "<p style='color:red'>FAILED with integer type</p>";
                }
                
                // Test with string with quotes
                $quotedCoinId = "'$coinId'";
                echo "<p>Testing with quoted string: $quotedCoinId</p>";
                $portfolioData = getUserCoinBalancePDO(trim($quotedCoinId, "'"));
                if (!empty($portfolioData)) {
                    echo "<p style='color:green'>SUCCESS with quoted string</p>";
                    echo "<pre>" . print_r($portfolioData, true) . "</pre>";
                } else {
                    echo "<p style='color:red'>FAILED with quoted string</p>";
                }
            }
        } else {
            echo "<p style='color:red'>ERROR: getUserCoinBalancePDO function not found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR in getUserCoinBalancePDO: " . $e->getMessage() . "</p>";
    }
    
    // Method 3: Simulate trade.php sell action
    echo "<h4>Method 3: Simulate trade.php sell action</h4>";
    try {
        // Get database connection
        $db = getDBConnection();
        
        // Convert coin ID to string
        $coinId = (string)$coinId;
        echo "<p>Coin ID as string: '$coinId'</p>";
        
        // Get portfolio data
        $portfolioData = null;
        if (function_exists('getUserCoinBalancePDO')) {
            $portfolioData = getUserCoinBalancePDO($coinId);
            echo "<p>Portfolio data from getUserCoinBalancePDO:</p>";
            echo "<pre>" . print_r($portfolioData, true) . "</pre>";
        }
        
        // If portfolio data is empty, try direct query
        if (empty($portfolioData)) {
            echo "<p>Portfolio data is empty, trying direct query</p>";
            
            $stmt = $db->prepare("SELECT * FROM portfolio WHERE coin_id = ?");
            $stmt->execute([$coinId]);
            $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<p>Direct query result:</p>";
            echo "<pre>" . print_r($directResult, true) . "</pre>";
            
            if ($directResult) {
                echo "<p style='color:green'>SUCCESS: Found coin via direct query</p>";
                $portfolioData = [
                    'amount' => (float)$directResult['amount'],
                    'avg_buy_price' => (float)$directResult['avg_buy_price'],
                    'coin_id' => $directResult['coin_id']
                ];
                echo "<p>Created portfolio data:</p>";
                echo "<pre>" . print_r($portfolioData, true) . "</pre>";
            } else {
                echo "<p style='color:red'>FAILED: Coin not found via direct query</p>";
            }
        }
        
        // Check if we have portfolio data now
        if (!empty($portfolioData)) {
            $userBalance = isset($portfolioData['amount']) ? $portfolioData['amount'] : 0;
            echo "<p>User balance: $userBalance</p>";
            
            if ($userBalance > 0) {
                echo "<p style='color:green'>SUCCESS: User has balance for this coin</p>";
                
                // Test executeSellPDO function
                if (function_exists('executeSellPDO')) {
                    echo "<h5>Testing executeSellPDO function (simulation only)</h5>";
                    echo "<p>Function exists but not executing to avoid actual selling</p>";
                } else {
                    echo "<p style='color:red'>executeSellPDO function not found</p>";
                }
            } else {
                echo "<p style='color:red'>FAILED: User has no balance for this coin</p>";
            }
        } else {
            echo "<p style='color:red'>FAILED: Could not get portfolio data</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR in simulation: " . $e->getMessage() . "</p>";
    }
    
    // Check portfolio table schema
    echo "<h4>Portfolio Table Schema</h4>";
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DESCRIBE portfolio");
        $stmt->execute();
        $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($schema as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR getting schema: " . $e->getMessage() . "</p>";
    }
    
    // Show all coins in portfolio
    echo "<h4>All Coins in Portfolio</h4>";
    try {
        $db = getDBConnection();
        $stmt = $db->query("SELECT * FROM portfolio");
        $allCoins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($allCoins) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>User ID</th><th>Coin ID</th><th>Amount</th><th>Avg Buy Price</th><th>Last Updated</th></tr>";
            
            foreach ($allCoins as $coin) {
                $highlightStyle = ($coin['coin_id'] == $coinId) ? "background-color: #ffff99;" : "";
                
                echo "<tr style='$highlightStyle'>";
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
        echo "<p style='color:red'>ERROR retrieving portfolio: " . $e->getMessage() . "</p>";
    }
}

// Get coin ID from query parameter or use default
$coinId = isset($_GET['coinId']) ? $_GET['coinId'] : '996';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2, h3, h4, h5 { margin-top: 20px; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 8px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Portfolio Debug Tool</h1>
    
    <form method="get">
        <label for="coinId">Coin ID:</label>
        <input type="text" id="coinId" name="coinId" value="<?php echo htmlspecialchars($coinId); ?>">
        <button type="submit">Test</button>
    </form>
    
    <hr>
    
    <?php testPortfolioLookup($coinId); ?>
    
    <hr>
    
    <h3>Test Other Coin IDs</h3>
    <ul>
        <?php
        $testCoinIds = ['996', '995', '999', '1009', '1017', '1021', '982', 'bitcoin'];
        foreach ($testCoinIds as $testId) {
            echo "<li><a href='?coinId=" . urlencode($testId) . "'>Test Coin ID: $testId</a></li>";
        }
        ?>
    </ul>
</body>
</html>
