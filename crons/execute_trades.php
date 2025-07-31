<?php
/**
 * Execute Trades Cron Job
 * 
 * This script executes trading strategies based on cryptocurrency data
 * It should be run periodically (e.g., every hour) via cron
 */

// Set error reporting
error_reporting(E_ERROR);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../includes/TradingStrategy.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Function to generate a unique ID for a trade
function generateTradeId($symbol, $action, $timestamp) {
    return md5($symbol . $action . $timestamp);
}

// Check if trades table exists, create if not
function ensureTradesTableExists() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    
    $sql = "
    CREATE TABLE IF NOT EXISTS trades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        symbol VARCHAR(20) NOT NULL,
        action ENUM('buy', 'sell') NOT NULL,
        amount DECIMAL(18, 8) NOT NULL,
        price DECIMAL(18, 8) NOT NULL,
        total DECIMAL(18, 8) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        order_id VARCHAR(100) NOT NULL,
        trade_time DATETIME NOT NULL,
        sold TINYINT(1) DEFAULT 0,
        sold_time DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) !== TRUE) {
        die("Error creating trades table: " . $conn->error);
    }
    
    $conn->close();
}

// Main execution
try {
    log_message("Cron job started: execute_trades.php");
    echo "===== EXECUTING TRADING STRATEGIES =====" . PHP_EOL;
    
    // Ensure trades table exists
    ensureTradesTableExists();
    
    // Initialize trading strategy (true for test mode, false for live trading)
    $testMode = true; // IMPORTANT: Set to false for live trading
    $strategy = new TradingStrategy($testMode);
    
    echo "Trading mode: " . ($testMode ? "TEST" : "LIVE") . PHP_EOL;
    
    // Execute trending strategy
    echo PHP_EOL . "Executing trending strategy..." . PHP_EOL;
    $trendingResults = $strategy->executeTrendingStrategy();
    
    echo "Trades attempted: " . $trendingResults['trades_attempted'] . PHP_EOL;
    echo "Trades successful: " . $trendingResults['trades_successful'] . PHP_EOL;
    echo "Trades failed: " . $trendingResults['trades_failed'] . PHP_EOL;
    
    if (!empty($trendingResults['details'])) {
        echo PHP_EOL . "Trade details:" . PHP_EOL;
        foreach ($trendingResults['details'] as $detail) {
            echo "- {$detail['symbol']}: {$detail['status']}" . PHP_EOL;
            if ($detail['status'] == 'skipped') {
                echo "  Reason: {$detail['reason']}" . PHP_EOL;
            } elseif ($detail['status'] == 'success' || $detail['status'] == 'simulated') {
                echo "  Action: {$detail['action']}" . PHP_EOL;
                echo "  Amount: {$detail['amount']}" . PHP_EOL;
                echo "  Price: {$detail['price']} {$detail['currency']}" . PHP_EOL;
            }
        }
    }
    
    // Check for profitable coins to sell (10% profit target)
    echo PHP_EOL . "Checking for profitable coins to sell..." . PHP_EOL;
    $sellResults = $strategy->checkAndSellProfitableCoins(10);
    
    echo "Sells attempted: " . $sellResults['sells_attempted'] . PHP_EOL;
    echo "Sells successful: " . $sellResults['sells_successful'] . PHP_EOL;
    echo "Sells failed: " . $sellResults['sells_failed'] . PHP_EOL;
    
    if (!empty($sellResults['details'])) {
        echo PHP_EOL . "Sell details:" . PHP_EOL;
        foreach ($sellResults['details'] as $detail) {
            echo "- {$detail['symbol']}: {$detail['status']}" . PHP_EOL;
            if ($detail['status'] == 'failed') {
                echo "  Reason: {$detail['reason']}" . PHP_EOL;
            } elseif ($detail['status'] == 'success' || $detail['status'] == 'simulated') {
                echo "  Amount: {$detail['amount']}" . PHP_EOL;
                if (isset($detail['profit_percentage'])) {
                    echo "  Profit: {$detail['profit_percentage']}%" . PHP_EOL;
                }
                echo "  Price: {$detail['price']} {$detail['currency']}" . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "===== TRADING EXECUTION COMPLETE =====" . PHP_EOL;
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    logEvent("Trading execution error: " . $e->getMessage(), 'error');
}
