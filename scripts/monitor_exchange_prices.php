<?php
/**
 * Exchange Price Monitor
 * 
 * This script monitors prices directly from the selected exchange (Binance or Bitvavo)
 * at regular intervals, identifies potential buying opportunities, and executes trades
 * based on price movement patterns.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/TradingLogger.php';

// Initialize logger
$logger = new TradingLogger();
$logger->logEvent("exchange_monitor", "startup", ["message" => "Starting exchange price monitor"]);

// CLI arguments handling
$options = getopt("e:d:v", ["exchange:", "debug", "verbose"]);
$selectedExchange = $options['e'] ?? $options['exchange'] ?? 'binance'; // Default to Binance
$debug = isset($options['d']) || isset($options['debug']);
$verbose = isset($options['v']) || isset($options['verbose']);

// Validate exchange selection
$supportedExchanges = ['binance', 'bitvavo'];
if (!in_array(strtolower($selectedExchange), $supportedExchanges)) {
    $logger->logEvent("exchange_monitor", "error", ["message" => "Unsupported exchange '{$selectedExchange}'. Supported exchanges: " . implode(', ', $supportedExchanges)]);
    exit(1);
}

// Load exchange-specific API handler
$exchangeHandler = loadExchangeHandler($selectedExchange);
if (!$exchangeHandler) {
    $logger->logEvent("exchange_monitor", "error", ["message" => "Failed to load handler for exchange '{$selectedExchange}'"]);
    exit(1);
}

// Configuration
$monitorInterval = 5; // seconds between price checks
$priceHistory = []; // Store price history for analysis
$activeMonitoring = []; // Coins we're actively monitoring
$maxMonitoringTime = 3600; // Maximum time to monitor a coin (1 hour)
$profitTarget = 2.5; // Target profit percentage
$stopLossThreshold = -1.5; // Stop loss percentage
$peakDropWaitTime = 30; // Wait 30 seconds after peak drop before selling
$minMarketCap = 1500000; // Minimum market cap ($1.5M)
$minVolume24h = 1500000; // Minimum 24h volume ($1.5M)
$maxCoinAge = 24; // Maximum coin age in hours

// Main monitoring loop
$logger->logEvent("exchange_monitor", "info", ["message" => "Starting price monitoring loop for {$selectedExchange} - checking every {$monitorInterval} seconds"]);
while (true) {
    try {
        // Fetch available coins from the exchange
        $availableCoins = $exchangeHandler->getAvailableCoins();
        if ($verbose) $logger->logEvent("exchange_monitor", "info", ["message" => "Found " . count($availableCoins) . " coins on {$selectedExchange}"]);
        
        // Get current prices for all coins
        $currentPrices = $exchangeHandler->getCurrentPrices();
        
        // Update price history
        foreach ($currentPrices as $symbol => $price) {
            if (!isset($priceHistory[$symbol])) {
                $priceHistory[$symbol] = [];
            }
            
            // Add current price to history, with timestamp
            $priceHistory[$symbol][] = [
                'timestamp' => time(),
                'price' => $price
            ];
            
            // Keep only the last 24 hours of price data (17280 entries at 5-second intervals)
            if (count($priceHistory[$symbol]) > 17280) {
                array_shift($priceHistory[$symbol]);
            }
        }
        
        // Analyze coins and identify potential buys based on user criteria
        $potentialBuys = identifyPotentialBuys($priceHistory, $currentPrices, $exchangeHandler);
        
        // Execute trades based on analysis
        foreach ($potentialBuys as $symbol => $buyInfo) {
            // Check if we have enough funds
            if ($exchangeHandler->checkSufficientFunds($buyInfo['estimatedCost'])) {
                // Execute buy
                $result = $exchangeHandler->executeBuy($symbol, $buyInfo['amount']);
                if ($result['success']) {
                    $logger->logEvent("exchange_monitor", "buy", ["symbol" => $symbol, "amount" => $buyInfo['amount'], "price" => $buyInfo['price'], "transactionId" => $result['transactionId'], "message" => "BOUGHT: {$buyInfo['amount']} {$symbol} at {$buyInfo['price']} - Transaction ID: {$result['transactionId']}"]);
                    
                    // Add to active monitoring
                    $activeMonitoring[$symbol] = [
                        'buyPrice' => $buyInfo['price'],
                        'amount' => $buyInfo['amount'],
                        'buyTime' => time(),
                        'highestPrice' => $buyInfo['price'],
                        'transactionId' => $result['transactionId']
                    ];
                } else {
                    $logger->logEvent("exchange_monitor", "error", ["symbol" => $symbol, "action" => "buy", "error" => $result['error'], "message" => "BUY FAILED: {$symbol} - {$result['error']}"]);
                }
            } else {
                if ($verbose) $logger->logEvent("exchange_monitor", "warning", ["symbol" => $symbol, "requiredFunds" => $buyInfo['estimatedCost'], "message" => "INSUFFICIENT FUNDS: Cannot buy {$symbol} - Required: {$buyInfo['estimatedCost']}"]);
            }
        }
        
        // Monitor active positions and sell if needed
        foreach ($activeMonitoring as $symbol => $monitorInfo) {
            if (!isset($currentPrices[$symbol])) {
                if ($verbose) $logger->logEvent("exchange_monitor", "warning", ["symbol" => $symbol, "message" => "Cannot find current price for {$symbol}"]);
                continue;
            }
            
            $currentPrice = $currentPrices[$symbol];
            $buyPrice = $monitorInfo['buyPrice'];
            $elapsedTime = time() - $monitorInfo['buyTime'];
            
            // Update highest observed price
            if ($currentPrice > $monitorInfo['highestPrice']) {
                $activeMonitoring[$symbol]['highestPrice'] = $currentPrice;
                if ($verbose) $logger->logEvent("exchange_monitor", "info", ["symbol" => $symbol, "price" => $currentPrice, "message" => "NEW HIGH: {$symbol} reached {$currentPrice}"]);
            }
            
            // Calculate current profit percentage
            $profitPercentage = (($currentPrice - $buyPrice) / $buyPrice) * 100;
            
            // Determine if we should sell
            $shouldSell = false;
            $sellReason = '';
            
            // Track when price starts dropping from peak
            $dropFromHigh = (($monitorInfo['highestPrice'] - $currentPrice) / $monitorInfo['highestPrice']) * 100;
            
            // Check if we're in a drop from peak
            if ($dropFromHigh > 0) {
                // Initialize peak drop tracking if not already set
                if (!isset($activeMonitoring[$symbol]['peakDropStartTime'])) {
                    $activeMonitoring[$symbol]['peakDropStartTime'] = time();
                    if ($verbose) $logger->logEvent("exchange_monitor", "info", [
                        "symbol" => $symbol, 
                        "drop" => $dropFromHigh,
                        "message" => "Price dropping from peak: {$symbol} dropping {$dropFromHigh}% from high of {$monitorInfo['highestPrice']}"
                    ]);
                }
                
                // Check if we've been dropping for more than 30 seconds
                $dropDuration = time() - $activeMonitoring[$symbol]['peakDropStartTime'];
                
                if ($dropDuration >= $peakDropWaitTime) {
                    $shouldSell = true;
                    $sellReason = "Price dropped from peak for {$dropDuration} seconds (drop: {$dropFromHigh}%)";
                }
            } else {
                // Reset peak drop tracking if price is rising again
                if (isset($activeMonitoring[$symbol]['peakDropStartTime'])) {
                    unset($activeMonitoring[$symbol]['peakDropStartTime']);
                    if ($verbose) $logger->logEvent("exchange_monitor", "info", [
                        "symbol" => $symbol,
                        "message" => "Price rising again for {$symbol}, resetting peak drop tracking"
                    ]);
                }
            }
            
            // Case 2: Stop loss triggered (keep this as a safety measure)
            if ($profitPercentage <= $stopLossThreshold) {
                $shouldSell = true;
                $sellReason = "Stop loss triggered: {$profitPercentage}%";
            }
            
            // Execute sell if conditions are met
            if ($shouldSell) {
                $result = $exchangeHandler->executeSell($symbol, $monitorInfo['amount']);
                if ($result['success']) {
                    $profit = ($currentPrice - $buyPrice) * $monitorInfo['amount'];
                    $logger->logEvent("exchange_monitor", "sell", ["symbol" => $symbol, "amount" => $monitorInfo['amount'], "price" => $currentPrice, "profit" => $profit, "reason" => $sellReason, "message" => "SOLD: {$monitorInfo['amount']} {$symbol} at {$currentPrice} - Profit: {$profit} - Reason: {$sellReason}"]);
                    
                    // Remove from active monitoring
                    unset($activeMonitoring[$symbol]);
                } else {
                    $logger->logEvent("exchange_monitor", "error", ["symbol" => $symbol, "action" => "sell", "error" => $result['error'], "message" => "SELL FAILED: {$symbol} - {$result['error']}"]);
                }
            } else {
                if ($verbose) $logger->logEvent("exchange_monitor", "monitor", ["symbol" => $symbol, "currentPrice" => $currentPrice, "boughtPrice" => $buyPrice, "profit" => $profitPercentage, "highestPrice" => $monitorInfo['highestPrice'], "message" => "MONITORING: {$symbol} - Current: {$currentPrice}, Bought: {$buyPrice}, Profit: {$profitPercentage}%, Highest: {$monitorInfo['highestPrice']}"]);
            }
        }
        
        // Sleep until next check
        sleep($monitorInterval);
    } catch (Exception $e) {
        $logger->logEvent("exchange_monitor", "error", ["message" => "ERROR: " . $e->getMessage()]);
        sleep($monitorInterval * 2); // Wait a bit longer after an error
    }
}

/**
 * Load the appropriate exchange handler based on selection
 * 
 * @param string $exchange Exchange name
 * @return object|false Exchange handler or false on failure
 */
