<?php
// === Starting fetch_coins.php ===

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Suppress warnings for this script execution
error_reporting(E_ERROR | E_PARSE);

/**
 * Generate a unique ID for a coin
 * @param string $symbol The coin symbol
 * @param string $source The data source
 * @return string MD5 hash of symbol and source
 */
function generateCoinId($symbol, $source) {
    return md5(strtolower($symbol) . '_' . strtolower(str_replace(' ', '_', $source)));
}

echo "\n===== FETCHING CRYPTOCURRENCY DATA =====\n";

// Initialize the processed coins array
$processedCoins = [];
$sourceCount = [];

// Database connection function
function connectDB() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        die("Database connection failed: " . $mysqli->connect_error);
    }
    return $mysqli;
}

// Add source column to cryptocurrencies table if it doesn't exist
function addSourceColumnIfNeeded($db) {
    $result = $db->query("SHOW COLUMNS FROM cryptocurrencies LIKE 'source'");
    if ($result->num_rows === 0) {
        $db->query("ALTER TABLE cryptocurrencies ADD COLUMN source VARCHAR(50) DEFAULT 'CoinMarketCap' AFTER symbol");
    }
}

// New API functions skeleton
class CoinFetcher {
    public function __construct() {
    }

    private function fetchFromCMC($coinSymbols) {
        $url = CMC_API_URL."/v1/cryptocurrency/quotes/latest?symbol=".urlencode($coinSymbols);
        
        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: '.CMC_API_KEY
        ];

