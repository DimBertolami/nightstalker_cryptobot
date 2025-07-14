<?php
/**
 * PriceMonitor Class
 * 
 * Monitors prices of portfolio coins and triggers automatic selling
 * when price drops for 30 consecutive seconds below the all-time high.
 */

class PriceMonitor {
    private $db;
    private $lastPrices = [];
    private $highestPrices = [];
    private $belowHighCounters = [];
    private $consecutiveThreshold = 10; // 10 measurements = 30 seconds (at 3-second intervals)
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
        $this->ensurePriceHistoryTable();
    }
    
    /**
     * Ensure price_history table exists
     */
    private function ensurePriceHistoryTable() {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS price_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coin_id VARCHAR(50) NOT NULL,
                price DECIMAL(20,8) NOT NULL,
                volume DECIMAL(30,2) DEFAULT 0,
                market_cap DECIMAL(30,2) DEFAULT 0,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_coin_id (coin_id),
                INDEX idx_recorded_at (recorded_at)
            )");
        } catch (PDOException $e) {
            error_log("Failed to create price_history table: " . $e->getMessage());
        }
    }
    
    /**
     * Get all coins in the portfolio
     * 
     * @return array Portfolio coins
     */
    public function getPortfolioCoins() {
        try {
            $query = "SELECT 
                portfolio.coin_id, 
                COALESCE(c.symbol, cr.symbol) as symbol,
                portfolio.amount,
                portfolio.avg_buy_price,
                COALESCE(c.price, cr.price) as current_price
            FROM portfolio 
            LEFT JOIN coins c ON portfolio.coin_id = c.id
            LEFT JOIN cryptocurrencies cr ON portfolio.coin_id = cr.id
            WHERE portfolio.amount > 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get portfolio coins: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current price for a coin
     * 
     * @param string $symbol Coin symbol
     * @return float|null Current price or null if not found
     */
    public function getCurrentPrice($symbol) {
        try {
            // First try to get price from coins table
            $stmt = $this->db->prepare("SELECT price FROM coins WHERE symbol = :symbol LIMIT 1");
            $stmt->bindParam(':symbol', $symbol);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['price'])) {
                return floatval($result['price']);
            }
            
            // If not found in coins, try cryptocurrencies table
            $stmt = $this->db->prepare("SELECT price FROM cryptocurrencies WHERE symbol = :symbol LIMIT 1");
            $stmt->bindParam(':symbol', $symbol);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['price'])) {
                return floatval($result['price']);
            }
            
            // If using Bitvavo API, could add API call here
            
            return null;
        } catch (PDOException $e) {
            error_log("Failed to get current price for $symbol: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Record price history for a coin
     * 
     * @param string $coinId Coin ID
     * @param float $price Current price
     * @param float $volume Trading volume (optional)
     * @param float $marketCap Market cap (optional)
     * @return bool Success status
     */
    public function recordPriceHistory($coinId, $price, $volume = 0, $marketCap = 0) {
        try {
            $stmt = $this->db->prepare("INSERT INTO price_history 
                (coin_id, price, volume, market_cap) 
                VALUES (:coin_id, :price, :volume, :market_cap)");
            
            $stmt->bindParam(':coin_id', $coinId);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':volume', $volume);
            $stmt->bindParam(':market_cap', $marketCap);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to record price history: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get highest recorded price for a coin
     * 
     * @param string $coinId Coin ID
     * @return float Highest price
     */
    public function getHighestPrice($coinId) {
        try {
            $stmt = $this->db->prepare("SELECT MAX(price) as highest_price 
                FROM price_history 
                WHERE coin_id = :coin_id");
            
            $stmt->bindParam(':coin_id', $coinId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['highest_price'])) {
                return floatval($result['highest_price']);
            }
            
            return 0;
        } catch (PDOException $e) {
            error_log("Failed to get highest price for $coinId: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if price has been below highest for consecutive measurements
     * 
     * @param string $coinId Coin ID
     * @param float $currentPrice Current price
     * @param float $highestPrice Highest recorded price
     * @return bool True if should sell
     */
    public function checkSellCondition($coinId, $currentPrice, $highestPrice) {
        // Initialize counter if not exists
        if (!isset($this->belowHighCounters[$coinId])) {
            $this->belowHighCounters[$coinId] = 0;
        }
        
        // If current price is at or above highest, reset counter
        if ($currentPrice >= $highestPrice) {
            $this->belowHighCounters[$coinId] = 0;
            return false;
        }
        
        // Increment counter if price is below highest
        $this->belowHighCounters[$coinId]++;
        
        // Log the counter status
        error_log("Coin $coinId: Price $currentPrice is below highest $highestPrice for {$this->belowHighCounters[$coinId]} consecutive checks");
        
        // Check if we've reached the threshold
        if ($this->belowHighCounters[$coinId] >= $this->consecutiveThreshold) {
            // Reset counter after triggering sell
            $this->belowHighCounters[$coinId] = 0;
            return true;
        }
        
        return false;
    }
    
    /**
     * Execute sell order
     * 
     * @param string $coinId Coin ID
     * @param string $symbol Coin symbol
     * @param float $price Current price
     * @return array Result of the sell operation
     */
    public function sellCoin($coinId, $symbol, $price) {
        try {
            // Prepare POST data for execute-trade.php
            $postData = [
                'action' => 'sell',
                'coin_id' => $coinId,
                'symbol' => $symbol,
                'amount' => 'all',
                'price' => $price,
                'strategy' => 'auto_price_monitor'
            ];
            
            // Create cURL request to execute-trade.php
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/NS/api/execute-trade.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log("Failed to sell coin $symbol: HTTP code $httpCode, Response: $response");
                return [
                    'success' => false,
                    'message' => "Failed to sell $symbol: HTTP error $httpCode"
                ];
            }
            
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['success']) || !$result['success']) {
                $errorMessage = isset($result['message']) ? $result['message'] : 'Unknown error';
                error_log("Failed to sell coin $symbol: $errorMessage");
                return [
                    'success' => false,
                    'message' => "Failed to sell $symbol: $errorMessage"
                ];
            }
            
            // Log successful sell
            error_log("Successfully sold $symbol at price $price: " . $result['message']);
            
            return [
                'success' => true,
                'message' => $result['message'],
                'data' => isset($result['data']) ? $result['data'] : []
            ];
        } catch (Exception $e) {
            error_log("Exception while selling coin $symbol: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Exception: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate profit/loss for a sell
     * 
     * @param float $buyPrice Average buy price
     * @param float $sellPrice Sell price
     * @param float $amount Amount sold
     * @return array Profit/loss information
     */
    public function calculateProfitLoss($buyPrice, $sellPrice, $amount) {
        $buyValue = $buyPrice * $amount;
        $sellValue = $sellPrice * $amount;
        $profitLoss = $sellValue - $buyValue;
        $profitLossPercent = $buyValue > 0 ? ($profitLoss / $buyValue) * 100 : 0;
        
        return [
            'buy_value' => $buyValue,
            'sell_value' => $sellValue,
            'profit_loss' => $profitLoss,
            'profit_loss_percent' => $profitLossPercent
        ];
    }
    
    /**
     * Monitor prices for all portfolio coins
     * 
     * @return array Results of monitoring cycle
     */
    public function monitorPrices() {
        $results = [];
        $coins = $this->getPortfolioCoins();
        
        foreach ($coins as $coin) {
            $coinId = $coin['coin_id'];
            $symbol = $coin['symbol'];
            
            // Get current price
            $currentPrice = $this->getCurrentPrice($symbol);
            
            if ($currentPrice === null) {
                $results[$symbol] = [
                    'success' => false,
                    'message' => "Could not get current price for $symbol"
                ];
                continue;
            }
            
            // Record price history
            $this->recordPriceHistory($coinId, $currentPrice);
            
            // Get highest price from database or use from memory if available
            if (!isset($this->highestPrices[$coinId])) {
                $highestPrice = $this->getHighestPrice($coinId);
                $this->highestPrices[$coinId] = $highestPrice > 0 ? $highestPrice : $currentPrice;
            }
            
            // Update highest price if current price is higher
            if ($currentPrice > $this->highestPrices[$coinId]) {
                $this->highestPrices[$coinId] = $currentPrice;
            }
            
            // Check if we should sell
            $shouldSell = $this->checkSellCondition($coinId, $currentPrice, $this->highestPrices[$coinId]);
            
            if ($shouldSell) {
                // Execute sell
                $sellResult = $this->sellCoin($coinId, $symbol, $currentPrice);
                
                if ($sellResult['success']) {
                    // Calculate profit/loss
                    $profitLoss = $this->calculateProfitLoss(
                        $coin['avg_buy_price'],
                        $currentPrice,
                        $coin['amount']
                    );
                    
                    $results[$symbol] = [
                        'success' => true,
                        'action' => 'sell',
                        'message' => "Sold $symbol: price below highest for 30+ seconds",
                        'current_price' => $currentPrice,
                        'highest_price' => $this->highestPrices[$coinId],
                        'profit_loss' => $profitLoss
                    ];
                } else {
                    $results[$symbol] = $sellResult;
                }
            } else {
                $results[$symbol] = [
                    'success' => true,
                    'action' => 'monitor',
                    'current_price' => $currentPrice,
                    'highest_price' => $this->highestPrices[$coinId],
                    'below_high_count' => isset($this->belowHighCounters[$coinId]) ? $this->belowHighCounters[$coinId] : 0
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Check if coin fetching is enabled
     * 
     * @return bool True if fetching is enabled
     */
    public static function isFetchingEnabled() {
        try {
            // Query the settings table or wherever the setting is stored
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT value FROM settings WHERE name = 'enable_fetching' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['value'])) {
                return $result['value'] == '1';
            }
            
            // Default to enabled if setting not found
            return true;
        } catch (Exception $e) {
            error_log("Error checking if fetching is enabled: " . $e->getMessage());
            return true; // Default to enabled on error
        }
    }
}
