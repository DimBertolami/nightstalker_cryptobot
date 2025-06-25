<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * BitvavoTrader Class
 * Handles trading operations with Bitvavo exchange using CCXT
 */
class BitvavoTrader {
    private $exchange;
    private $apiKey;
    private $apiSecret;
    private $testMode;
    
    /**
     * Constructor
     * 
     * @param bool $testMode Whether to use simulation mode (doesn't execute real trades) or live trading
     */
    public function __construct($testMode = true) {
        $this->apiKey = BITVAVO_API_KEY;
        $this->apiSecret = BITVAVO_API_SECRET;
        $this->testMode = $testMode;
        
        try {
            // Initialize the Bitvavo exchange
            $this->exchange = new \ccxt\bitvavo([
                'apiKey' => $this->apiKey,
                'secret' => $this->apiSecret,
                'enableRateLimit' => true,
                'options' => [
                    'adjustForTimeDifference' => true,
                ]
            ]);
            
            // Note: Bitvavo doesn't support sandbox mode in CCXT
            // We'll use simulation mode instead (doesn't execute real trades)
            if ($this->testMode) {
                logEvent("Bitvavo trader initialized in SIMULATION mode (trades will not be executed)", 'info');
            } else {
                logEvent("Bitvavo trader initialized in LIVE mode", 'info');
            }
            
        } catch (\Exception $e) {
            logEvent("Error initializing Bitvavo trader: " . $e->getMessage(), 'error');
            throw new \Exception("Failed to initialize Bitvavo trader: " . $e->getMessage());
        }
    }
    
