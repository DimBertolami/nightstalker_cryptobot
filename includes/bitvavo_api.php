<?php
/**
 * BitvavoAPI Class
 * 
 * Handles API requests to Bitvavo for real-time price data and trading
 */

class BitvavoAPI {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.bitvavo.com/v2';
    
    /**
     * Constructor
     * 
     * @param string $apiKey Bitvavo API Key
     * @param string $apiSecret Bitvavo API Secret
     */
    public function __construct($apiKey = null, $apiSecret = null) {
        // Use provided keys or try to get from environment/config
        $this->apiKey = $apiKey ?: (defined('BITVAVO_API_KEY') ? BITVAVO_API_KEY : getenv('BITVAVO_API_KEY'));
        $this->apiSecret = $apiSecret ?: (defined('BITVAVO_API_SECRET') ? BITVAVO_API_SECRET : getenv('BITVAVO_API_SECRET'));
        
        if (!$this->apiKey || !$this->apiSecret) {
            error_log("Warning: Bitvavo API credentials not provided. Some functionality will be limited.");
        }
    }
    
    /**
     * Create authentication signature for Bitvavo API
     * 
     * @param int $timestamp Current timestamp in milliseconds
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $path API endpoint path (e.g., '/order')
     * @param string $body Request body as JSON string (can be empty for GET requests)
     * @return string HMAC-SHA256 signature in hexadecimal format
     */
    private function createSignature($timestamp, $method, $path, $body = '') {
        // Create message string: timestamp + method + path + body
        $message = $timestamp . $method . $path . $body;
        
        // Create HMAC-SHA256 signature using API secret as key
        $signature = hash_hmac('sha256', $message, $this->apiSecret);
        
        return $signature;
    }
    
