<?php
// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to fetch all coins from CoinGecko API
function fetchAllCoinsFromCoinGecko() {
    $url = 'https://api.coingecko.com/api/v3/coins/list?include_platform=true';
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to fetch coins from CoinGecko. HTTP Code: " . $httpCode);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from CoinGecko");
    }
    
    return $data;
}

// Function to create or update the coins table
function updateCoinsTable($db, $coins) {
    // Create the table if it doesn't exist
    $createTable = "
    CREATE TABLE IF NOT EXISTS `all_coingecko_coins` (
        `id` varchar(100) NOT NULL,
        `symbol` varchar(50) NOT NULL,
        `name` varchar(100) NOT NULL,
        `platforms` TEXT NULL,
        `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_symbol` (`symbol`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if (!$db->query($createTable)) {
        throw new Exception("Failed to create table: " . $db->error);
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Prepare insert/update statement with ON DUPLICATE KEY UPDATE
        $stmt = $db->prepare("
            INSERT INTO `all_coingecko_coins` (id, symbol, name, platforms) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                symbol = VALUES(symbol),
                name = VALUES(name),
                platforms = VALUES(platforms),
                last_updated = CURRENT_TIMESTAMP
        ");
        
        // Insert or update each coin
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($coins as $coin) {
            $platforms = isset($coin['platforms']) ? json_encode($coin['platforms']) : '{}';
            
            $stmt->bind_param('ssss', 
                $coin['id'],
                $coin['symbol'],
                $coin['name'],
                $platforms
            );
            
            try {
                $result = $stmt->execute();
                
                // Check if this was an insert or update
                if ($stmt->affected_rows > 0) {
                    if ($stmt->insert_id > 0) {
                        $inserted++;
                    } else {
                        $updated++;
                    }
                } else {
                    $skipped++;
                }
                
                // Log progress every 100 records
                $total = $inserted + $updated + $skipped;
                if ($total % 100 === 0) {
                    echo "Processed $total records (I:$inserted, U:$updated, S:$skipped)\n";
                }
            
            } catch (Exception $e) {
                // Log error but continue with next coin
                echo "Error processing coin {$coin['id']}: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Return summary
        return [
            'total' => $inserted + $updated + $skipped,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

// Main execution
try {
    echo "Starting CoinGecko coins fetch...\n";
    
    // Check if we already ran today
    $db = getDBConnection();
    
    // First, ensure the table exists
    $tableExists = $db->query("SHOW TABLES LIKE 'all_coingecko_coins'")->num_rows > 0;
    
    if ($tableExists) {
        $lastRun = $db->query("SELECT MAX(last_updated) as last_run FROM all_coingecko_coins")->fetch_assoc();
        
        if ($lastRun && isset($lastRun['last_run'])) {
            $lastRunTime = new DateTime($lastRun['last_run']);
            $now = new DateTime();
            $diff = $now->diff($lastRunTime);
            
            if ($diff->days < 1) {
                die("CoinGecko data was last updated " . $diff->h . " hours ago. Skipping fetch.\n");
            }
        }
    } else {
        echo "Table 'all_coingecko_coins' doesn't exist yet. It will be created.\n";
    }
    
    // Fetch data from CoinGecko
    echo "Fetching coins from CoinGecko...\n";
    $coins = fetchAllCoinsFromCoinGecko();
    
    if (empty($coins)) {
        throw new Exception("No coins received from CoinGecko");
    }
    
    echo "Fetched " . count($coins) . " coins. Updating database...\n";
    
    // Update database
    $result = updateCoinsTable($db, $coins);
    
    // Display summary
    echo "\nUpdate Summary:\n";
    echo "Total processed: " . $result['total'] . "\n";
    echo "New coins inserted: " . $result['inserted'] . "\n";
    echo "Existing coins updated: " . $result['updated'] . "\n";
    echo "Skipped (no changes): " . $result['skipped'] . "\n\n";
    
    echo "Successfully updated the database.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Also save to CSV for backup
$filename = __DIR__ . '/../data/coingecko_coins_' . date('Ymd_His') . '.csv';
$fp = fopen($filename, 'w');

// Add CSV headers
fputcsv($fp, ['id', 'symbol', 'name', 'platforms']);

// Add data
foreach ($coins as $coin) {
    $platforms = isset($coin['platforms']) ? json_encode($coin['platforms']) : '';
    fputcsv($fp, [
        $coin['id'],
        $coin['symbol'],
        $coin['name'],
        $platforms
    ]);
}

fclose($fp);
echo "CSV backup saved to: $filename\n";

echo "Done!\n";