    /**
     * Get account balance
     * 
     * @return array Account balance information
     */
    public function getBalance() {
        try {
            $balance = $this->exchange->fetch_balance();
            logEvent("Successfully fetched balance from Bitvavo", 'info');
            return $balance;
        } catch (\Exception $e) {
            logEvent("Error fetching balance: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get available markets
     * 
     * @return array Available markets
     */
    public function getMarkets() {
        try {
            $markets = $this->exchange->load_markets();
            logEvent("Successfully loaded markets from Bitvavo", 'info');
            return $markets;
        } catch (\Exception $e) {
            logEvent("Error loading markets: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get ticker information for a specific symbol
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @return array Ticker information
     */
    public function getTicker($symbol) {
        try {
            $ticker = $this->exchange->fetch_ticker($symbol);
            return $ticker;
        } catch (\Exception $e) {
            logEvent("Error fetching ticker for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Place a market buy order
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @param float $amount Amount to buy
     * @return array Order information
     */
    public function marketBuy($symbol, $amount) {
        try {
            // In test mode, simulate the order instead of executing it
            if ($this->testMode) {
                // Get ticker to simulate price
                $ticker = $this->exchange->fetch_ticker($symbol);
                $price = $ticker['last'];
                $cost = $amount * $price;
                
                // Create a simulated order response
                $order = [
                    'id' => 'sim_' . uniqid(),
                    'symbol' => $symbol,
                    'type' => 'market',
                    'side' => 'buy',
                    'amount' => $amount,
                    'price' => $price,
                    'cost' => $cost,
                    'status' => 'closed',
                    'timestamp' => time() * 1000,
                    'datetime' => date('c'),
                    'simulated' => true
                ];
                
                logEvent("SIMULATION: Market buy order placed for {$amount} {$symbol} at {$price}", 'info');
                return $order;
            } else {
                // Execute real order
                $order = $this->exchange->create_market_buy_order($symbol, $amount);
                logEvent("Market buy order placed for {$amount} {$symbol}", 'info');
                return $order;
            }
        } catch (\Exception $e) {
            logEvent("Error placing market buy order: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Place a market sell order
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @param float $amount Amount to sell
     * @return array Order information
     */
    public function marketSell($symbol, $amount) {
        try {
            // In test mode, simulate the order instead of executing it
            if ($this->testMode) {
                // Get ticker to simulate price
                $ticker = $this->exchange->fetch_ticker($symbol);
                $price = $ticker['last'];
                $cost = $amount * $price;
                
                // Create a simulated order response
                $order = [
                    'id' => 'sim_' . uniqid(),
                    'symbol' => $symbol,
                    'type' => 'market',
                    'side' => 'sell',
                    'amount' => $amount,
                    'price' => $price,
                    'cost' => $cost,
                    'status' => 'closed',
                    'timestamp' => time() * 1000,
                    'datetime' => date('c'),
                    'simulated' => true
                ];
                
                logEvent("SIMULATION: Market sell order placed for {$amount} {$symbol} at {$price}", 'info');
                return $order;
            } else {
                // Execute real order
                $order = $this->exchange->create_market_sell_order($symbol, $amount);
                logEvent("Market sell order placed for {$amount} {$symbol}", 'info');
                return $order;
            }
        } catch (\Exception $e) {
            logEvent("Error placing market sell order: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Place a limit buy order
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @param float $amount Amount to buy
     * @param float $price Price to buy at
     * @return array Order information
     */
    public function limitBuy($symbol, $amount, $price) {
        try {
            // In test mode, simulate the order instead of executing it
            if ($this->testMode) {
                $cost = $amount * $price;
                
                // Create a simulated order response
                $order = [
                    'id' => 'sim_' . uniqid(),
                    'symbol' => $symbol,
                    'type' => 'limit',
                    'side' => 'buy',
                    'amount' => $amount,
                    'price' => $price,
                    'cost' => $cost,
                    'status' => 'open',
                    'timestamp' => time() * 1000,
                    'datetime' => date('c'),
                    'simulated' => true
                ];
                
                logEvent("SIMULATION: Limit buy order placed for {$amount} {$symbol} at price {$price}", 'info');
                return $order;
            } else {
                // Execute real order
                $order = $this->exchange->create_limit_buy_order($symbol, $amount, $price);
                logEvent("Limit buy order placed for {$amount} {$symbol} at price {$price}", 'info');
                return $order;
            }
        } catch (\Exception $e) {
            logEvent("Error placing limit buy order: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Place a limit sell order
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @param float $amount Amount to sell
     * @param float $price Price to sell at
     * @return array Order information
     */
    public function limitSell($symbol, $amount, $price) {
        try {
            // In test mode, simulate the order instead of executing it
            if ($this->testMode) {
                $cost = $amount * $price;
                
                // Create a simulated order response
                $order = [
                    'id' => 'sim_' . uniqid(),
                    'symbol' => $symbol,
                    'type' => 'limit',
                    'side' => 'sell',
                    'amount' => $amount,
                    'price' => $price,
                    'cost' => $cost,
                    'status' => 'open',
                    'timestamp' => time() * 1000,
                    'datetime' => date('c'),
                    'simulated' => true
                ];
                
                logEvent("SIMULATION: Limit sell order placed for {$amount} {$symbol} at price {$price}", 'info');
                return $order;
            } else {
                // Execute real order
                $order = $this->exchange->create_limit_sell_order($symbol, $amount, $price);
                logEvent("Limit sell order placed for {$amount} {$symbol} at price {$price}", 'info');
                return $order;
            }
        } catch (\Exception $e) {
            logEvent("Error placing limit sell order: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Cancel an order
     * 
     * @param string $id Order ID
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @return array Cancellation information
     */
    public function cancelOrder($id, $symbol = null) {
        try {
            // In test mode, simulate order cancellation
            if ($this->testMode && strpos($id, 'sim_') === 0) {
                $result = [
                    'id' => $id,
                    'status' => 'canceled',
                    'symbol' => $symbol,
                    'timestamp' => time() * 1000,
                    'datetime' => date('c'),
                    'simulated' => true
                ];
                
                logEvent("SIMULATION: Order {$id} cancelled", 'info');
                return $result;
            } else {
                // Execute real cancellation
                $result = $this->exchange->cancel_order($id, $symbol);
                logEvent("Order {$id} cancelled", 'info');
                return $result;
            }
        } catch (\Exception $e) {
            logEvent("Error cancelling order: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get order information
     * 
     * @param string $id Order ID
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @return array Order information
     */
    public function getOrder($id, $symbol = null) {
        try {
            $order = $this->exchange->fetch_order($id, $symbol);
            return $order;
        } catch (\Exception $e) {
            logEvent("Error fetching order {$id}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get open orders
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @return array Open orders
     */
    public function getOpenOrders($symbol = null) {
        try {
            $orders = $this->exchange->fetch_open_orders($symbol);
            return $orders;
        } catch (\Exception $e) {
            logEvent("Error fetching open orders: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get order history
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @return array Order history
     */
    public function getOrderHistory($symbol = null) {
        try {
            $history = $this->exchange->fetch_closed_orders($symbol);
            return $history;
        } catch (\Exception $e) {
            logEvent("Error fetching order history: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get trade history
     * 
     * @param string $symbol Trading pair symbol (e.g., 'BTC/EUR')
     * @return array Trade history
     */
    public function getTradeHistory($symbol) {
        try {
            $trades = $this->exchange->fetch_my_trades($symbol);
            return $trades;
        } catch (\Exception $e) {
            logEvent("Error fetching trade history for {$symbol}: " . $e->getMessage(), 'error');
            return ['error' => $e->getMessage()];
        }
    }
}
