<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
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
    echo "<h2>5. Testing getTradeLogWithMarketDataPDO() function...</h2>";
    $function_trades = getTradeLogWithMarketDataPDO(10);
    
    if ($function_trades) {
        echo "<p style='color:green;'>Function getTradeLogWithMarketDataPDO() returned " . count($function_trades) . " records.</p>";
        echo "<h3>Data from function:</h3>";
        echo "<pre>" . htmlspecialchars(print_r($function_trades, true)) . "</pre>";
    } else {
        echo "<p style='color:red;'>Function getTradeLogWithMarketDataPDO() returned no data. This is the root cause.</p>";
    }


} catch (Exception $e) {
    echo "<h2 style='color:red;'>An Error Occurred</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</pre>";
?>