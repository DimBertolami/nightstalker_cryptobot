<?php
/**
 * New Coin Strategy Execution Script
 * 
 * This script implements a strategy to:
 * 1. Find new coins (<24h old) with marketcap > $1.5M and volume > $1.5M
 * 2. Buy the coin if criteria are met
 * 3. Monitor price 20x/minute (every 3 seconds)
 * 4. Sell when price drops for 30 consecutive seconds
 * 
 * Run this script continuously for the strategy to work effectively
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/NewCoinStrategy.php';

// Set to true for simulation mode, false for live trading
$testMode = true;

// Initialize the strategy
try {
    $strategy = new NewCoinStrategy($testMode);
    
    echo "=== NEW COIN STRATEGY EXECUTION ===\n";
    echo "Mode: " . ($testMode ? "SIMULATION (no real trades)" : "LIVE TRADING") . "\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Ensure trades table exists
    ensureTradesTableExists();
    
    // Main execution loop
    while (true) {
        // Check if we're already monitoring a coin
        if ($strategy->isMonitoringActive()) {
            echo "\nMonitoring active coin...\n";
            $monitorResults = $strategy->monitorPrice();
            
            if (isset($monitorResults['details']['symbol'])) {
                $details = $monitorResults['details'];
                echo "Symbol: {$details['symbol']}\n";
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
                
                // After selling, wait a bit before looking for new coins
                echo "\nWaiting 10 seconds before resuming search for new coins...\n";
                sleep(10);
            }
        } else {
            // Look for new coins that meet the criteria
            echo "\nSearching for new coins...\n";
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
                echo "\nStarting price monitoring...\n";
            } else {
                // If no trades were executed, wait before checking again
                echo "No trades executed. Waiting 60 seconds before checking again...\n";
                sleep(60);
                continue;
            }
        }
        
        // Sleep for 3 seconds (20 checks per minute)
        sleep(3);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    logEvent("Error executing new coin strategy: " . $e->getMessage(), 'error');
    exit(1);
}

/**
 * Ensure the trades table exists in the database
 */
function ensureTradesTableExists() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS trades (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        symbol VARCHAR(20) NOT NULL,
        action ENUM('buy', 'sell') NOT NULL,
        amount DECIMAL(18,8) NOT NULL,
        price DECIMAL(18,8) NOT NULL,
        total DECIMAL(18,8) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        order_id VARCHAR(100) NOT NULL,
        trade_time DATETIME NOT NULL,
        sold TINYINT(1) DEFAULT 0,
        sold_time DATETIME NULL
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "Trades table ready\n";
    } else {
        echo "Error creating trades table: " . $conn->error . "\n";
        exit(1);
    }
    
    $conn->close();
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
