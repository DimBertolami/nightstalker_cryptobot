<?php
require_once __DIR__ . '/BitvavoTrader.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * TradingStrategy Class
 * Implements trading strategies based on cryptocurrency data
 */
class TradingStrategy {
    private $trader;
    private $conn;
    private $testMode;
    
    /**
     * Constructor
     * 
     * @param bool $testMode Whether to use test mode (sandbox) or live trading
     */
    public function __construct($testMode = true) {
        $this->testMode = $testMode;
        $this->trader = new BitvavoTrader($testMode);
        
        // Connect to database
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            log_message("Database connection failed: " . $this->conn->connect_error, 'error');
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
    }
    
    /**
     * Execute trading strategy based on trending coins
     * 
     * @return array Results of trading operations
     */
    public function executeTrendingStrategy() {
        $results = [
            'trades_attempted' => 0,
            'trades_successful' => 0,
            'trades_failed' => 0,
            'details' => []
        ];
        
        try {
            // Get trending coins from database
            $stmt = $this->conn->prepare("SELECT * FROM coins WHERE is_trending = 1 ORDER BY volume DESC LIMIT 10");
            $stmt->execute();
            $trendingCoins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($trendingCoins)) {
                log_message("No trending coins found for trading", 'info');
                return $results;
            }
            
            log_message("Found " . count($trendingCoins) . " trending coins for potential trading", 'info');
            
            // Get account balance
            $balance = $this->trader->getBalance();
            if (isset($balance['error'])) {
                log_message("Error fetching balance: " . $balance['error'], 'error');
                return $results;
            }
            
            // Get available markets
            $markets = $this->trader->getMarkets();
            if (isset($markets['error'])) {
                log_message("Error fetching markets: " . $markets['error'], 'error');
                return $results;
            }
            
            // Process each trending coin
            foreach ($trendingCoins as $coin) {
                $results['trades_attempted']++;
                $symbol = $coin['symbol'];
                
                // Find appropriate trading pair (with EUR or USDT)
                $tradingPair = null;
                if (isset($markets["{$symbol}/EUR"])) {
                    $tradingPair = "{$symbol}/EUR";
                } elseif (isset($markets["{$symbol}/USDT"])) {
                    $tradingPair = "{$symbol}/USDT";
                } else {
                    log_message("No suitable trading pair found for {$symbol}", 'warning');
                    $results['details'][] = [
                        'symbol' => $symbol,
                        'status' => 'skipped',
                        'reason' => 'No suitable trading pair found'
                    ];
                    continue;
                }
                
                // Get ticker information
                $ticker = $this->trader->getTicker($tradingPair);
                if (isset($ticker['error'])) {
                    log_message("Error fetching ticker for {$tradingPair}: " . $ticker['error'], 'error');
                    $results['trades_failed']++;
                    $results['details'][] = [
                        'symbol' => $symbol,
                        'status' => 'failed',
                        'reason' => 'Error fetching ticker: ' . $ticker['error']
                    ];
                    continue;
                }
                
                // Implement trading logic
                // For example, buy if 24h change is positive and volume is high
                if ($ticker['percentage'] > 5 && $coin['volume'] > MIN_VOLUME_THRESHOLD) {
                    // Check if we have enough balance
                    $quoteCurrency = explode('/', $tradingPair)[1]; // EUR or USDT
                    $availableBalance = $balance['free'][$quoteCurrency] ?? 0;
                    
                    // Calculate trade amount (in this example, use 5% of available balance)
                    $tradeAmount = $availableBalance * 0.05;
                    $coinAmount = $tradeAmount / $ticker['last'];
                    
                    // Skip if trade amount is too small
                    if ($tradeAmount < 10) { // Minimum 10 EUR/USDT
                        log_message("Available balance too low for trading {$symbol}", 'info');
                        $results['details'][] = [
                            'symbol' => $symbol,
                            'status' => 'skipped',
                            'reason' => 'Available balance too low'
                        ];
                        continue;
                    }
                    
                    // In test mode, just log what would happen
                    if ($this->testMode) {
                        log_message("TEST MODE: Would buy {$coinAmount} {$symbol} at {$ticker['last']} {$quoteCurrency}", 'info');
                        $results['trades_successful']++;
                        $results['details'][] = [
                            'symbol' => $symbol,
                            'status' => 'simulated',
                            'action' => 'buy',
                            'amount' => $coinAmount,
                            'price' => $ticker['last'],
                            'total' => $tradeAmount,
                            'currency' => $quoteCurrency
                        ];
                    } else {
                        // Place market buy order
                        $order = $this->trader->marketBuy($tradingPair, $coinAmount);
                        
                        if (isset($order['error'])) {
                            log_message("Error placing buy order for {$tradingPair}: " . $order['error'], 'error');
                            $results['trades_failed']++;
                            $results['details'][] = [
                                'symbol' => $symbol,
                                'status' => 'failed',
                                'reason' => 'Error placing buy order: ' . $order['error']
                            ];
                        } else {
                            log_message("Successfully bought {$coinAmount} {$symbol} at {$ticker['last']} {$quoteCurrency}", 'info');
                            $results['trades_successful']++;
                            $results['details'][] = [
                                'symbol' => $symbol,
                                'status' => 'success',
                                'action' => 'buy',
                                'order_id' => $order['id'],
                                'amount' => $order['amount'],
                                'price' => $order['price'],
                                'total' => $order['cost'],
                                'currency' => $quoteCurrency
                            ];
                            
                            // Record trade in database
                            $this->recordTrade($symbol, 'buy', $order['amount'], $order['price'], $order['cost'], $quoteCurrency, $order['id']);
                        }
                    }
                } else {
                    log_message("Trading conditions not met for {$symbol}", 'info');
                    $results['details'][] = [
                        'symbol' => $symbol,
                        'status' => 'skipped',
                        'reason' => 'Trading conditions not met',
                        'percentage_change' => $ticker['percentage'],
                        'volume' => $coin['volume']
                    ];
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            log_message("Error executing trading strategy: " . $e->getMessage(), 'error');
            return [
                'error' => $e->getMessage(),
                'trades_attempted' => $results['trades_attempted'],
                'trades_successful' => $results['trades_successful'],
                'trades_failed' => $results['trades_failed']
            ];
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
                log_message("Trade recorded in database: {$action} {$amount} {$symbol}", 'info');
                return true;
            } else {
                log_message("Failed to record trade in database", 'error');
                return false;
            }
        } catch (Exception $e) {
            log_message("Error recording trade: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Check and sell coins that have reached profit target
     * 
     * @param float $profitTarget Profit target percentage (e.g., 10 for 10%)
     * @return array Results of sell operations
     */
    public function checkAndSellProfitableCoins($profitTarget = 10) {
        $results = [
            'sells_attempted' => 0,
            'sells_successful' => 0,
            'sells_failed' => 0,
            'details' => []
        ];
        
        try {
            // Get buy trades from database
            $stmt = $this->conn->prepare("
                SELECT t.*, c.price as current_price 
                FROM trades t
                JOIN coins c ON t.symbol = c.symbol
                WHERE t.action = 'buy' AND t.sold = 0
            ");
            $stmt->execute();
            $buyTrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($buyTrades)) {
                log_message("No buy trades found to check for selling", 'info');
                return $results;
            }
            
            // Get account balance
            $balance = $this->trader->getBalance();
            if (isset($balance['error'])) {
                log_message("Error fetching balance: " . $balance['error'], 'error');
                return $results;
            }
            
            // Check each buy trade
            foreach ($buyTrades as $trade) {
                $symbol = $trade['symbol'];
                $buyPrice = $trade['price'];
                $currentPrice = $trade['current_price'];
                $profitPercentage = (($currentPrice - $buyPrice) / $buyPrice) * 100;
                
                // If profit target reached, sell
                if ($profitPercentage >= $profitTarget) {
                    $results['sells_attempted']++;
                    
                    // Find appropriate trading pair
                    $tradingPair = "{$symbol}/{$trade['currency']}";
                    
                    // Check if we have enough balance of this coin
                    $availableBalance = $balance['free'][$symbol] ?? 0;
                    if ($availableBalance < $trade['amount']) {
                        log_message("Not enough {$symbol} available for selling", 'warning');
                        $results['sells_failed']++;
                        $results['details'][] = [
                            'symbol' => $symbol,
                            'status' => 'failed',
                            'reason' => 'Not enough balance available',
                            'required' => $trade['amount'],
                            'available' => $availableBalance
                        ];
                        continue;
                    }
                    
                    // In test mode, just log what would happen
                    if ($this->testMode) {
                        log_message("TEST MODE: Would sell {$trade['amount']} {$symbol} at {$currentPrice} {$trade['currency']} (profit: {$profitPercentage}%)", 'info');
                        $results['sells_successful']++;
                        $results['details'][] = [
                            'symbol' => $symbol,
                            'status' => 'simulated',
                            'action' => 'sell',
                            'amount' => $trade['amount'],
                            'buy_price' => $buyPrice,
                            'sell_price' => $currentPrice,
                            'profit_percentage' => $profitPercentage,
                            'currency' => $trade['currency']
                        ];
                    } else {
                        // Place market sell order
                        $order = $this->trader->marketSell($tradingPair, $trade['amount']);
                        
                        if (isset($order['error'])) {
                            log_message("Error placing sell order for {$tradingPair}: " . $order['error'], 'error');
                            $results['sells_failed']++;
                            $results['details'][] = [
                                'symbol' => $symbol,
                                'status' => 'failed',
                                'reason' => 'Error placing sell order: ' . $order['error']
                            ];
                        } else {
                            log_message("Successfully sold {$trade['amount']} {$symbol} at {$currentPrice} {$trade['currency']} (profit: {$profitPercentage}%)", 'info');
                            $results['sells_successful']++;
                            $results['details'][] = [
                                'symbol' => $symbol,
                                'status' => 'success',
                                'action' => 'sell',
                                'order_id' => $order['id'],
                                'amount' => $order['amount'],
                                'price' => $order['price'],
                                'total' => $order['cost'],
                                'profit_percentage' => $profitPercentage,
                                'currency' => $trade['currency']
                            ];
                            
                            // Record sell trade in database
                            $this->recordTrade($symbol, 'sell', $order['amount'], $order['price'], $order['cost'], $trade['currency'], $order['id']);
                            
                            // Mark original buy trade as sold
                            $this->markTradeAsSold($trade['id']);
                        }
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            log_message("Error checking for profitable coins: " . $e->getMessage(), 'error');
            return [
                'error' => $e->getMessage(),
                'sells_attempted' => $results['sells_attempted'],
                'sells_successful' => $results['sells_successful'],
                'sells_failed' => $results['sells_failed']
            ];
        }
    }
    
    /**
     * Mark a trade as sold in the database
     * 
     * @param int $tradeId Trade ID
     * @return bool Success or failure
     */
    private function markTradeAsSold($tradeId) {
        try {
            $stmt = $this->conn->prepare("UPDATE trades SET sold = 1, sold_time = NOW() WHERE id = ?");
            $stmt->bind_param("i", $tradeId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                log_message("Trade ID {$tradeId} marked as sold", 'info');
                return true;
            } else {
                log_message("Failed to mark trade ID {$tradeId} as sold", 'error');
                return false;
            }
        } catch (Exception $e) {
            log_message("Error marking trade as sold: " . $e->getMessage(), 'error');
            return false;
        }
    }
}
