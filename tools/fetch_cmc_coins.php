<?php
// fetch_cmc_coins.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Set error reporting - don't display errors, just log them
error_reporting(E_ALL);
ini_set('display_errors', 0);

echo "Starting CoinMarketCap data fetch...\n";

try {
    // Check if database connection is working
    $db = getDBConnection();
    echo "Database connection established.\n";
    
    // Create the table if it doesn't exist
    $createTable = "
    CREATE TABLE IF NOT EXISTS `all_cmc_coins` (
        `symbol` varchar(50) NOT NULL,
        `name` varchar(100) NOT NULL,
        `price` decimal(24,8) NOT NULL,
        `change_24h` decimal(10,2) NULL,
        `volume_24h` decimal(24,2) NULL,
        `market_cap` decimal(24,2) NULL,
        `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`symbol`),
        KEY `idx_price` (`price`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $db->exec($createTable);
    echo "Table structure verified.\n";
    
    // Fetch data from CoinMarketCap
    echo "Fetching data from CoinMarketCap API...\n";
    $data = fetchFromCMC();
    
    if (empty($data)) {
        throw new Exception("No data received from CoinMarketCap API");
    }
    
    echo "Fetched " . count($data) . " coins. Updating database...\n";
    
    // Start transaction
    $db->beginTransaction();
    
    // Prepare insert/update statement
    $stmt = $db->prepare("
        INSERT INTO `all_cmc_coins` (symbol, coin_name, price, change_24h, volume_24h, market_cap) 
        VALUES (:symbol, :name, :price, :change_24h, :volume_24h, :market_cap)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            price = VALUES(price),
            change_24h = VALUES(change_24h),
            volume_24h = VALUES(volume_24h),
            market_cap = VALUES(market_cap),
            last_updated = CURRENT_TIMESTAMP
    ");
    
    // Insert or update each coin
    $updated = 0;
    $skipped = 0;
    
    foreach ($data as $symbol => $values) {
        try {
            $name = $values['name'] ?? $symbol;
            $price = $values['price'] ?? 0;
            $change = $values['change'] ?? 0;
            $volume = $values['volume_24h'] ?? 0;
            $marketCap = $values['market_cap'] ?? 0;
            
            $stmt->bindParam(':symbol', $symbol, PDO::PARAM_STR);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':change_24h', $change);
            $stmt->bindParam(':volume_24h', $volume);
            $stmt->bindParam(':market_cap', $marketCap);
            
            $stmt->execute();
            
            // Check if this was an insert or update
            $rowCount = $stmt->rowCount();
            if ($rowCount > 0) {
                $updated++;
            } else {
                $skipped++;
            }
            
            // Log progress every 100 records
            $total = $updated + $skipped;
            if ($total % 100 === 0) {
                echo "Processed $total records (U:$updated, S:$skipped)\n";
            }
        
        } catch (PDOException $e) {
            echo "Error processing coin $symbol: " . $e->getMessage() . "\n";
            $skipped++;
            continue;
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Display summary
    echo "\nUpdate Summary:\n";
    echo "Total processed: " . ($updated + $skipped) . "\n";
    echo "Updated: " . $updated . "\n";
    echo "Skipped: " . $skipped . "\n\n";
    
    echo "Successfully updated the database.\n";
    
    // Also save to CSV for backup
    $filename = __DIR__ . '/../data/cmc_coins_' . date('Ymd_His') . '.csv';
    $fp = fopen($filename, 'w');

    // Add CSV headers
    fputcsv($fp, ['symbol', 'name', 'price', 'change_24h', 'volume_24h', 'market_cap']);

    // Add data
    foreach ($data as $symbol => $values) {
        $name = $values['name'] ?? $symbol;
        $price = $values['price'] ?? 0;
        $change = $values['change'] ?? 0;
        $volume = $values['volume_24h'] ?? 0;
        $marketCap = $values['market_cap'] ?? 0;
        
        fputcsv($fp, [
            $symbol,
            $name,
            $price,
            $change,
            $volume,
            $marketCap
        ]);
    }

    fclose($fp);
    echo "CSV backup saved to: $filename\n";
    
    echo "Done!\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
