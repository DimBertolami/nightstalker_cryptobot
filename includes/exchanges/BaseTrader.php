<?php
/**
 * Night Stalker Base Trader
 * 
 * This is the base class for all exchange traders.
 * It provides common functionality and ensures consistent trade recording.
 */

require_once __DIR__ . '/../TradeRecorder.php';

abstract class BaseTrader {
    protected $exchange;
    protected $exchangeName;
    protected $apiKey;
    protected $apiSecret;
    protected $additionalParams;
    protected $tradeRecorder;
    protected $logger;
    
    /**
     * Constructor
     * 
     * @param string $apiKey API key
     * @param string $apiSecret API secret
     * @param array $additionalParams Additional parameters for the exchange
     */
    public function __construct($apiKey, $apiSecret, $additionalParams = []) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->additionalParams = $additionalParams;
        $this->tradeRecorder = new TradeRecorder();
        
        // Initialize logger
        $this->initLogger();
        
        // Initialize exchange
        $this->initExchange();
    }
    
    /**
     * Initialize logger
     */
    protected function initLogger() {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logger = function($level, $message) use ($logDir) {
            $date = date('Y-m-d H:i:s');
            $logFile = $logDir . '/exchange_' . strtolower($this->exchangeName) . '.log';
            $logMessage = "[$date] [$level] $message" . PHP_EOL;
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        };
    }
    
    /**
     * Log info message
     */
    protected function logInfo($message) {
        ($this->logger)('INFO', $message);
    }
    
    /**
     * Log error message
     */
    protected function logError($message) {
        ($this->logger)('ERROR', $message);
    }
    
    /**
     * Initialize exchange
     * This method should be implemented by child classes
     */
    abstract protected function initExchange();
    
    /**
     * Get account balance
     * 
     * @return array Account balance
     */
    abstract public function getBalance();
    
    /**
     * Place a buy order
     * 
     * @param string $symbol Trading pair symbol (e.g. BTC/USDT)
     * @param float $amount Amount to buy
     * @param float $price Price per unit (null for market orders)
     * @param string $type Order type (limit, market)
     * @param array $params Additional parameters
     * @return array Order details
     */
    public function buy($symbol, $amount, $price = null, $type = 'limit', $params = []) {
        try {
            $this->logInfo("Placing buy order: $amount $symbol at " . ($price ?? 'market') . " ($type)");
            
            // Extract base symbol from trading pair (e.g. BTC from BTC/USDT)
            $baseSymbol = explode('/', $symbol)[0];
            
            // Place order on exchange
            $order = $this->placeBuyOrder($symbol, $amount, $price, $type, $params);
            
            if (!$order) {
                throw new Exception("Failed to place buy order on exchange");
            }
            
            // Record the trade in our database
            $actualPrice = $price ?? $order['price'] ?? 0;
            $actualAmount = $order['amount'] ?? $amount;
            $tradeId = $this->tradeRecorder->recordBuy(
                $baseSymbol,
                $actualAmount,
                $actualPrice,
                $this->exchangeName,
                [
                    'order_id' => $order['id'] ?? null,
                    'trading_pair' => $symbol,
                    'order_type' => $type,
                    'exchange_data' => $order
                ]
            );
            
            if (!$tradeId) {
                $this->logError("Order placed on exchange but failed to record in database");
            }
            
            return $order;
            
        } catch (Exception $e) {
            $this->logError("Buy order failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Place a sell order
     * 
     * @param string $symbol Trading pair symbol (e.g. BTC/USDT)
     * @param float $amount Amount to sell
     * @param float $price Price per unit (null for market orders)
     * @param string $type Order type (limit, market)
     * @param array $params Additional parameters
     * @return array Order details
     */
    public function sell($symbol, $amount, $price = null, $type = 'limit', $params = []) {
        try {
            $this->logInfo("Placing sell order: $amount $symbol at " . ($price ?? 'market') . " ($type)");
            
            // Extract base symbol from trading pair (e.g. BTC from BTC/USDT)
            $baseSymbol = explode('/', $symbol)[0];
            
            // Place order on exchange
            $order = $this->placeSellOrder($symbol, $amount, $price, $type, $params);
            
            if (!$order) {
                throw new Exception("Failed to place sell order on exchange");
            }
            
            // Record the trade in our database
            $actualPrice = $price ?? $order['price'] ?? 0;
            $actualAmount = $order['amount'] ?? $amount;
            $tradeId = $this->tradeRecorder->recordSell(
                $baseSymbol,
                $actualAmount,
                $actualPrice,
                $this->exchangeName,
                [
                    'order_id' => $order['id'] ?? null,
                    'trading_pair' => $symbol,
                    'order_type' => $type,
                    'exchange_data' => $order
                ]
            );
            
            if (!$tradeId) {
                $this->logError("Order placed on exchange but failed to record in database");
            }
            
            return $order;
            
        } catch (Exception $e) {
            $this->logError("Sell order failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Place a buy order on the exchange
     * This method should be implemented by child classes
     */
    abstract protected function placeBuyOrder($symbol, $amount, $price, $type, $params);
    
    /**
     * Place a sell order on the exchange
     * This method should be implemented by child classes
     */
    abstract protected function placeSellOrder($symbol, $amount, $price, $type, $params);
    
    /**
     * Get order status
     * 
     * @param string $id Order ID
     * @param string $symbol Trading pair symbol
     * @return array Order status
     */
    abstract public function getOrderStatus($id, $symbol = null);
    
    /**
     * Cancel order
     * 
     * @param string $id Order ID
     * @param string $symbol Trading pair symbol
     * @return bool True if successful
     */
    abstract public function cancelOrder($id, $symbol = null);
    
    /**
     * Get ticker for a symbol
     * 
     * @param string $symbol Trading pair symbol
     * @return array Ticker data
     */
    abstract public function getTicker($symbol);
    
    /**
     * Get exchange name
     * 
     * @return string Exchange name
     */
    public function getExchangeName() {
        return $this->exchangeName;
    }
}
