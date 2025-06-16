<?php
require_once __DIR__ . '/BitvavoTrader.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/TradingLogger.php';

/**
 * NewCoinStrategy Class
 * Implements a trading strategy focused on newly listed cryptocurrencies
 * 
 * Strategy:
 * 1. Monitor for new coins (<24h old)
 * 2. Filter by marketcap (>$1.5M)
 * 3. Filter by volume (>$1.5M)
 * 4. Buy when all criteria met
 * 5. Monitor price 20x/minute
 * 6. Sell when price drops for 30 consecutive seconds
 * 7. Resume monitoring for new opportunities
 */
class NewCoinStrategy {
    private $trader;
    private $conn;
    private $testMode;
    private $logger;
    private $activeTrade = null;
    private $monitoringActive = false;
    private $priceHistory = [];
    private $lastBuyPrice = 0;
    private $highestPrice = 0;
    private $consecutiveDrops = 0;
    private $monitoringInterval = 3; // Check every 3 seconds (20x/minute)
    private $sellTriggerSeconds = 30; // Sell after 30 seconds of consecutive drops
    
    // Strategy criteria
    private $maxCoinAge = 24; // Hours
    private $minMarketCap = 1500000; // $1.5M
    private $minVolume = 1500000; // $1.5M
    
    /**
     * Constructor
     * 
     * @param bool $testMode Whether to use simulation mode or live trading
     */
    public function __construct($testMode = true) {
        $this->testMode = $testMode;
        $this->trader = new BitvavoTrader($testMode);
        $this->logger = new TradingLogger();
        
        // Connect to database
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            logEvent("Database connection failed: " . $this->conn->connect_error, 'error');
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
        
        // Initialize trading stats
        $this->logger->updateStats('new_coin_strategy', [
            'trades_executed' => 0,
            'successful_trades' => 0,
            'failed_trades' => 0,
            'total_profit' => 0,
            'win_rate' => 0,
            'avg_profit_percentage' => 0,
            'avg_holding_time' => 0
        ]);
    }
    
