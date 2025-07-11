<?php
/**
 * Binance Exchange Handler
 * 
 * Handles direct interaction with the Binance API for fetching coin data,
 * monitoring prices, and executing trades.
 */

class BinanceHandler {
    private $apiKey;
    private $apiSecret;
    private $apiUrl;
    private $logger;
    
    /**
     * Constructor - initialize with API credentials
     */
    public function __construct() {
        $this->apiKey = BINANCE_API_KEY;
        $this->apiSecret = BINANCE_API_SECRET;
        $this->apiUrl = BINANCE_API_URL;
        
        // Initialize logger
        require_once __DIR__ . '/../TradingLogger.php';
        $this->logger = new TradingLogger();
        $this->logger->logEvent("binance", "init", ["message" => "Binance handler initialized"]);
    }
    
    /**
     * Get list of available coins on Binance
     * 
     * @return array List of available coin symbols
     */
    public function getAvailableCoins() {
        $this->logger->logEvent("binance", "info", ["message" => "Fetching available coins from Binance"]);
        
        try {
            // Call Binance API to get exchange info
            $response = $this->makeApiRequest('/api/v3/exchangeInfo', [], 'GET');
            
            if (!isset($response['symbols'])) {
                $this->logger->logEvent("binance", "error", ["message" => "Invalid response from Binance API"]);
                return [];
            }
            
            // Filter for active symbols with USDT trading pairs
            $coins = [];
            foreach ($response['symbols'] as $symbol) {
                if ($symbol['status'] === 'TRADING' && 
                    $symbol['quoteAsset'] === 'USDT' &&
                    $symbol['isSpotTradingAllowed']) {
                    
                    $coins[] = $symbol['symbol'];
                }
            }
            
            $this->logger->logEvent("binance", "info", ["count" => count($coins), "message" => "Found " . count($coins) . " available coins on Binance"]);
            return $coins;
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["message" => "ERROR fetching available coins: " . $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get current prices for all coins or a specific coin
     * 
     * @param string|null $symbol Optional specific symbol to get price for
     * @return array Associative array of symbol => price
     */
    public function getCurrentPrices($symbol = null) {
        try {
            $endpoint = '/api/v3/ticker/price';
            $params = [];
            
            if ($symbol) {
                $params['symbol'] = $symbol;
            }
            
            $response = $this->makeApiRequest($endpoint, $params, 'GET');
            
            $prices = [];
            
            // Handle both single symbol and all symbols responses
            if (isset($response['symbol']) && isset($response['price'])) {
                // Single symbol response
                $prices[$response['symbol']] = floatval($response['price']);
            } else {
                // Multiple symbols response
                foreach ($response as $item) {
                    if (isset($item['symbol']) && isset($item['price']) && 
                        strpos($item['symbol'], 'USDT') !== false) {
                        $prices[$item['symbol']] = floatval($item['price']);
                    }
                }
            }
            
            return $prices;
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["message" => "ERROR fetching prices: " . $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Check if we have sufficient funds for a trade
     * 
     * @param float $estimatedCost Estimated cost of the trade
     * @return bool True if sufficient funds available
     */
    public function checkSufficientFunds($estimatedCost) {
        try {
            // Get account information including balances
            $response = $this->makeApiRequest('/api/v3/account', [], 'GET', true);
            
            if (!isset($response['balances'])) {
                $this->logger->logEvent("binance", "error", ["message" => "Invalid response when checking balances"]);
                return false;
            }
            
            // Find USDT balance
            $usdtBalance = 0;
            foreach ($response['balances'] as $balance) {
                if ($balance['asset'] === 'USDT') {
                    $usdtBalance = floatval($balance['free']);
                    break;
                }
            }
            
            // Add some buffer for fees and price fluctuations
            $requiredWithBuffer = $estimatedCost * 1.01;
            
            $this->logger->logEvent("binance", "info", ["available" => $usdtBalance, "required" => $requiredWithBuffer, "message" => "Checking funds - Available: $usdtBalance USDT, Required: $requiredWithBuffer USDT"]);
            
            return $usdtBalance >= $requiredWithBuffer;
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["message" => "ERROR checking funds: " . $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Execute a buy order
     * 
     * @param string $symbol Symbol to buy
     * @param float $amount Amount to buy
     * @return array Result with success status and transaction details
     */
    public function executeBuy($symbol, $amount) {
        $this->logger->logEvent("binance", "buy", ["symbol" => $symbol, "amount" => $amount, "message" => "Executing buy order for $amount $symbol"]);
        
        try {
            // Get current price
            $priceData = $this->getCurrentPrices($symbol);
            if (empty($priceData) || !isset($priceData[$symbol])) {
                return [
                    'success' => false,
                    'error' => "Could not get current price for $symbol"
                ];
            }
            
            $currentPrice = $priceData[$symbol];
            
            // Calculate quantity based on amount and price
            $quantity = $this->calculateAndFormatQuantity($symbol, $amount, $currentPrice);
            
            // Prepare order parameters
            $params = [
                'symbol' => $symbol,
                'side' => 'BUY',
                'type' => 'MARKET',
                'quantity' => $quantity,
                'timestamp' => $this->getTimestamp()
            ];
            
            // Execute the order
            $response = $this->makeApiRequest('/api/v3/order', $params, 'POST', true);
            
            if (isset($response['orderId'])) {
                // Record the trade in our database
                $this->recordTrade($symbol, 'buy', $quantity, $currentPrice, $response['orderId']);
                
                return [
                    'success' => true,
                    'transactionId' => $response['orderId'],
                    'price' => $currentPrice,
                    'quantity' => $quantity,
                    'total' => $currentPrice * $quantity
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Order failed: " . json_encode($response)
                ];
            }
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["action" => "buy", "message" => "ERROR executing buy: " . $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute a sell order
     * 
     * @param string $symbol Symbol to sell
     * @param float $amount Amount to sell
     * @return array Result with success status and transaction details
     */
    public function executeSell($symbol, $amount) {
        $this->logger->logEvent("binance", "sell", ["symbol" => $symbol, "amount" => $amount, "message" => "Executing sell order for $amount $symbol"]);
        
        try {
            // Get current price
            $priceData = $this->getCurrentPrices($symbol);
            if (empty($priceData) || !isset($priceData[$symbol])) {
                return [
                    'success' => false,
                    'error' => "Could not get current price for $symbol"
                ];
            }
            
            $currentPrice = $priceData[$symbol];
            
            // Calculate quantity based on amount and price
            $quantity = $this->calculateAndFormatQuantity($symbol, $amount, $currentPrice);
            
            // Prepare order parameters
            $params = [
                'symbol' => $symbol,
                'side' => 'SELL',
                'type' => 'MARKET',
                'quantity' => $quantity,
                'timestamp' => $this->getTimestamp()
            ];
            
            // Execute the order
            $response = $this->makeApiRequest('/api/v3/order', $params, 'POST', true);
            
            if (isset($response['orderId'])) {
                // Record the trade in our database
                $this->recordTrade($symbol, 'sell', $quantity, $currentPrice, $response['orderId']);
                
                return [
                    'success' => true,
                    'transactionId' => $response['orderId'],
                    'price' => $currentPrice,
                    'quantity' => $quantity,
                    'total' => $currentPrice * $quantity
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Order failed: " . json_encode($response)
                ];
            }
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["action" => "sell", "message" => "ERROR executing sell: " . $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make an API request to Binance
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method (GET, POST, etc.)
     * @param bool $auth Whether authentication is required
     * @return array Response data
     */
    private function makeApiRequest($endpoint, $params = [], $method = 'GET', $auth = false) {
        $url = $this->apiUrl . $endpoint;
        
        // Add authentication if required
        if ($auth) {
            $params['timestamp'] = $this->getTimestamp();
            $params['recvWindow'] = 5000;
            $query = http_build_query($params);
            $signature = hash_hmac('sha256', $query, $this->apiSecret);
            $params['signature'] = $signature;
        }
        
        // Build query string
        $query = http_build_query($params);
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set authentication headers if required
        if ($auth) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-MBX-APIKEY: ' . $this->apiKey
            ]);
        }
        
        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check for errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: $error");
        }
        
        curl_close($ch);
        
        // Decode JSON response
        $data = json_decode($response, true);
        
        // Check for API errors
        if ($httpCode >= 400) {
            $errorMessage = isset($data['msg']) ? $data['msg'] : "API Error (HTTP $httpCode)";
            throw new Exception("Binance API Error: $errorMessage");
        }
        
        return $data;
    }
    
    /**
     * Get current timestamp in milliseconds
     * 
     * @return int Timestamp
     */
    private function getTimestamp() {
        return round(microtime(true) * 1000);
    }
    
    /**
     * Calculate and format quantity for trading based on exchange rules
     * 
     * @param string $symbol Trading symbol
     * @param float $amount Amount to trade
     * @param float $price Current price
     * @return string Formatted quantity
     */
    private function calculateAndFormatQuantity($symbol, $amount, $price) {
        // Get symbol info to determine precision
        $symbolInfo = $this->getSymbolInfo($symbol);
        
        // Default precision if we can't get symbol info
        $precision = 5;
        
        if ($symbolInfo && isset($symbolInfo['quantityPrecision'])) {
            $precision = $symbolInfo['quantityPrecision'];
        }
        
        // Format the quantity with the correct precision
        return number_format($amount, $precision, '.', '');
    }
    
    /**
     * Get symbol information from Binance
     * 
     * @param string $symbol Trading symbol
     * @return array|null Symbol information or null if not found
     */
    private function getSymbolInfo($symbol) {
        static $symbolsInfo = null;
        
        // Cache the exchange info to avoid repeated API calls
        if ($symbolsInfo === null) {
            try {
                $response = $this->makeApiRequest('/api/v3/exchangeInfo', [], 'GET');
                if (isset($response['symbols'])) {
                    $symbolsInfo = [];
                    foreach ($response['symbols'] as $info) {
                        $symbolsInfo[$info['symbol']] = $info;
                    }
                }
            } catch (Exception $e) {
                $this->logger->logEvent("binance", "error", ["message" => "ERROR getting exchange info: " . $e->getMessage()]);
                return null;
            }
        }
        
        return isset($symbolsInfo[$symbol]) ? $symbolsInfo[$symbol] : null;
    }
    
    /**
     * Get metadata for available coins (age, market cap, volume)
     * 
     * @return array Coin metadata
     */
    public function getCoinMetadata() {
        $this->logger->logEvent("binance", "info", ["message" => "Fetching coin metadata from Binance"]);
        $metadata = [];
        
        try {
            // Get 24hr ticker statistics for all symbols
            $response = $this->makeApiRequest('/api/v3/ticker/24hr', [], 'GET', false);
            
            if (!is_array($response)) {
                $this->logger->logEvent("binance", "error", ["message" => "Invalid response when fetching coin metadata"]);
                return [];
            }
            
            foreach ($response as $ticker) {
                if (!isset($ticker['symbol']) || !isset($ticker['quoteVolume'])) {
                    continue;
                }
                
                $symbol = $ticker['symbol'];
                if (substr($symbol, -4) === 'USDT') {
                    $baseSymbol = substr($symbol, 0, -4);
                    
                    // Get approximate age by checking listing date
                    // Note: This is an approximation as Binance API doesn't directly provide listing date
                    $ageHours = $this->getApproximateAge($baseSymbol);
                    
                    // Calculate market cap (price * circulating supply)
                    // Note: We're using volume as a proxy since exact circulating supply isn't available
                    $price = isset($ticker['lastPrice']) ? floatval($ticker['lastPrice']) : 0;
                    $volume24h = isset($ticker['quoteVolume']) ? floatval($ticker['quoteVolume']) : 0;
                    $marketCap = $price * ($volume24h / 10); // Rough approximation
                    
                    $metadata[$symbol] = [
                        'age_hours' => $ageHours,
                        'market_cap' => $marketCap,
                        'volume_24h' => $volume24h
                    ];
                }
            }
            
            $this->logger->logEvent("binance", "info", ["count" => count($metadata), "message" => "Fetched metadata for " . count($metadata) . " coins"]);
            return $metadata;
            
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["message" => "ERROR fetching coin metadata: " . $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get approximate age of a coin in hours
     * 
     * @param string $symbol Coin symbol
     * @return int Approximate age in hours
     */
    private function getApproximateAge($symbol) {
        try {
            // Try to get the first trade for this symbol
            $params = [
                'symbol' => $symbol . 'USDT',
                'limit' => 1
            ];
            
            $response = $this->makeApiRequest('/api/v3/trades', $params, 'GET', false);
            
            if (is_array($response) && count($response) > 0 && isset($response[0]['time'])) {
                $firstTradeTime = $response[0]['time'] / 1000; // Convert from milliseconds to seconds
                $ageSeconds = time() - $firstTradeTime;
                return round($ageSeconds / 3600); // Convert to hours
            }
            
            // If we can't get the first trade, check klines (candlesticks)
            $params = [
                'symbol' => $symbol . 'USDT',
                'interval' => '1d',
                'limit' => 1,
                'startTime' => 0 // From the beginning
            ];
            
            $response = $this->makeApiRequest('/api/v3/klines', $params, 'GET', false);
            
            if (is_array($response) && count($response) > 0 && isset($response[0][0])) {
                $firstCandleTime = $response[0][0] / 1000; // Convert from milliseconds to seconds
                $ageSeconds = time() - $firstCandleTime;
                return round($ageSeconds / 3600); // Convert to hours
            }
            
            // Default to a high number if we can't determine age
            return 9999;
            
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["message" => "ERROR determining coin age: " . $e->getMessage()]);
            return 9999; // Default to a high number
        }
    }
    
    /**
     * Get available funds for trading
     * 
     * @return float Available USDT balance
     */
    public function getAvailableFunds() {
        try {
            $response = $this->makeApiRequest('/api/v3/account', [], 'GET', true);
            
            if (!isset($response['balances'])) {
                $this->logger->logEvent("binance", "error", ["message" => "Invalid response when checking available funds"]);
                return 0;
            }
            
            foreach ($response['balances'] as $balance) {
                if ($balance['asset'] === 'USDT') {
                    $available = floatval($balance['free']);
                    $this->logger->logEvent("binance", "info", ["available" => $available, "message" => "Available funds: $available USDT"]);
                    return $available;
                }
            }
            
            $this->logger->logEvent("binance", "warning", ["message" => "No USDT balance found"]);
            return 0;
            
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["message" => "ERROR checking available funds: " . $e->getMessage()]);
            return 0;
        }
    }
    
    /**
     * Record a trade in the database
     * 
     * @param string $symbol Trading symbol
     * @param string $type Trade type (buy/sell)
     * @param float $quantity Quantity traded
     * @param float $price Trade price
     * @param string $orderId Exchange order ID
     * @return bool Success status
     */
    private function recordTrade($symbol, $type, $quantity, $price, $orderId) {
        try {
            // Connect to database
            require_once __DIR__ . '/../database.php';
            $db = getDbConnection();
            
            // Prepare statement
            $stmt = $db->prepare("
                INSERT INTO trades 
                (coin_id, trade_type, amount, price, exchange, order_id, trade_time) 
                VALUES (?, ?, ?, ?, 'binance', ?, NOW())
            ");
            
            // Extract base symbol (remove USDT)
            $baseSymbol = str_replace('USDT', '', $symbol);
            
            // Execute statement
            $stmt->execute([
                $baseSymbol,
                $type,
                $quantity,
                $price,
                $orderId
            ]);
            
            $this->logger->logEvent("binance", "db", ["type" => $type, "symbol" => $symbol, "quantity" => $quantity, "price" => $price, "message" => "Trade recorded in database: $type $quantity $symbol at $price"]);
            return true;
        } catch (Exception $e) {
            $this->logger->logEvent("binance", "error", ["action" => "record_trade", "message" => "ERROR recording trade: " . $e->getMessage()]);
            return false;
        }
    }
}