    /**
     * Make authenticated request to Bitvavo API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint (without base URL)
     * @param array $params Request parameters
     * @param int $window Optional execution window in milliseconds
     * @return array Response data and status
     */
    public function request($method, $endpoint, $params = [], $window = 10000) {
        $timestamp = round(microtime(true) * 1000);
        $path = '/v2' . $endpoint;
        $url = $this->baseUrl . $endpoint;
        
        // Initialize cURL session
        $ch = curl_init();
        
        // Set common cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Prepare request body and headers
        $body = '';
        $headers = [
            'Content-Type: application/json'
        ];
        
        // Add authentication headers if credentials are available
        if ($this->apiKey && $this->apiSecret) {
            $headers[] = 'Bitvavo-Access-Key: ' . $this->apiKey;
            $headers[] = 'Bitvavo-Access-Timestamp: ' . $timestamp;
            $headers[] = 'Bitvavo-Access-Window: ' . $window;
        }
        
        // Handle different HTTP methods
        switch ($method) {
            case 'GET':
                if (!empty($params)) {
                    $queryString = http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . $queryString);
                    $path .= '?' . $queryString;
                }
                break;
                
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($params)) {
                    $body = json_encode($params);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if (!empty($params)) {
                    $body = json_encode($params);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                break;
                
            default:
                throw new Exception("Unsupported HTTP method: $method");
        }
        
        // Create signature and add to headers if credentials are available
        if ($this->apiKey && $this->apiSecret) {
            $signature = $this->createSignature($timestamp, $method, $path, $body);
            $headers[] = 'Bitvavo-Access-Signature: ' . $signature;
        }
        
        // Set request headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => "cURL Error: $error",
                'httpCode' => 0
            ];
        }
        
        curl_close($ch);
        
        // Parse response
        $result = json_decode($response, true);
        
        // Handle API errors
        if ($httpCode >= 400) {
            $errorMessage = isset($result['error']) ? $result['error'] : 'Unknown error';
            
            // Handle rate limit errors specially
            if ($httpCode === 429) {
                return [
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'httpCode' => $httpCode,
                    'retryAfter' => isset($result['retryAfter']) ? $result['retryAfter'] : 60
                ];
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'httpCode' => $httpCode
            ];
        }
        
        // Return successful response
        return [
            'success' => true,
            'data' => $result,
            'httpCode' => $httpCode
        ];
    }
    
    /**
     * Get ticker prices for all markets or specific symbols
     * 
     * @param array $symbols Optional array of symbols to filter (e.g., ['BTC', 'ETH'])
     * @param string $market Optional market currency (e.g., 'EUR', 'USD')
     * @return array Prices indexed by symbol
     */
    public function getTickerPrices($symbols = [], $market = 'EUR') {
        try {
            // Get all ticker prices
            $result = $this->request('GET', '/ticker/price');
            
            if (!$result['success']) {
                error_log("Error fetching Bitvavo ticker prices: " . ($result['error'] ?? 'Unknown error'));
                return [];
            }
            
            $allPrices = $result['data'];
            $filteredPrices = [];
            
            foreach ($allPrices as $price) {
                $marketPair = $price['market'];
                list($symbol, $currency) = explode('-', $marketPair);
                
                // Filter by market currency if specified
                if ($market && $currency !== $market) {
                    continue;
                }
                
                // Filter by symbols if specified
                if (!empty($symbols) && !in_array($symbol, $symbols)) {
                    continue;
                }
                
                $filteredPrices[$symbol] = [
                    'price' => floatval($price['price']),
                    'currency' => $currency,
                    'market' => $marketPair
                ];
            }
            
            return $filteredPrices;
            
        } catch (Exception $e) {
            error_log("Exception in getTickerPrices: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current price for a specific symbol
     * 
     * @param string $symbol Coin symbol (e.g., 'BTC')
     * @param string $market Market currency (e.g., 'EUR')
     * @return float|null Current price or null if not found
     */
    public function getPrice($symbol, $market = 'EUR') {
        $marketPair = $symbol . '-' . $market;
        
        try {
            $result = $this->request('GET', '/ticker/price/' . $marketPair);
            
            if (!$result['success']) {
                error_log("Error fetching Bitvavo price for $marketPair: " . ($result['error'] ?? 'Unknown error'));
                return null;
            }
            
            return floatval($result['data']['price']);
            
        } catch (Exception $e) {
            error_log("Exception in getPrice for $marketPair: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Place a market order
     * 
     * @param string $symbol Coin symbol (e.g., 'BTC')
     * @param string $side Order side ('buy' or 'sell')
     * @param float $amount Amount to buy/sell
     * @param string $market Market currency (e.g., 'EUR')
     * @return array Order result
     */
    public function placeMarketOrder($symbol, $side, $amount, $market = 'EUR') {
        if (!$this->apiKey || !$this->apiSecret) {
            return [
                'success' => false,
                'error' => 'API credentials not configured'
            ];
        }
        
        $marketPair = $symbol . '-' . $market;
        
        $params = [
            'market' => $marketPair,
            'side' => $side,
            'orderType' => 'market',
            'amount' => (string)$amount
        ];
        
        try {
            $result = $this->request('POST', '/order', $params);
            
            if (!$result['success']) {
                error_log("Error placing $side order for $marketPair: " . ($result['error'] ?? 'Unknown error'));
                return $result;
            }
            
            return [
                'success' => true,
                'order' => $result['data']
            ];
            
        } catch (Exception $e) {
            error_log("Exception in placeMarketOrder for $marketPair: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get account balance
     * 
     * @param string $symbol Optional symbol to filter
     * @return array Account balances
     */
    public function getBalance($symbol = null) {
        if (!$this->apiKey || !$this->apiSecret) {
            return [
                'success' => false,
                'error' => 'API credentials not configured'
            ];
        }
        
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        
        try {
            $result = $this->request('GET', '/balance', $params);
            
            if (!$result['success']) {
                error_log("Error fetching balance: " . ($result['error'] ?? 'Unknown error'));
                return $result;
            }
            
            return [
                'success' => true,
                'balances' => $result['data']
            ];
            
        } catch (Exception $e) {
            error_log("Exception in getBalance: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
