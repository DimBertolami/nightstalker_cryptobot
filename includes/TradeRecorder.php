<?php
/**
 * Night Stalker Trade Recorder
 * 
 * This class handles consistent trade recording across all exchanges.
 * It ensures that all trades are properly recorded in the database and
 * portfolio is updated accordingly, regardless of which exchange is used.
 */

class TradeRecorder {
    private $db;
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            $this->db = getDBConnection();
        } catch (Exception $e) {
            $this->logError("Failed to connect to database: " . $e->getMessage());
            throw $e;
        }
        
        // Initialize logger
        $this->initLogger();
    }
    
    /**
     * Initialize logger
     */
    private function initLogger() {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logger = function($level, $message) use ($logDir) {
            $date = date('Y-m-d H:i:s');
            $logFile = $logDir . '/trade_recorder.log';
            $logMessage = "[$date] [$level] $message" . PHP_EOL;
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        };
    }
    
    /**
     * Log info message
     */
    public function logInfo($message) {
        ($this->logger)('INFO', $message);
    }
    
    /**
     * Log error message
     */
    public function logError($message) {
        ($this->logger)('ERROR', $message);
    }
    
    /**
     * Record a buy trade
     * 
     * @param string $symbol Coin symbol (e.g. BTC)
     * @param float $amount Amount of coins bought
     * @param float $price Price per coin
     * @param string $exchange Exchange name
     * @param array $extraData Any extra data to store
     * @return int|bool Trade ID if successful, false otherwise
     */
    public function recordBuy($symbol, $amount, $price, $exchange = 'default', $extraData = []) {
        try {
            $this->logInfo("Recording buy: $amount $symbol at $price on $exchange");
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Get or create coin ID
            $coinId = $this->getCoinId($symbol);
            if (!$coinId) {
                throw new Exception("Failed to get coin ID for symbol: $symbol");
            }
            
            // Calculate total value
            $totalValue = $amount * $price;
            
            // Insert trade record
            $stmt = $this->db->prepare("
                INSERT INTO trades 
                    (coin_id, trade_type, amount, price, total_value, trade_time, exchange) 
                VALUES 
                    (?, 'buy', ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([$coinId, $amount, $price, $totalValue, $exchange]);
            $tradeId = $this->db->lastInsertId();
            
            // Update portfolio
            $this->updatePortfolioAfterBuy($coinId, $amount, $price);
            
            // Store extra data if provided
            if (!empty($extraData) && $tradeId) {
                $this->storeTradeExtraData($tradeId, $extraData);
            }
            
            // Commit transaction
            $this->db->commit();
            
            $this->logInfo("Buy recorded successfully. Trade ID: $tradeId");
            return $tradeId;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logError("Failed to record buy: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record a sell trade
     * 
     * @param string $symbol Coin symbol (e.g. BTC)
     * @param float $amount Amount of coins sold
     * @param float $price Price per coin
     * @param string $exchange Exchange name
     * @param array $extraData Any extra data to store
     * @return int|bool Trade ID if successful, false otherwise
     */
    public function recordSell($symbol, $amount, $price, $exchange = 'default', $extraData = []) {
        try {
            $this->logInfo("Recording sell: $amount $symbol at $price on $exchange");
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Get coin ID
            $coinId = $this->getCoinId($symbol);
            if (!$coinId) {
                throw new Exception("Failed to get coin ID for symbol: $symbol");
            }
            
            // Check if we have enough coins to sell
            $portfolio = $this->getPortfolioPosition($coinId);
            if (!$portfolio || $portfolio['amount'] < $amount) {
                throw new Exception("Not enough $symbol in portfolio to sell. Have: " . 
                    ($portfolio ? $portfolio['amount'] : 0) . ", Trying to sell: $amount");
            }
            
            // Calculate total value
            $totalValue = $amount * $price;
            
            // Insert trade record
            $stmt = $this->db->prepare("
                INSERT INTO trades 
                    (coin_id, trade_type, amount, price, total_value, trade_time, exchange) 
                VALUES 
                    (?, 'sell', ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([$coinId, $amount, $price, $totalValue, $exchange]);
            $tradeId = $this->db->lastInsertId();
            
            // Update portfolio
            $this->updatePortfolioAfterSell($coinId, $amount);
            
            // Store extra data if provided
            if (!empty($extraData) && $tradeId) {
                $this->storeTradeExtraData($tradeId, $extraData);
            }
            
            // Commit transaction
            $this->db->commit();
            
            $this->logInfo("Sell recorded successfully. Trade ID: $tradeId");
            return $tradeId;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logError("Failed to record sell: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get coin ID from symbol
     * 
     * @param string $symbol Coin symbol
     * @return string|bool Coin ID if found, false otherwise
     */
    private function getCoinId($symbol) {
        try {
            // First check cryptocurrencies table
            $stmt = $this->db->prepare("
                SELECT id FROM cryptocurrencies WHERE symbol = ?
            ");
            $stmt->execute([$symbol]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // Then check coins table
            $stmt = $this->db->prepare("
                SELECT id FROM coins WHERE symbol = ?
            ");
            $stmt->execute([$symbol]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // If not found, create a new entry in cryptocurrencies
            $coinId = 'COIN_' . strtoupper($symbol);
            $stmt = $this->db->prepare("
                INSERT INTO cryptocurrencies 
                    (id, symbol, name, price, created_at) 
                VALUES 
                    (?, ?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE 
                    symbol = VALUES(symbol)
            ");
            $stmt->execute([$coinId, $symbol, $symbol]);
            
            return $coinId;
            
        } catch (Exception $e) {
            $this->logError("Failed to get coin ID for $symbol: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update portfolio after buy
     * 
     * @param string $coinId Coin ID
     * @param float $amount Amount bought
     * @param float $price Price per coin
     * @return bool True if successful, false otherwise
     */
    private function updatePortfolioAfterBuy($coinId, $amount, $price) {
        try {
            // Check if we already have this coin in portfolio
            $portfolio = $this->getPortfolioPosition($coinId);
            
            if ($portfolio) {
                // Update existing position
                $newAmount = $portfolio['amount'] + $amount;
                $newAvgPrice = (($portfolio['amount'] * $portfolio['avg_buy_price']) + ($amount * $price)) / $newAmount;
                
                $stmt = $this->db->prepare("
                    UPDATE portfolio 
                    SET amount = ?, avg_buy_price = ? 
                    WHERE user_id = 1 AND coin_id = ?
                ");
                $stmt->execute([$newAmount, $newAvgPrice, $coinId]);
            } else {
                // Create new position
                $stmt = $this->db->prepare("
                    INSERT INTO portfolio 
                        (user_id, coin_id, amount, avg_buy_price) 
                    VALUES 
                        (1, ?, ?, ?)
                ");
                $stmt->execute([$coinId, $amount, $price]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Failed to update portfolio after buy: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update portfolio after sell
     * 
     * @param string $coinId Coin ID
     * @param float $amount Amount sold
     * @return bool True if successful, false otherwise
     */
    private function updatePortfolioAfterSell($coinId, $amount) {
        try {
            // Get current position
            $portfolio = $this->getPortfolioPosition($coinId);
            
            if (!$portfolio) {
                throw new Exception("No portfolio position found for coin ID: $coinId");
            }
            
            // Calculate new amount
            $newAmount = $portfolio['amount'] - $amount;
            
            if ($newAmount <= 0) {
                // Remove position if no coins left
                $stmt = $this->db->prepare("
                    DELETE FROM portfolio 
                    WHERE user_id = 1 AND coin_id = ?
                ");
                $stmt->execute([$coinId]);
            } else {
                // Update position
                $stmt = $this->db->prepare("
                    UPDATE portfolio 
                    SET amount = ? 
                    WHERE user_id = 1 AND coin_id = ?
                ");
                $stmt->execute([$newAmount, $coinId]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Failed to update portfolio after sell: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get portfolio position for a coin
     * 
     * @param string $coinId Coin ID
     * @return array|bool Position data if found, false otherwise
     */
    private function getPortfolioPosition($coinId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM portfolio 
                WHERE user_id = 1 AND coin_id = ?
            ");
            $stmt->execute([$coinId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Failed to get portfolio position: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store extra data for a trade
     * 
     * @param int $tradeId Trade ID
     * @param array $extraData Extra data to store
     * @return bool True if successful, false otherwise
     */
    private function storeTradeExtraData($tradeId, $extraData) {
        try {
            // Check if trade_meta table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'trade_meta'");
            if ($stmt->rowCount() == 0) {
                // Create table if it doesn't exist
                $this->db->exec("
                    CREATE TABLE trade_meta (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        trade_id INT NOT NULL,
                        meta_key VARCHAR(255) NOT NULL,
                        meta_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX (trade_id),
                        INDEX (meta_key)
                    )
                ");
            }
            
            // Store each extra data item
            foreach ($extraData as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO trade_meta 
                        (trade_id, meta_key, meta_value) 
                    VALUES 
                        (?, ?, ?)
                ");
                $stmt->execute([$tradeId, $key, $value]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Failed to store trade extra data: " . $e->getMessage());
            return false;
        }
    }
}
