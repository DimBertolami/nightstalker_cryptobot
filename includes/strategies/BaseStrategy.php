<?php
/**
 * Base Strategy Class
 * 
 * Abstract base class for all trading strategies in the Night Stalker cryptobot
 * Provides common functionality for strategy execution and integration with trader classes
 */

namespace NightStalker\Strategies;

abstract class BaseStrategy {
    protected $db;
    protected $config;
    protected $trader;
    protected $name;
    protected $logger;
    
    /**
     * Constructor
     * 
     * @param array $config Strategy configuration
     * @param object $trader Trader instance (Binance, Bitvavo, etc.)
     * @param object $db Database connection
     */
    public function __construct($config, $trader, $db) {
        $this->config = $config;
        $this->trader = $trader;
        $this->db = $db;
        $this->logger = $this->initLogger();
    }
    
    /**
     * Initialize logger
     * 
     * @return object Logger instance
     */
    protected function initLogger() {
        // Simple file logger
        return function($message, $level = 'info') {
            $logFile = '/opt/lampp/htdocs/NS/logs/strategy-' . date('Y-m-d') . '.log';
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[$timestamp] [$level] [{$this->name}] $message" . PHP_EOL;
            file_put_contents($logFile, $formattedMessage, FILE_APPEND);
        };
    }
    
    /**
     * Execute the strategy
     * 
     * @return array Results of strategy execution
     */
    public function execute() {
        $this->log("Starting execution of {$this->name} strategy");
        
        try {
            // Check if trader is connected
            if (!$this->trader->isConnected()) {
                throw new \Exception("Trader is not connected");
            }
            
            // Get signals from the strategy
            $signals = $this->getSignals();
            $this->log("Generated " . count($signals) . " trading signals");
            
            // Execute trades based on signals
            $trades = $this->executeTrades($signals);
            
            $this->log("Strategy execution completed successfully with " . count($trades) . " trades");
            
            return [
                'success' => true,
                'strategy' => $this->name,
                'message' => "Successfully executed " . count($trades) . " trades",
                'trades' => $trades
            ];
        } catch (\Exception $e) {
            $this->log("Strategy execution failed: " . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'strategy' => $this->name,
                'message' => "Strategy execution failed: " . $e->getMessage(),
                'trades' => []
            ];
        }
    }
    
    /**
     * Execute trades based on signals
     * 
     * @param array $signals Trading signals
     * @return array Executed trades
     */
    protected function executeTrades($signals) {
        $trades = [];
        
        foreach ($signals as $signal) {
            try {
                $symbol = $signal['symbol'];
                $action = $signal['action'];
                $amount = $signal['amount'];
                $price = $signal['price'];
                
                $this->log("Executing $action signal for $symbol, amount: $amount, price: $price");
                
                // Check if we have enough balance for buy orders
                if ($action === 'buy') {
                    $balance = $this->trader->getBalance('USDT');
                    $requiredFunds = $amount * $price;
                    
                    if ($balance < $requiredFunds) {
                        $this->log("Insufficient balance for $symbol buy order. Required: $requiredFunds USDT, Available: $balance USDT", 'warning');
                        continue;
                    }
                }
                
                // Check if we have enough of the asset for sell orders
                if ($action === 'sell') {
                    $balance = $this->trader->getBalance($symbol);
                    
                    if ($balance < $amount) {
                        $this->log("Insufficient balance for $symbol sell order. Required: $amount $symbol, Available: $balance $symbol", 'warning');
                        continue;
                    }
                }
                
                // Place the order
                $order = $this->trader->placeOrder($symbol, $action, $amount, $price);
                
                // Log the trade
                $this->logTrade($symbol, $action, $amount, $price, $order['id']);
                
                $trades[] = [
                    'symbol' => $symbol,
                    'action' => $action,
                    'amount' => $amount,
                    'price' => $price,
                    'order_id' => $order['id'],
                    'status' => 'success',
                    'total' => $amount * $price
                ];
                
                $this->log("Successfully placed $action order for $symbol, order ID: {$order['id']}");
            } catch (\Exception $e) {
                $this->log("Failed to execute trade for {$signal['symbol']}: " . $e->getMessage(), 'error');
            }
        }
        
        return $trades;
    }
    
    /**
     * Log a trade to the database
     * 
     * @param string $symbol Coin symbol
     * @param string $action Trade action (buy/sell)
     * @param float $amount Trade amount
     * @param float $price Trade price
     * @param string $orderId Exchange order ID
     */
    protected function logTrade($symbol, $action, $amount, $price, $orderId) {
        try {
            // Check if trades table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'trades'");
            $stmt->execute();
            $hasTradesTable = $stmt->rowCount() > 0;
            
            if (!$hasTradesTable) {
                // Create trades table
                $this->db->exec("CREATE TABLE trades (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    exchange VARCHAR(50) NOT NULL,
                    symbol VARCHAR(20) NOT NULL,
                    action VARCHAR(10) NOT NULL,
                    amount DECIMAL(18,8) NOT NULL,
                    price DECIMAL(18,8) NOT NULL,
                    order_id VARCHAR(100),
                    strategy VARCHAR(50) NOT NULL,
                    status VARCHAR(20) DEFAULT 'executed',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
            
            // Insert trade record
            $stmt = $this->db->prepare("INSERT INTO trades (exchange, symbol, action, amount, price, order_id, strategy) 
                VALUES (:exchange, :symbol, :action, :amount, :price, :order_id, :strategy)");
                
            $exchange = get_class($this->trader);
            $stmt->bindParam(':exchange', $exchange);
            $stmt->bindParam(':symbol', $symbol);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->bindParam(':strategy', $this->name);
            $stmt->execute();
            
            $this->log("Trade logged to database successfully");
        } catch (\Exception $e) {
            $this->log("Failed to log trade to database: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Log a message
     * 
     * @param string $message Message to log
     * @param string $level Log level
     */
    protected function log($message, $level = 'info') {
        $logger = $this->logger;
        $logger($message, $level);
    }
    
    /**
     * Get trading signals
     * 
     * @return array Trading signals
     */
    abstract public function getSignals();
    
    /**
     * Get strategy name
     * 
     * @return string Strategy name
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * Get strategy configuration
     * 
     * @return array Strategy configuration
     */
    public function getConfig() {
        return $this->config;
    }
}
