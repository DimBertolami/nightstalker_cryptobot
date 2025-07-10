<?php
/**
 * Night Stalker Binance Trader
 * 
 * Implementation of the Binance exchange trader using CCXT.
 */

require_once __DIR__ . '/BaseTrader.php';
require_once __DIR__ . '/../../vendor/autoload.php';

class BinanceTrader extends BaseTrader {
    /**
     * Constructor
     * 
     * @param string $apiKey API key
     * @param string $apiSecret API secret
     * @param array $additionalParams Additional parameters
     */
    public function __construct($apiKey, $apiSecret, $additionalParams = []) {
        $this->exchangeName = 'Binance';
        parent::__construct($apiKey, $apiSecret, $additionalParams);
    }
    
    /**
     * Initialize exchange
     */
    protected function initExchange() {
        try {
            // Create CCXT Binance instance
            $this->exchange = new \ccxt\binance([
                'apiKey' => $this->apiKey,
                'secret' => $this->apiSecret,
                'enableRateLimit' => true,
                'options' => [
                    'adjustForTimeDifference' => true
                ]
            ]);
            
            // Apply additional parameters if provided
            if (!empty($this->additionalParams)) {
                foreach ($this->additionalParams as $key => $value) {
                    $this->exchange->$key = $value;
                }
            }
            
            $this->logInfo("Binance trader initialized successfully");
            
        } catch (\Exception $e) {
            $this->logError("Failed to initialize Binance exchange: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get account balance
     * 
     * @return array Account balance
     */
    public function getBalance() {
        try {
            $balance = $this->exchange->fetch_balance();
            $this->logInfo("Balance fetched successfully");
            return $balance;
        } catch (\Exception $e) {
            $this->logError("Failed to fetch balance: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Place a buy order on the exchange
     * 
     * @param string $symbol Trading pair symbol
     * @param float $amount Amount to buy
     * @param float $price Price per unit (null for market orders)
     * @param string $type Order type (limit, market)
     * @param array $params Additional parameters
     * @return array Order details
     */
    protected function placeBuyOrder($symbol, $amount, $price, $type, $params) {
        try {
            // Place order on Binance
            $order = $this->exchange->create_order(
                $symbol,
                $type,
                'buy',
                $amount,
                $price,
                $params
            );
            
            $this->logInfo("Buy order placed successfully: " . json_encode($order));
            return $order;
        } catch (\Exception $e) {
            $this->logError("Failed to place buy order: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Place a sell order on the exchange
     * 
     * @param string $symbol Trading pair symbol
     * @param float $amount Amount to sell
     * @param float $price Price per unit (null for market orders)
     * @param string $type Order type (limit, market)
     * @param array $params Additional parameters
     * @return array Order details
     */
    protected function placeSellOrder($symbol, $amount, $price, $type, $params) {
        try {
            // Place order on Binance
            $order = $this->exchange->create_order(
                $symbol,
                $type,
                'sell',
                $amount,
                $price,
                $params
            );
            
            $this->logInfo("Sell order placed successfully: " . json_encode($order));
            return $order;
        } catch (\Exception $e) {
            $this->logError("Failed to place sell order: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get order status
     * 
     * @param string $id Order ID
     * @param string $symbol Trading pair symbol
     * @return array Order status
     */
    public function getOrderStatus($id, $symbol = null) {
        try {
            if (!$symbol) {
                throw new \Exception("Symbol is required for Binance order status");
            }
            
            $order = $this->exchange->fetch_order($id, $symbol);
            $this->logInfo("Order status fetched successfully for order $id");
            return $order;
        } catch (\Exception $e) {
            $this->logError("Failed to fetch order status: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cancel order
     * 
     * @param string $id Order ID
     * @param string $symbol Trading pair symbol
     * @return bool True if successful
     */
    public function cancelOrder($id, $symbol = null) {
        try {
            if (!$symbol) {
                throw new \Exception("Symbol is required for Binance cancel order");
            }
            
            $result = $this->exchange->cancel_order($id, $symbol);
            $this->logInfo("Order $id canceled successfully");
            return true;
        } catch (\Exception $e) {
            $this->logError("Failed to cancel order: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get ticker for a symbol
     * 
     * @param string $symbol Trading pair symbol
     * @return array Ticker data
     */
    public function getTicker($symbol) {
        try {
            $ticker = $this->exchange->fetch_ticker($symbol);
            return $ticker;
        } catch (\Exception $e) {
            $this->logError("Failed to fetch ticker for $symbol: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get order book for a symbol
     * 
     * @param string $symbol Trading pair symbol
     * @param int $limit Number of orders to fetch
     * @return array Order book
     */
    public function getOrderBook($symbol, $limit = 20) {
        try {
            $orderBook = $this->exchange->fetch_order_book($symbol, $limit);
            return $orderBook;
        } catch (\Exception $e) {
            $this->logError("Failed to fetch order book for $symbol: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get recent trades for a symbol
     * 
     * @param string $symbol Trading pair symbol
     * @param int $limit Number of trades to fetch
     * @return array Recent trades
     */
    public function getRecentTrades($symbol, $limit = 20) {
        try {
            $trades = $this->exchange->fetch_trades($symbol, null, $limit);
            return $trades;
        } catch (\Exception $e) {
            $this->logError("Failed to fetch recent trades for $symbol: " . $e->getMessage());
            throw $e;
        }
    }
}