function loadExchangeHandler($exchange) {
    $exchange = strtolower($exchange);
    
    switch ($exchange) {
        case 'binance':
            require_once __DIR__ . '/../includes/exchanges/BinanceHandler.php';
            return new BinanceHandler();
            
        case 'bitvavo':
            require_once __DIR__ . '/../includes/exchanges/BitvavoHandler.php';
            return new BitvavoHandler();
            
        default:
            return false;
    }
}

/**
 * Identify potential buying opportunities based on user's specific criteria
 * 
 * @param array $priceHistory Price history data
 * @param array $currentPrices Current prices
 * @param object $exchangeHandler Exchange handler instance
 * @return array Potential buy opportunities
 */
function identifyPotentialBuys($priceHistory, $currentPrices, $exchangeHandler) {
    global $minMarketCap, $minVolume24h, $maxCoinAge, $logger, $verbose;
    $potentialBuys = [];
    
    // Get coin metadata (age, market cap, volume)
    $coinMetadata = $exchangeHandler->getCoinMetadata();
    
    if ($verbose) $logger->logEvent("exchange_monitor", "info", ["message" => "Evaluating coins based on criteria: Age > {$maxCoinAge}h, Market Cap > $minMarketCap, 24h Volume > $minVolume24h"]);
    
    foreach ($currentPrices as $symbol => $currentPrice) {
        // Skip if no metadata available
        if (!isset($coinMetadata[$symbol])) {
            if ($verbose) $logger->logEvent("exchange_monitor", "info", ["symbol" => $symbol, "message" => "Skipping {$symbol} - no metadata available"]);
            continue;
        }
        
        $metadata = $coinMetadata[$symbol];
        
        // Apply user's specific criteria
        // 1. Coin age greater than 24h
        if (isset($metadata['age_hours']) && $metadata['age_hours'] < $maxCoinAge) {
            if ($verbose) $logger->logEvent("exchange_monitor", "info", ["symbol" => $symbol, "age" => $metadata['age_hours'], "message" => "Skipping {$symbol} - too new ({$metadata['age_hours']} hours)"]);
            continue;
        }
        
        // 2. Market cap over $1.5M
        if (isset($metadata['market_cap']) && $metadata['market_cap'] < $minMarketCap) {
            if ($verbose) $logger->logEvent("exchange_monitor", "info", ["symbol" => $symbol, "market_cap" => $metadata['market_cap'], "message" => "Skipping {$symbol} - market cap too low ({$metadata['market_cap']})"]);
            continue;
        }
        
        // 3. 24h volume over $1.5M
        if (isset($metadata['volume_24h']) && $metadata['volume_24h'] < $minVolume24h) {
            if ($verbose) $logger->logEvent("exchange_monitor", "info", ["symbol" => $symbol, "volume" => $metadata['volume_24h'], "message" => "Skipping {$symbol} - volume too low ({$metadata['volume_24h']})"]);
            continue;
        }
        
        // Coin meets all criteria - add to potential buys
        $logger->logEvent("exchange_monitor", "info", [
            "symbol" => $symbol, 
            "price" => $currentPrice,
            "age" => $metadata['age_hours'] ?? 'unknown',
            "market_cap" => $metadata['market_cap'] ?? 'unknown',
            "volume_24h" => $metadata['volume_24h'] ?? 'unknown',
            "message" => "Found potential buy: {$symbol} at {$currentPrice}"
        ]);
        
        // Calculate how much to buy - use all available funds
        $availableFunds = $exchangeHandler->getAvailableFunds();
        $amount = $availableFunds / $currentPrice * 0.99; // Use 99% of funds to account for fees
        
        $potentialBuys[$symbol] = [
            'price' => $currentPrice,
            'amount' => $amount,
            'estimatedCost' => $currentPrice * $amount
        ];
    }
    
    return $potentialBuys;
}

/**
 * Calculate trend percentage from price history
 * 
 * @param array $history Price history
 * @return float Trend percentage
 */
function calculateTrend($history) {
    if (count($history) < 2) {
        return 0;
    }
    
    $firstPrice = $history[0]['price'];
    $lastPrice = end($history)['price'];
    
    return (($lastPrice - $firstPrice) / $firstPrice) * 100;
}

/**
 * Calculate price volatility
 * 
 * @param array $history Price history
 * @return float Volatility percentage
 */
function calculateVolatility($history) {
    if (count($history) < 2) {
        return 0;
    }
    
    $prices = array_column($history, 'price');
    $min = min($prices);
    $max = max($prices);
    $avg = array_sum($prices) / count($prices);
    
    return (($max - $min) / $avg) * 100;
}

/**
 * Calculate amount to buy based on price and available funds
 * 
 * @param float $price Current price
 * @return float Amount to buy
 */
function calculateBuyAmount($price) {
    // Default to $50 worth of the coin, adjust based on your strategy
    $targetValue = 50;
    
    // Ensure minimum amount
    $amount = $targetValue / $price;
    if ($amount * $price < 10) {
        // Ensure we're buying at least $10 worth
        $amount = 10 / $price;
    }
    
    return $amount;
}
