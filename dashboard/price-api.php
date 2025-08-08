<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/database.php';
header('Content-Type: application/json');

// Get database connection
$db = getDBConnection();

if ($db === null) {
    error_log('No database connection available');
}

// Debug: Verify database connection
if ($db) {
    error_log('Database connection successful');
    try {
        $test = $db->query("SELECT 1")->fetchColumn();
        error_log('Database test query result: ' . $test);
    } catch (PDOException $e) {
        error_log('Database test failed: ' . $e->getMessage());
    }
}

// Create table if connection exists
if ($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `crypto_prices` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `coin_id` varchar(20) NOT NULL,
            `price` decimal(24,12) NOT NULL,
            `24h_change` decimal(10,4) DEFAULT NULL,
            `market_cap` decimal(30,8) DEFAULT NULL,
            `source` varchar(20) DEFAULT NULL,
            `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `coin_id` (`coin_id`),
            KEY `timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (PDOException $e) {
        error_log('Failed to create crypto_prices table: ' . $e->getMessage());
    }
}

// Get coin ID/symbol from request (default to DMC)
$coinId = strtolower($_GET['coin'] ?? 'DMC');
$coinSymbol = strtoupper($coinId);

// Cache setup - use system temp directory which is always writable
$cacheDir = sys_get_temp_dir().'/nightstalker_cache';
if (!file_exists($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
    $cacheDir = null; // Disable caching if directory creation fails
}

$cacheFile = $cacheDir ? $cacheDir.'/price_cache_'.$coinId.'.json' : null;
$cacheTime = 60; // 1 minute

// Try to use cached data if fresh
if ($cacheFile && file_exists($cacheFile) && is_readable($cacheFile) && 
    time() - filemtime($cacheFile) < $cacheTime) {
    $cachedData = file_get_contents($cacheFile);
    if ($cachedData !== false) {
        echo $cachedData;
        exit;
    }
}

try {
    $priceData = [];
    
    switch (PRICE_API_SOURCE) {
        case 'coinmarketcap':
            // CoinMarketCap implementation
            $ch = curl_init();
            $url = CMC_API_URL."/quotes/latest?symbol=$coinSymbol";
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-CMC_PRO_API_KEY: '.CMC_API_KEY,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $data = json_decode($response, true);
            
            if (empty($data['data'][$coinSymbol])) {
                throw new Exception('CoinMarketCap: No data for symbol');
            }
            
            $priceData = [
                'price' => $data['data'][$coinSymbol]['quote']['USD']['price'],
                'timestamp' => time(),
                'coin' => $coinId,
                '24h_change' => $data['data'][$coinSymbol]['quote']['USD']['percent_change_24h'],
                'market_cap' => $data['data'][$coinSymbol]['quote']['USD']['market_cap'],
                'source' => 'coinmarketcap'
            ];
            break;
            
        case 'coingecko':
        default:
            // CoinGecko implementation
            $ch = curl_init();
            $url = COINGECKO_API_URL."/coins/markets?vs_currency=usd&ids=$coinId";
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $data = json_decode($response, true);
            
            if (empty($data)) {
                throw new Exception('CoinGecko: No price data returned');
            }
            
            $priceData = [
                'price' => $data[0]['current_price'],
                'timestamp' => time(),
                'coin' => $coinId,
                '24h_change' => $data[0]['price_change_percentage_24h'],
                'market_cap' => $data[0]['market_cap'],
                'source' => 'coingecko'
            ];
            break;
    }
    
    // Save to cache
    if ($cacheFile) {
        file_put_contents($cacheFile, json_encode($priceData));
    }
    
    // Store price data in database if connection exists
    if ($db) {
        try {
            error_log('Attempting to store price data for '.$coinId);
            $stmt = $db->prepare("INSERT INTO crypto_prices (coin_id, price, `24h_change`, market_cap, source) VALUES (:coin_id, :price, :24h_change, :market_cap, :source)");
            $stmt->execute([
                ':coin_id' => $priceData['coin'],
                ':price' => $priceData['price'],
                ':24h_change' => $priceData['24h_change'],
                ':market_cap' => $priceData['market_cap'],
                ':source' => $priceData['source']
            ]);
            error_log('Stored price data. Rows affected: '.$stmt->rowCount());
        } catch (PDOException $e) {
            error_log('Database error: '.$e->getMessage());
        }
    } else {
        error_log('No database connection available for storage');
    }
    
    // Output data
    echo json_encode($priceData);
    
} catch (Exception $e) {
    // Fallback to cached data if available
    if ($cacheFile && file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        // Final fallback to simulated data
        echo json_encode([
            'price' => round(30000 + (rand(-1000, 1000) / 100), 2),
            'timestamp' => time(),
            'coin' => $coinId,
            '24h_change' => rand(-10, 10),
            'market_cap' => rand(500000000, 1000000000),
            'source' => 'fallback'
        ]);
    }
    
    error_log('Price API Error: '.$e->getMessage());
} finally {
    if (isset($ch)) {
        curl_close($ch);
    }
}
?>
