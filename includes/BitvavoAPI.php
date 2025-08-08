<?php
/**
 * BitvavoAPI Class
 * 
 * This class provides methods to interact with the Bitvavo API
 * for fetching market data and executing trades.
 */

class BitvavoAPI {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.bitvavo.com/v2';
    private $accessWindow = 10000; // Default window: 10 seconds
    
    /**
     * Constructor
     * 
     * @param string $apiKey Bitvavo API Key
     * @param string $apiSecret Bitvavo API Secret
     * @param int $accessWindow Optional, execution timeout in milliseconds
     */
    public function __construct($apiKey, $apiSecret, $accessWindow = 10000) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->accessWindow = $accessWindow;
    }
    
    /**
     * Create HMAC-SHA256 signature for API authentication
     * 
     * @param int $timestamp Unix timestamp in milliseconds
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path API endpoint path
     * @param string $body Request body (for POST requests)
     * @return string Hex-encoded signature
     */
    private function createSignature($timestamp, $method, $path, $body = '') {
        $string = $timestamp . $method . $path . $body;
        return hash_hmac('sha256', $string, $this->apiSecret);
    }
    
    /**
     * Make an API request to Bitvavo
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param array $params Request parameters
     * @param bool $isPrivate Whether this is a private API endpoint requiring authentication
     * @return array|null Response data or null on error
     */
    private function request($method, $endpoint, $params = [], $isPrivate = false) {
        $url = $this->baseUrl . $endpoint;
        $body = '';
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set up request based on method
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        } else if ($method === 'POST' && !empty($params)) {
            $body = json_encode($params);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        // Set common cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Set headers
        $headers = ['Content-Type: application/json'];
        
        // Add authentication headers for private endpoints
        if ($isPrivate) {
            $timestamp = round(microtime(true) * 1000);
            $signature = $this->createSignature($timestamp, $method, str_replace($this->baseUrl, '', $url), $body);
            
            $headers[] = 'Bitvavo-Access-Key: ' . $this->apiKey;
            $headers[] = 'Bitvavo-Access-Signature: ' . $signature;
            $headers[] = 'Bitvavo-Access-Timestamp: ' . $timestamp;
            $headers[] = 'Bitvavo-Access-Window: ' . $this->accessWindow;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            error_log('Bitvavo API cURL error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // Parse response
        $data = json_decode($response, true);
        
        // Check for API errors
        if ($httpCode !== 200) {
            $errorMessage = isset($data['error']) ? $data['error'] : 'Unknown error';
            error_log("Bitvavo API error: $errorMessage (HTTP $httpCode)");
            return null;
        }
        
        return $data;
    }
    
    /**
     * Get ticker prices for all markets or a specific market
     * 
     * @param string $market Optional, specific market (e.g., 'BTC-EUR')
     * @return array|null Array of ticker prices or null on error
     */
    public function getTickerPrices($market = null) {
        $params = [];
        if ($market) {
            $params['market'] = $market;
        }
        
        return $this->request('GET', '/ticker/price', $params);
    }
    
    /**
     * Get ticker prices for multiple symbols
     * 
     * @param array $symbols Array of coin symbols (e.g., ['BTC', 'ETH'])
     * @param string $currency Currency to price against (e.g., 'EUR')
     * @return array Associative array of symbol => price
     */
    public function getTickerPricesForSymbols($symbols, $currency = 'EUR') {
        $allPrices = $this->getTickerPrices();
        
        if (!$allPrices) {
            return [];
        }
        
        $filteredPrices = [];
        foreach ($allPrices as $price) {
            $market = $price['market'];
            list($symbol, $marketCurrency) = explode('-', $market);
            
            if (in_array($symbol, $symbols) && $marketCurrency === $currency) {
                $filteredPrices[$symbol] = $price['price'];
            }
        }
        
        return $filteredPrices;
    }
    
    /**
     * Get ticker book (highest buy and lowest sell prices)
     * 
     * @param string $market Optional, specific market (e.g., 'BTC-EUR')
     * @return array|null Array of ticker book data or null on error
     */
    public function getTickerBook($market = null) {
        $params = [];
        if ($market) {
            $params['market'] = $market;
        }
        
        return $this->request('GET', '/ticker/book', $params);
    }
    
    /**
     * Get available markets
     * 
     * @return array|null Array of market data or null on error
     */
    public function getMarkets() {
        return $this->request('GET', '/markets');
    }
    
    /**
     * Get account balance
     * 
     * @param string $symbol Optional, specific asset symbol
     * @return array|null Array of balance data or null on error
     */
    public function getBalance($symbol = null) {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        
        return $this->request('GET', '/balance', $params, true);
    }
    
    /**
     * Create a new order
     * 
     * @param array $orderParams Order parameters
     * @return array|null Order data or null on error
     */
    public function createOrder($orderParams) {
        return $this->request('POST', '/order', $orderParams, true);
    }
    
    /**
     * Create a market buy order
     * 
     * @param string $market Market symbol (e.g., 'BTC-EUR')
     * @param float $amount Amount to buy
     * @return array|null Order data or null on error
     */
    public function marketBuy($market, $amount) {
        $params = [
            'market' => $market,
            'side' => 'buy',
            'orderType' => 'market',
            'amount' => (string)$amount
        ];
        
        return $this->createOrder($params);
    }
    
    /**
     * Create a market sell order
     * 
     * @param string $market Market symbol (e.g., 'BTC-EUR')
     * @param float $amount Amount to sell
     * @return array|null Order data or null on error
     */
    public function marketSell($market, $amount) {
        $params = [
            'market' => $market,
            'side' => 'sell',
            'orderType' => 'market',
            'amount' => (string)$amount
        ];
        
        return $this->createOrder($params);
    }
    
    /**
     * Get open orders
     * 
     * @param string $market Optional, specific market
     * @return array|null Array of open orders or null on error
     */
    public function getOpenOrders($market = null) {
        $params = [];
        if ($market) {
            $params['market'] = $market;
        }
        
        return $this->request('GET', '/orders', $params, true);
    }
    
    /**
     * Get order by ID
     * 
     * @param string $orderId Order ID
     * @return array|null Order data or null on error
     */
    public function getOrder($orderId) {
        return $this->request('GET', '/order', ['orderId' => $orderId], true);
    }
    
    /**
     * Cancel order by ID
     * 
     * @param string $orderId Order ID
     * @return array|null Cancellation result or null on error
     */
    public function cancelOrder($orderId) {
        return $this->request('DELETE', '/order', ['orderId' => $orderId], true);
    }
    
    /**
     * Get trade history
     * 
     * @param string $market Optional, specific market
     * @param int $limit Optional, number of trades to return (default: 500, max: 1000)
     * @return array|null Array of trades or null on error
     */
    public function getTrades($market = null, $limit = 500) {
        $params = ['limit' => $limit];
        if ($market) {
            $params['market'] = $market;
        }
        
        return $this->request('GET', '/trades', $params, true);
    }
}
?>
