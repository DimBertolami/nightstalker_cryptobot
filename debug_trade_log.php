<?php
require_once __DIR__ . '/includes/config.php';
#require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdo_functions.php';
require_once __DIR__ . '/includes/database.php';

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

try {
    // 1. Test Database Connection
    echo "<h2>1. Testing Database Connection...</h2>";
    $db = getDBConnection();
    if ($db) {
        echo "<p style='color:green;'>Database connection successful.</p>";
    } else {
        echo "<p style='color:red;'>Database connection failed. Check config/config.php</p>";
        exit;
    }

    // 2. Check if 'trade_log' table exists
    echo "<h2>2. Checking 'trade_log' Table...</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'trade_log'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>Table 'trade_log' exists.</p>";
    } else {
        echo "<p style='color:red;'>Table 'trade_log' does NOT exist.</p>";
        
        // Check for 'trades' table as a fallback
        echo "<h3>Checking for 'trades' table as a fallback...</h3>";
        $stmt_trades = $db->query("SHOW TABLES LIKE 'trades'");
        if ($stmt_trades->rowCount() > 0) {
            echo "<p style='color:orange;'>Alternative table 'trades' found. The script might be pointing to the wrong table.</p>";
        } else {
            echo "<p style='color:red;'>Alternative table 'trades' also not found.</p>";
        }
        exit;
    }

    // 3. Inspect 'trade_log' Table Structure
    echo "<h2>3. Inspecting 'trade_log' Table Structure...</h2>";
    $stmt = $db->query("DESCRIBE trade_log");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($structure) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars((string)$column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$column['Default']) . "</td>";
            echo "<td>" . htmlspecialchars((string)$column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Could not get table structure.</p>";
    }

    // 4. Fetch Data from 'trade_log'
    echo "<h2>4. Fetching Data from 'trade_log'...</h2>";
    $stmt = $db->query("SELECT * FROM trade_log ORDER BY id DESC LIMIT 10");
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($trades) {
        echo "<p style='color:green;'>Successfully fetched " . count($trades) . " records.</p>";
        echo "<h3>First 10 Trades:</h3>";
        echo "<table border='1' cellpadding='5'>";
        // Headers
        echo "<tr>";
        foreach (array_keys($trades[0]) as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";
        // Rows
        foreach ($trades as $trade) {
            echo "<tr>";
            foreach ($trade as $value) {
                echo "<td>" . htmlspecialchars((string)$value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>No records found in 'trade_log' table.</p>";
    }
    
    // 5. Test the main function getTradeLogWithMarketDataPDO()
    // echo "<h2>5. Testing getTradeLogWithMarketDataPDO() function...</h2>";
    // $function_trades = getTradeLogWithMarketDataPDO(10);
    
    // if ($function_trades) {
    //     echo "<p style='color:green;'>Function getTradeLogWithMarketDataPDO() returned " . count($function_trades) . " records.</p>";
    //     echo "<h3>Data from function:</h3>";
    //     echo "<pre>" . htmlspecialchars(print_r($function_trades, true)) . "</pre>";
    // } else {
    //     echo "<p style='color:red;'>Function getTradeLogWithMarketDataPDO() returned no data. This is the root cause.</p>";
    // }

    // 6. Inserting a test trade directly
    echo "<h2>6. Inserting a test trade directly...</h2>";
    try {
        // First, ensure the test coin exists in the cryptocurrencies table to satisfy foreign key constraints
        $testCoinSymbol = 'TESTCOIN_DIRECT';
        $stmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ?");
        $stmt->execute([$testCoinSymbol]);
        $coinExists = $stmt->fetch();

        if (!$coinExists) {
            echo "<p>Test coin '$testCoinSymbol' not found in 'cryptocurrencies' table. Inserting it first.</p>";
            $insertCoinStmt = $db->prepare("INSERT INTO cryptocurrencies (symbol, name, last_updated) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE symbol=symbol");
            $insertCoinStmt->execute([$testCoinSymbol, 'Test Coin Direct']);
             echo "<p style='color:green;'>Test coin '$testCoinSymbol' inserted.</p>";
        }

        $sql = "INSERT INTO trade_log (coin_id, trade_type, amount, price) VALUES ((SELECT id FROM cryptocurrencies WHERE symbol = 'TESTCOIN_DIRECT'), 'buy', 10, 100.50)";
        $stmt = $db->prepare($sql);
        if ($stmt->execute()) {
            $lastId = $db->lastInsertId();
            echo "<p style='color:green;'>Successfully inserted a direct test trade. Last inserted ID: $lastId</p>";
        } else {
            echo "<p style='color:red;'>Failed to insert direct test trade.</p>";
            echo "<p>Error: " . print_r($stmt->errorInfo(), true) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error inserting direct test trade: " . $e->getMessage() . "</p>";
    }

    // 7. Testing executeBuyPDO()
    echo "<h2>7. Testing executeBuyPDO() function...</h2>";
    $buyCoinId = 'TESTCOIN_BUY';
    $buyAmount = 20;
    $buyPrice = 120.75;
    echo "<p>Attempting to buy $buyAmount of $buyCoinId at $buyPrice...</p>";
    try {
        $buyTradeId = executeBuyPDO($buyCoinId, $buyAmount, $buyPrice);
        if ($buyTradeId) {
            echo "<p style='color:green;'>executeBuyPDO() successful. Trade ID: $buyTradeId</p>";
        } else {
            echo "<p style='color:red;'>executeBuyPDO() failed. No trade ID returned.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error during executeBuyPDO(): " . $e->getMessage() . "</p>";
    }

    // 8. Testing executeSellPDO()
    echo "<h2>8. Testing executeSellPDO() function...</h2>";
    $sellCoinId = 'TESTCOIN_BUY'; // Selling the coin we just bought
    $sellAmount = 5;
    $sellPrice = 130.25;
    echo "<p>Attempting to sell $sellAmount of $sellCoinId at $sellPrice...</p>";
    try {
        $sellResult = executeSellPDO($sellCoinId, $sellAmount, $sellPrice);
        if ($sellResult) {
            echo "<p style='color:green;'>executeSellPDO() successful.</p>";
            echo "<p>Result: <pre>" . htmlspecialchars(print_r($sellResult, true)) . "</pre></p>";
        } else {
            echo "<p style='color:red;'>executeSellPDO() failed.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error during executeSellPDO(): " . $e->getMessage() . "</p>";
    }

    // 9. Fetch Data from 'trade_log' again to see changes
    echo "<h2>9. Fetching Data from 'trade_log' again...</h2>";
    $stmt = $db->query("SELECT * FROM trade_log ORDER BY id DESC LIMIT 10");
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($trades) {
        echo "<p style='color:green;'>Successfully fetched " . count($trades) . " records.</p>";
        echo "<h3>Latest 10 Trades:</h3>";
        echo "<table border='1' cellpadding='5'>";
        // Headers
        echo "<tr>";
        if (!empty($trades)) {
            foreach (array_keys($trades[0]) as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
        }
        echo "</tr>";
        // Rows
        foreach ($trades as $trade) {
            echo "<tr>";
            foreach ($trade as $value) {
                echo "<td>" . htmlspecialchars((string)$value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>No records found in 'trade_log' table.</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red;'>An Error Occurred</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</pre>";
?>