    /**
     * Find new coins that meet the criteria
     * 
     * @return array Array of coins that meet the criteria
     */
    public function findNewCoins() {
        try {
            // Calculate the timestamp for 24 hours ago
            $hoursAgo = date('Y-m-d H:i:s', strtotime("-{$this->maxCoinAge} hours"));
            
            // Query for new coins that meet the criteria
            $stmt = $this->conn->prepare("
                SELECT * FROM coins 
                WHERE date_added >= ? 
                AND market_cap >= ? 
                AND volume >= ? 
                ORDER BY date_added DESC
            ");
            
            $stmt->bind_param("sdd", $hoursAgo, $this->minMarketCap, $this->minVolume);
            $stmt->execute();
            $result = $stmt->get_result();
            $newCoins = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            logEvent("Found " . count($newCoins) . " new coins that meet the criteria", 'info');
            return $newCoins;
            
        } catch (Exception $e) {
            logEvent("Error finding new coins: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Execute the new coin strategy
     * 
     * @return array Results of the strategy execution
     */
    public function execute() {
        $results = [
            'coins_found' => 0,
            'trades_executed' => 0,
            'monitoring_active' => false,
            'details' => []
        ];
        
        try {
            // If we're already monitoring a coin, don't look for new ones
            if ($this->monitoringActive && $this->activeTrade) {
                $results['monitoring_active'] = true;
                $results['active_trade'] = [
                    'symbol' => $this->activeTrade['symbol'],
                    'buy_price' => $this->lastBuyPrice,
                    'current_price' => $this->getCurrentPrice($this->activeTrade['symbol']),
                    'highest_price' => $this->highestPrice,
                    'consecutive_drops' => $this->consecutiveDrops
                ];
                return $results;
            }
            
            // Find new coins that meet the criteria
            $newCoins = $this->findNewCoins();
            $results['coins_found'] = count($newCoins);
            
            if (empty($newCoins)) {
                logEvent("No new coins found that meet the criteria", 'info');
                return $results;
            }
            
            // Get account balance
            $balance = $this->trader->getBalance();
            if (isset($balance['error'])) {
                logEvent("Error fetching balance: " . $balance['error'], 'error');
                return $results;
            }
            
            // Get available markets
            $markets = $this->trader->getMarkets();
            if (isset($markets['error'])) {
                logEvent("Error fetching markets: " . $markets['error'], 'error');
                return $results;
            }
            
            // Process each new coin
            foreach ($newCoins as $coin) {
                $symbol = $coin['symbol'];
                $results['details'][] = [
                    'symbol' => $symbol,
                    'name' => $coin['name'],
                    'age' => $this->calculateCoinAge($coin['date_added']),
                    'market_cap' => $coin['market_cap'],
                    'volume' => $coin['volume']
                ];
                
                // Find appropriate trading pair (with EUR or USDT)
                $tradingPair = null;
                if (isset($markets["{$symbol}/EUR"])) {
                    $tradingPair = "{$symbol}/EUR";
                    $quoteCurrency = 'EUR';
                } elseif (isset($markets["{$symbol}/USDT"])) {
                    $tradingPair = "{$symbol}/USDT";
                    $quoteCurrency = 'USDT';
                } else {
                    logEvent("No suitable trading pair found for {$symbol}", 'warning');
                    continue;
                }
                
                // Get ticker information
                $ticker = $this->trader->getTicker($tradingPair);
                if (isset($ticker['error'])) {
                    logEvent("Error fetching ticker for {$tradingPair}: " . $ticker['error'], 'error');
                    continue;
                }
                
                // Check if we have enough balance
                $availableBalance = $balance['free'][$quoteCurrency] ?? 0;
                
                // Skip if available balance is too low
                if ($availableBalance < 10) { // Minimum 10 EUR/USDT
                    logEvent("Available balance too low for trading {$symbol}", 'info');
                    continue;
                }
                
                // Calculate trade amount (use 100% of available balance for this strategy)
                $tradeAmount = $availableBalance;
                $coinAmount = $tradeAmount / $ticker['last'];
                
                // Buy the coin
                if ($this->testMode) {
                    logEvent("TEST MODE: Would buy {$coinAmount} {$symbol} at {$ticker['last']} {$quoteCurrency}", 'info');
                    $order = [
                        'id' => 'sim_' . uniqid(),
                        'symbol' => $tradingPair,
                        'type' => 'market',
                        'side' => 'buy',
                        'amount' => $coinAmount,
                        'price' => $ticker['last'],
                        'cost' => $tradeAmount,
                        'status' => 'closed',
                        'timestamp' => time() * 1000,
                        'datetime' => date('c'),
                        'simulated' => true
                    ];
                } else {
                    $order = $this->trader->marketBuy($tradingPair, $coinAmount);
                    if (isset($order['error'])) {
                        logEvent("Error buying {$symbol}: " . $order['error'], 'error');
                        continue;
                    }
                }
                
                // Record the trade in database
                $this->recordTrade($symbol, 'buy', $order['amount'], $order['price'], $order['cost'], $quoteCurrency, $order['id']);
                
                // Log the buy event with detailed information
                $this->logger->logEvent('new_coin_strategy', 'buy', [
                    'symbol' => $symbol,
                    'trading_pair' => $tradingPair,
                    'price' => $order['price'],
                    'amount' => $order['amount'],
                    'cost' => $order['cost'],
                    'currency' => $quoteCurrency,
                    'order_id' => $order['id'],
                    'market_cap' => $coin['market_cap'],
                    'volume' => $coin['volume'],
                    'age_hours' => $this->calculateCoinAge($coin['date_added']),
                    'simulated' => $this->testMode
                ]);
                
                // Set up monitoring for this coin
                $this->activeTrade = [
                    'symbol' => $symbol,
                    'trading_pair' => $tradingPair,
                    'quote_currency' => $quoteCurrency,
                    'amount' => $order['amount'],
                    'order_id' => $order['id'],
                    'buy_time' => time()
                ];
                
                $this->lastBuyPrice = $order['price'];
                $this->highestPrice = $order['price'];
                $this->consecutiveDrops = 0;
                $this->monitoringActive = true;
                $this->priceHistory = [];
                
                // Update trading stats
                $stats = $this->logger->getStats('new_coin_strategy');
                $this->logger->updateStats('new_coin_strategy', [
                    'trades_executed' => ($stats['trades_executed'] ?? 0) + 1,
                    'active_trade_symbol' => $symbol,
                    'active_trade_buy_price' => $order['price'],
                    'active_trade_time' => date('Y-m-d H:i:s')
                ]);
                
                logEvent("Started monitoring {$symbol} for price movements", 'info');
                $results['trades_executed']++;
                $results['monitoring_active'] = true;
                $results['active_trade'] = [
                    'symbol' => $symbol,
                    'buy_price' => $this->lastBuyPrice,
                    'amount' => $order['amount'],
                    'quote_currency' => $quoteCurrency
                ];
                
                // Only buy one coin at a time for this strategy
                break;
            }
            
            return $results;
            
        } catch (Exception $e) {
            logEvent("Error executing new coin strategy: " . $e->getMessage(), 'error');
            return [
                'error' => $e->getMessage(),
                'coins_found' => $results['coins_found'],
                'trades_executed' => $results['trades_executed']
            ];
        }
    }
    
    /**
     * Monitor the price of the active trade
     * 
     * @return array Results of the monitoring
     */
    public function monitorPrice() {
        $results = [
            'monitoring_active' => $this->monitoringActive,
            'action_taken' => 'none',
            'details' => []
        ];
        
        if (!$this->monitoringActive || !$this->activeTrade) {
            return $results;
        }
        
        try {
            $symbol = $this->activeTrade['symbol'];
            $tradingPair = $this->activeTrade['trading_pair'];
            
            // Get current price
            $ticker = $this->trader->getTicker($tradingPair);
            if (isset($ticker['error'])) {
                logEvent("Error fetching ticker for {$tradingPair}: " . $ticker['error'], 'error');
                return $results;
            }
            
            $currentPrice = $ticker['last'];
            $timestamp = time();
            
            // Record price history
            $this->priceHistory[] = [
                'price' => $currentPrice,
                'timestamp' => $timestamp
            ];
            
            // Keep only the last 20 price points (1 minute at 3-second intervals)
            if (count($this->priceHistory) > 20) {
                array_shift($this->priceHistory);
            }
            
            // Update highest price if current price is higher
            if ($currentPrice > $this->highestPrice) {
                $this->highestPrice = $currentPrice;
                $this->consecutiveDrops = 0; // Reset consecutive drops counter
                logEvent("New highest price for {$symbol}: {$currentPrice}", 'info');
            }
            
            // Check if price is dropping from the highest
            if ($currentPrice < $this->highestPrice) {
                $this->consecutiveDrops++;
                $dropPercentage = (($this->highestPrice - $currentPrice) / $this->highestPrice) * 100;
                
                logEvent("Price drop detected for {$symbol}: {$currentPrice} ({$dropPercentage}% below peak, {$this->consecutiveDrops} consecutive drops)", 'info');
                
                // If price has been dropping for the specified duration, sell
                if ($this->consecutiveDrops >= ($this->sellTriggerSeconds / $this->monitoringInterval)) {
                    logEvent("Sell trigger activated for {$symbol} after {$this->consecutiveDrops} consecutive drops", 'info');
                    
                    // Sell the coin
                    if ($this->testMode) {
                        logEvent("TEST MODE: Would sell {$this->activeTrade['amount']} {$symbol} at {$currentPrice} {$this->activeTrade['quote_currency']}", 'info');
                        $order = [
                            'id' => 'sim_' . uniqid(),
                            'symbol' => $tradingPair,
                            'type' => 'market',
                            'side' => 'sell',
                            'amount' => $this->activeTrade['amount'],
                            'price' => $currentPrice,
                            'cost' => $this->activeTrade['amount'] * $currentPrice,
                            'status' => 'closed',
                            'timestamp' => time() * 1000,
                            'datetime' => date('c'),
                            'simulated' => true
                        ];
                    } else {
                        $order = $this->trader->marketSell($tradingPair, $this->activeTrade['amount']);
                        if (isset($order['error'])) {
                            logEvent("Error selling {$symbol}: " . $order['error'], 'error');
                            return $results;
                        }
                    }
                    
                    // Calculate profit/loss
                    $buyValue = $this->lastBuyPrice * $this->activeTrade['amount'];
                    $sellValue = $currentPrice * $this->activeTrade['amount'];
                    $profitLoss = $sellValue - $buyValue;
                    $profitPercentage = ($profitLoss / $buyValue) * 100;
                    $holdingTime = time() - $this->activeTrade['buy_time'];
                    
                    // Record the trade in database
                    $this->recordTrade($symbol, 'sell', $order['amount'], $order['price'], $order['cost'], $this->activeTrade['quote_currency'], $order['id']);
                    
                    // Log the sell event with detailed information
                    $this->logger->logEvent('new_coin_strategy', 'sell', [
                        'symbol' => $symbol,
                        'trading_pair' => $this->activeTrade['trading_pair'],
                        'buy_price' => $this->lastBuyPrice,
                        'sell_price' => $currentPrice,
                        'amount' => $this->activeTrade['amount'],
                        'cost' => $buyValue,
                        'proceeds' => $sellValue,
                        'profit' => $profitLoss,
                        'profit_percentage' => $profitPercentage,
                        'currency' => $this->activeTrade['quote_currency'],
                        'highest_price' => $this->highestPrice,
                        'holding_time_seconds' => $holdingTime,
                        'consecutive_drops' => $this->consecutiveDrops,
                        'buy_order_id' => $this->activeTrade['order_id'],
                        'sell_order_id' => $order['id'],
                        'simulated' => $this->testMode
                    ]);
                    
                    // Mark the buy trade as sold
                    $this->markTradeAsSold($this->activeTrade['order_id']);
                    
                    // Update trading stats
                    $stats = $this->logger->getStats('new_coin_strategy');
                    $successful = $profitLoss > 0 ? 1 : 0;
                    $failed = $profitLoss <= 0 ? 1 : 0;
                    
                    // Calculate new averages
                    $totalTrades = ($stats['successful_trades'] ?? 0) + ($stats['failed_trades'] ?? 0) + 1;
                    $newSuccessful = ($stats['successful_trades'] ?? 0) + $successful;
                    $newWinRate = ($totalTrades > 0) ? ($newSuccessful / $totalTrades) * 100 : 0;
                    
                    // Calculate new average profit percentage
                    $totalProfit = ($stats['total_profit'] ?? 0) + $profitLoss;
                    $avgProfitPercentage = ($stats['avg_profit_percentage'] ?? 0);
                    $newAvgProfitPercentage = (($avgProfitPercentage * ($totalTrades - 1)) + $profitPercentage) / $totalTrades;
                    
                    // Calculate new average holding time
                    $avgHoldingTime = ($stats['avg_holding_time'] ?? 0);
                    $newAvgHoldingTime = (($avgHoldingTime * ($totalTrades - 1)) + $holdingTime) / $totalTrades;
                    
                    // Update best/worst trade stats
                    $bestTradeProfit = ($stats['best_trade_profit'] ?? 0);
                    $worstTradeLoss = ($stats['worst_trade_loss'] ?? 0);
                    
                    if ($profitLoss > $bestTradeProfit) {
                        $bestTradeProfit = $profitLoss;
                    }
                    
                    if ($profitLoss < $worstTradeLoss) {
                        $worstTradeLoss = $profitLoss;
                    }
                    
                    $this->logger->updateStats('new_coin_strategy', [
                        'successful_trades' => $newSuccessful,
                        'failed_trades' => ($stats['failed_trades'] ?? 0) + $failed,
                        'total_profit' => $totalProfit,
                        'win_rate' => $newWinRate,
                        'avg_profit_percentage' => $newAvgProfitPercentage,
                        'avg_holding_time' => $newAvgHoldingTime,
                        'best_trade_profit' => $bestTradeProfit,
                        'worst_trade_loss' => $worstTradeLoss,
                        'active_trade_symbol' => null,
                        'active_trade_buy_price' => null,
                        'active_trade_time' => null
                    ]);
                    
                    // Reset monitoring
                    $this->monitoringActive = false;
                    $results['monitoring_active'] = false;
                    $results['action_taken'] = 'sell';
                    $results['details'] = [
                        'symbol' => $symbol,
                        'buy_price' => $this->lastBuyPrice,
                        'sell_price' => $currentPrice,
                        'amount' => $this->activeTrade['amount'],
                        'profit_loss' => $profitLoss,
                        'profit_percentage' => $profitPercentage,
                        'highest_price' => $this->highestPrice,
                        'holding_time' => $holdingTime
                    ];
                    
                    logEvent("Sold {$symbol} at {$currentPrice} ({$profitPercentage}% profit/loss)", 'info');
                    $this->activeTrade = null;
                }
            } else {
                // Reset consecutive drops if price is not dropping
                $this->consecutiveDrops = 0;
            }
            
            // Update results with current monitoring status
            if ($this->monitoringActive) {
                $results['details'] = [
                    'symbol' => $symbol,
                    'current_price' => $currentPrice,
                    'buy_price' => $this->lastBuyPrice,
                    'highest_price' => $this->highestPrice,
                    'consecutive_drops' => $this->consecutiveDrops,
                    'profit_percentage' => (($currentPrice - $this->lastBuyPrice) / $this->lastBuyPrice) * 100
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            logEvent("Error monitoring price: " . $e->getMessage(), 'error');
            return [
                'error' => $e->getMessage(),
                'monitoring_active' => $this->monitoringActive
            ];
        }
    }
    
    /**
     * Calculate the age of a coin in hours
     * 
     * @param string $dateAdded Date the coin was added
     * @return float Age in hours
     */
    private function calculateCoinAge($dateAdded) {
        $addedTime = strtotime($dateAdded);
        $currentTime = time();
        $ageInSeconds = $currentTime - $addedTime;
        return $ageInSeconds / 3600; // Convert to hours
    }
    
    /**
     * Get the current price of a coin
     * 
     * @param string $symbol Coin symbol
     * @return float Current price
     */
    private function getCurrentPrice($symbol) {
        if (!$this->activeTrade) {
            return 0;
        }
        
        try {
            $ticker = $this->trader->getTicker($this->activeTrade['trading_pair']);
            if (isset($ticker['error'])) {
                logEvent("Error fetching ticker: " . $ticker['error'], 'error');
                return 0;
            }
            
            return $ticker['last'];
        } catch (Exception $e) {
            logEvent("Error getting current price: " . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    /**
     * Record a trade in the database
     * 
     * @param string $symbol Coin symbol
     * @param string $action Buy or sell
     * @param float $amount Amount of coin
     * @param float $price Price per coin
     * @param float $total Total cost/proceeds
     * @param string $currency Currency used (EUR, USDT, etc.)
     * @param string $orderId Exchange order ID
     * @return bool Success or failure
     */
    private function recordTrade($symbol, $action, $amount, $price, $total, $currency, $orderId) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO trades (symbol, action, amount, price, total, currency, order_id, trade_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("ssdddss", $symbol, $action, $amount, $price, $total, $currency, $orderId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                logEvent("Trade recorded in database: {$action} {$amount} {$symbol}", 'info');
                return true;
            } else {
                logEvent("Failed to record trade in database", 'error');
                return false;
            }
        } catch (Exception $e) {
            logEvent("Error recording trade: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Mark a trade as sold in the database
     * 
     * @param string $orderId Order ID of the buy trade
     * @return bool Success or failure
     */
    private function markTradeAsSold($orderId) {
        try {
            $stmt = $this->conn->prepare("UPDATE trades SET sold = 1, sold_time = NOW() WHERE order_id = ?");
            $stmt->bind_param("s", $orderId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                logEvent("Trade with order ID {$orderId} marked as sold", 'info');
                return true;
            } else {
                logEvent("Failed to mark trade as sold", 'error');
                return false;
            }
        } catch (Exception $e) {
            logEvent("Error marking trade as sold: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Check if a coin is currently being monitored
     * 
     * @return bool True if monitoring is active
     */
    public function isMonitoringActive() {
        return $this->monitoringActive;
    }
    
    /**
     * Get the active trade details
     * 
     * @return array|null Active trade details or null if no active trade
     */
    public function getActiveTrade() {
        return $this->activeTrade;
    }
}
