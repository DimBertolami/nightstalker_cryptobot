<?php
/**
 * New Coin Monitor Script
 * 
 * This script coordinates between fetching new coins and trading:
 * 1. Checks if active trading is happening
 * 2. If no active trade, runs fetch_coins.php to update the database
 * 3. Executes the new coin strategy to find trading opportunities
 * 4. When a trade is active, pauses coin fetching and focuses on price monitoring
 * 
 * Run this script as a cron job every minute
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/NewCoinStrategy.php';

// Set to true for simulation mode, false for live trading
$testMode = true;

// Initialize the strategy
try {
    $strategy = new NewCoinStrategy($testMode);
    
    echo "=== NEW COIN MONITOR EXECUTION ===\n";
    echo "Mode: " . ($testMode ? "SIMULATION (no real trades)" : "LIVE TRADING") . "\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Check if we're already monitoring a coin
    if ($strategy->isMonitoringActive()) {
        echo "Active trade detected. Skipping coin fetching.\n";
        $activeTrade = $strategy->getActiveTrade();
        echo "Currently trading: {$activeTrade['symbol']}\n";
        
        // Execute price monitoring
        $monitorResults = $strategy->monitorPrice();
        
        if (isset($monitorResults['details']['symbol'])) {
            $details = $monitorResults['details'];
            echo "Current price: {$details['current_price']}\n";
            echo "Buy price: {$details['buy_price']}\n";
            echo "Highest price: {$details['highest_price']}\n";
            echo "Consecutive drops: {$details['consecutive_drops']}\n";
            echo "Current profit: {$details['profit_percentage']}%\n";
        }
        
        // If a sell was executed
        if ($monitorResults['action_taken'] === 'sell') {
            echo "\n*** SELL EXECUTED ***\n";
            $details = $monitorResults['details'];
            echo "Symbol: {$details['symbol']}\n";
            echo "Buy price: {$details['buy_price']}\n";
            echo "Sell price: {$details['sell_price']}\n";
            echo "Profit/Loss: {$details['profit_percentage']}%\n";
            echo "Holding time: " . formatSeconds($details['holding_time']) . "\n";
            
            // After selling, run fetch_coins.php to update the database
            echo "\nRunning fetch_coins.php to update database...\n";
            exec('php ' . __DIR__ . '/fetch_coins.php', $output, $returnVar);
            echo implode("\n", $output) . "\n";
        }
    } else {
        // No active trade, run fetch_coins.php to update the database
        echo "No active trade. Running fetch_coins.php to update database...\n";
        exec('php ' . __DIR__ . '/fetch_coins.php', $output, $returnVar);
        echo implode("\n", $output) . "\n";
        
        // Look for new coins that meet the criteria
        echo "\nSearching for new coins that meet the criteria...\n";
        $results = $strategy->execute();
        
        echo "Coins found: {$results['coins_found']}\n";
        echo "Trades executed: {$results['trades_executed']}\n";
        
        if ($results['monitoring_active']) {
            echo "\n*** BUY EXECUTED ***\n";
            $trade = $results['active_trade'];
            echo "Symbol: {$trade['symbol']}\n";
            echo "Buy price: {$trade['buy_price']}\n";
            echo "Amount: {$trade['amount']}\n";
            echo "Currency: {$trade['quote_currency']}\n";
        }
    }
    
    echo "\nMonitor execution completed at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    logEvent("Error in monitor_new_coins.php: " . $e->getMessage(), 'error');
    exit(1);
}

/**
 * Format seconds into a human-readable time
 * 
 * @param int $seconds Number of seconds
 * @return string Formatted time string
 */
function formatSeconds($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    $timeString = "";
    if ($hours > 0) {
        $timeString .= $hours . "h ";
    }
    if ($minutes > 0 || $hours > 0) {
        $timeString .= $minutes . "m ";
    }
    $timeString .= $secs . "s";
    
    return $timeString;
}