        return $this->fetchWithRetry($url, $headers);
    }

    private function fetchFromCoinGecko($coinIds) {
        $url = COINGECKO_API_URL."/coins/markets?vs_currency=usd&ids=".urlencode($coinIds);
        $headers = ['Accept: application/json'];
        
        if (defined('COINGECKO_API_KEY') && COINGECKO_API_KEY !== 'your_coingecko_key_here') {
            $headers[] = 'x-cg-pro-api-key: '.COINGECKO_API_KEY;
        }
        
        return $this->fetchWithRetry($url, $headers);
    }

    private function fetchWithRetry($url, $headers, $maxRetries = 3) {
        $retryCount = 0;
        while ($retryCount < $maxRetries) {
            if ($retryCount > 0) {
                sleep(5); // 5 second delay between retries
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FAILONERROR => true
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200) {
                return $response;
            }

            $retryCount++;
        }
        return false;
    }

    private function processCMCData($apiData) {
        $results = [];
        foreach ($apiData as $symbol => $data) {
            $results[] = [
                'id' => $symbol,
                'symbol' => $symbol,
                'price' => $data['quote']['USD']['price'],
                'market_cap' => $data['quote']['USD']['market_cap'],
                'volume' => $data['quote']['USD']['volume_24h'],
                'price_change_24h' => $data['quote']['USD']['percent_change_24h'],
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }
        return $results;
    }

    private function processCoinGeckoData($apiData) {
        $results = [];
        foreach ($apiData as $coin) {
            $results[] = [
                'id' => $coin['id'],
                'symbol' => strtoupper($coin['symbol']),
                'price' => $coin['current_price'],
                'market_cap' => $coin['market_cap'],
                'volume' => $coin['total_volume'],
                'price_change_24h' => $coin['price_change_percentage_24h'],
                'last_updated' => date('Y-m-d H:i:s', strtotime($coin['last_updated']))
            ];
        }
        return $results;
    }

    private function fetchFromJupiter() {
        // Implementation here
    }

    // Fetch from CoinMarketCap, CoinGecko, and other APIs
    private function fetchCoins() {
        $results = [];
        $symbolString = 'USUAL,LOOM,BCH,ETH,RED,TLM';

        // Fetch from CoinMarketCap
        if(!empty($cmcData = $this->fetchFromCMC($symbolString))) {
            $cmcData = json_decode($cmcData, true);
            if(is_array($cmcData['data'] ?? null)) {
                $results = array_merge($results, $this->processCMCData($cmcData['data']));
            }
        }

        // Fetch from CoinGecko
        $cgIds = [
            'DMC' => 'doggy-moon-coin',
            'POPCAT' => 'popcat'
        ];
        $cgWhitelist = array_intersect_key($cgIds, array_flip(COIN_WHITELIST));

        if (defined('COINGECKO_API_KEY') && COINGECKO_API_KEY !== 'your_coingecko_key_here') {
            if(!empty($cgData = $this->fetchFromCoinGecko(implode(',', $cgWhitelist)))) {
                $cgData = json_decode($cgData, true);
                if(is_array($cgData)) {
                    $results = array_merge($results, $this->processCoinGeckoData($cgData));
                }
            }
        }

        if(empty($results)) {
            return false;
        }

        return $results;
    }

    public function run() {
        $results = $this->fetchCoins();

        // Process each coin
        foreach ($results as $coin) {
            $coinId = $coin['id'];
            $symbol = $coin['symbol'];
            $source = 'CoinMarketCap'; // Default source

            if (strpos($coinId, 'COIN_') === 0) {
                $source = 'CoinGecko';
            }

            $processedCoins[$coinId] = [
                'name' => $coin['name'] ?? 'Unknown',
                'symbol' => $symbol,
                'price' => $coin['price'] ?? 0,
                'change' => $coin['price_change_24h'] ?? 0,
                'market_cap' => $coin['market_cap'] ?? 0,
                'volume' => $coin['volume'] ?? 0,
                'volume_24h' => $coin['volume_24h'] ?? $coin['volume'] ?? 0,
                'date_added' => null,
                'source' => $source
            ];

            $sourceCount[$source]++;
            echo "Added {$symbol} from {$source}\n";
        }

        // Check if we have any data
        if (empty($processedCoins)) {
            echo "\n✗ ERROR: Failed to fetch cryptocurrency data from any source\n";
            exit;
        }

        // Display summary of fetched data
        echo "\n===== CRYPTOCURRENCY DATA SUMMARY =====\n";
        echo "Total coins fetched: " . count($processedCoins) . "\n";
        echo "Coins by source:\n";
        foreach ($sourceCount as $source => $count) {
            echo "- {$source}: {$count} coins\n";
        }
        echo "====================================\n\n";

        $db = connectDB();
        addSourceColumnIfNeeded($db);

        echo "\n===== PROCESSING COINS =====\n";
        echo "Total coins to process: " . count($processedCoins) . "\n\n";

        // Process each coin
        foreach ($processedCoins as $coinId => $coin) {
            try {
                // Get the symbol and source
                $symbol = $coin['symbol'];
                $source = $coin['source'];

                // Convert datetime to MySQL format if available
                $createdAt = isset($coin['date_added']) && $coin['date_added']
                    ? date('Y-m-d H:i:s', strtotime($coin['date_added']))
                    : date('Y-m-d H:i:s', strtotime('-24 hours'));

                $ageHours = isset($coin['date_added']) && $coin['date_added']
                    ? round((time() - strtotime($coin['date_added'])) / 3600)
                    : 24;

                // Check if this coin already exists in the database
                $stmt = $db->prepare("SELECT id FROM cryptocurrencies WHERE symbol = ? AND source = ?");
                $stmt->bind_param("ss", $symbol, $source);
                $stmt->execute();
                $result = $stmt->get_result();
                $coinExists = $result->num_rows > 0;

                if (!$coinExists) {
                    // Insert new coin
                    $stmt = $db->prepare("INSERT INTO cryptocurrencies
                        (id, name, symbol, created_at, age_hours, market_cap, volume, price, price_change_24h, last_updated, source)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                    // Prepare variables for binding
                    $name = $coin['name'];
                    $marketCap = $coin['market_cap'] ?? 0;
                    $volume = $coin['volume'] ?? 0;
                    $price = $coin['price'] ?? 0;
                    $change = $coin['price_change_24h'] ?? 0;

                    $stmt->bind_param(
                        'ssssiiddds',
                        $coinId,
                        $name,
                        $symbol,
                        $createdAt,
                        $ageHours,
                        $marketCap,
                        $volume,
                        $price,
                        $change,
                        $source
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Insert failed: " . $stmt->error);
                    }

                } else {
                    // Update existing coin
                    $existingCoin = $result->fetch_assoc();
                    $coinId = $existingCoin['id'];

                    $stmt = $db->prepare("UPDATE cryptocurrencies SET
                        name = ?,
                        market_cap = ?,
                        volume = ?,
                        price = ?,
                        price_change_24h = ?,
                        last_updated = NOW(),
                        source = ?
                        WHERE id = ?");
                    // Prepare variables for binding
                    $name = $coin['name'];
                    $marketCap = $coin['market_cap'] ?? 0;
                    $volume = $coin['volume'] ?? 0;
                    $price = $coin['price'] ?? 0;
                    $change = $coin['price_change_24h'] ?? 0;

                    $stmt->bind_param(
                        'sddddss',
                        $name,
                        $marketCap,
                        $volume,
                        $price,
                        $change,
                        $source,
                        $coinId
                    );
                    $stmt->execute();
                }

                // Update price history
                $stmt = $db->prepare("INSERT INTO price_history
                    (coin_id, price, volume, market_cap)
                    VALUES (?, ?, ?, ?)");
                // Prepare variables for binding
                $price = $coin['price'] ?? 0;
                $volume = $coin['volume'] ?? 0;
                $marketCap = $coin['market_cap'] ?? 0;

                $stmt->bind_param(
                    'sddd',
                    $coinId,
                    $price,
                    $volume,
                    $marketCap
                );
                $stmt->execute();

                // Check for volume spikes
                if ($coin['volume'] >= MIN_VOLUME_THRESHOLD) {
                    $db->query("UPDATE cryptocurrencies SET is_trending=1 WHERE id='{$coinId}'");
                }

                // Output detailed coin information
                echo "Processed: {$symbol} from {$source} - Price: $" . number_format($coin['price'] ?? 0, 4) . ", Volume: $" . number_format($coin['volume'] ?? 0, 2) . "\n";

            } catch (Exception $e) {
                continue;
            }
        }

        // Display summary after processing
        echo "\n===== PROCESSING SUMMARY =====\n";
        echo "Successfully updated cryptocurrency data from multiple sources\n";

        echo "Coins in database by source:\n";
        foreach ($sourceCount as $source => $count) {
            echo "- {$source}: {$count} coins\n";
        }

        echo "============================\n";
    }
}

// Instantiate and run
$fetcher = new CoinFetcher();
$fetcher->run();