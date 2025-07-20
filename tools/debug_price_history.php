<?php
/**
 * Price History Diagnostic Tool
 * For Night Stalker Cryptobot
 * 
 * This script checks the price_history table for common issues
 * and provides diagnostic information to help troubleshoot problems.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';
// Set headers for CLI or browser output
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>";
}

echo "=== PRICE HISTORY DIAGNOSTIC TOOL ===\n\n";
echo "Running diagnostics at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "✓ Database connection successful\n\n";
    
    // Check if price_history table exists
    $stmt = $db->query("SHOW TABLES LIKE 'price_history'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("price_history table does not exist");
    }
    
    echo "✓ price_history table exists\n\n";
    
    // Check table structure
    echo "Checking table structure...\n";
    $stmt = $db->query("DESCRIBE price_history");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})" . 
             ($column['Null'] === 'NO' ? ' NOT NULL' : '') . 
             (isset($column['Default']) ? " DEFAULT '{$column['Default']}'" : '') . "\n";
    }
    echo "\n";
    
    // Check for primary key
    $stmt = $db->query("SHOW KEYS FROM price_history WHERE Key_name = 'PRIMARY'");
    $primaryKey = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($primaryKey)) {
        echo "⚠️ WARNING: No primary key defined on price_history table\n\n";
    } else {
        echo "✓ Primary key defined on column(s): " . implode(', ', array_column($primaryKey, 'Column_name')) . "\n\n";
    }
    
    // Check total records
    $stmt = $db->query("SELECT COUNT(*) as total FROM price_history");
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "Total records: $totalRecords\n\n";
    
    if ($totalRecords === 0) {
        echo "⚠️ WARNING: price_history table is empty\n\n";
    } else {
        // Check distinct coins
        $stmt = $db->query("SELECT COUNT(DISTINCT coin_id) as distinct_coins FROM price_history");
        $distinctCoins = $stmt->fetch(PDO::FETCH_ASSOC)['distinct_coins'];
        
        echo "Distinct coins: $distinctCoins\n\n";
        
        // Show sample of coins with their record counts
        $stmt = $db->query("
            SELECT coin_id, COUNT(*) as record_count, 
                   MIN(recorded_at) as first_record, 
                   MAX(recorded_at) as last_record,
                   MIN(price) as min_price,
                   MAX(price) as max_price
            FROM price_history 
            GROUP BY coin_id 
            ORDER BY record_count DESC 
            LIMIT 10
        ");
        $coinStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Top 10 coins by record count:\n";
        echo str_pad("Coin ID", 20) . " | " . 
             str_pad("Records", 8) . " | " . 
             str_pad("First Record", 20) . " | " . 
             str_pad("Last Record", 20) . " | " . 
             str_pad("Min Price", 12) . " | " . 
             str_pad("Max Price", 12) . "\n";
        echo str_repeat("-", 100) . "\n";
        
        foreach ($coinStats as $stat) {
            echo str_pad($stat['coin_id'], 20) . " | " . 
                 str_pad($stat['record_count'], 8) . " | " . 
                 str_pad($stat['first_record'], 20) . " | " . 
                 str_pad($stat['last_record'], 20) . " | " . 
                 str_pad($stat['min_price'], 12) . " | " . 
                 str_pad($stat['max_price'], 12) . "\n";
        }
        echo "\n";
        
        // Check for null or zero prices
        $stmt = $db->query("SELECT COUNT(*) as count FROM price_history WHERE price IS NULL OR price = 0");
        $nullPrices = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($nullPrices > 0) {
            echo "⚠️ WARNING: Found $nullPrices records with NULL or zero prices\n\n";
        } else {
            echo "✓ No NULL or zero prices found\n\n";
        }
        
        // Check for recent records (last 24 hours)
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM price_history 
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $recentRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "Records in last 24 hours: $recentRecords\n\n";
        
        if ($recentRecords === 0) {
            echo "⚠️ WARNING: No recent price history data (last 24 hours)\n\n";
        }
    }
    
    echo "=== DIAGNOSTIC COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
}

// Close HTML pre tag if in browser mode
if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
