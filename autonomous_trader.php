<?php
/**
 * Night Stalker Autonomous Trading Bot
 * 
 * This script runs the autonomous trading bot that executes trades based on configured strategies
 * It can be run via cron job or as a daemon process
 */

// Set error reporting
error_reporting(E_ERROR);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/TradingStrategy.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/vendor/autoload.php';
// Load trading configuration
$configFile = __DIR__ . '/config/trading_config.json';
if (file_exists($configFile)) {
    $tradingConfig = json_decode(file_get_contents($configFile), true);
} else {
    // Default configuration
    $tradingConfig = [
        'test_mode' => true,                  // Set to false for live trading
        'strategies' => [
            'new_coin' => [
                'enabled' => true,            // Enable trending strategy
                'max_trades_per_run' => 3,    // Maximum trades per execution
                'min_volume' => 100000,       // Minimum volume in EUR
                'max_coins' => 5,             // Maximum coins to hold at once
                'trade_amount' => 100         // Amount in EUR to trade per position
            ],
            'profitable_sell' => [
                'enabled' => true,            // Enable profitable selling
                'profit_target' => 10,        // Profit target percentage
                'stop_loss' => -5             // Stop loss percentage
            ]
        ],
        'trading_interval' => 3600,           // Trading interval in seconds (1 hour)
        'exchanges' => [
            'bitvavo' => [
                'enabled' => true,
                'pairs' => ['BTC/EUR', 'ETH/EUR', 'XRP/EUR'] // Default trading pairs
            ]
        ]
    ];
    
    // Save default configuration
    file_put_contents($configFile, json_encode($tradingConfig, JSON_PRETTY_PRINT));
    logEvent("Created default trading configuration", 'info');
}

/**
 * Autonomous Trader Class
 * Manages the autonomous trading process
 */
class AutonomousTrader {
    private $config;
    private $strategy;
    private $db;
    
    /**
     * Constructor
     * 
     * @param array $config Trading configuration
     */
    public function __construct($config) {
        $this->config = $config;
        $this->strategy = new TradingStrategy($config['test_mode']);
        $this->db = getDBConnection();
        
        logEvent("Autonomous trader initialized in " . 
                 ($config['test_mode'] ? "TEST" : "LIVE") . " mode", 'info');
    }
    
    /**
     * Run the trading cycle
     */
    public function runTradingCycle() {
        try {
            echo "===== STARTING AUTONOMOUS TRADING CYCLE =====" . PHP_EOL;
            echo "Mode: " . ($this->config['test_mode'] ? "TEST" : "LIVE") . PHP_EOL;
            echo "Time: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;
            
            // Update coin data
            $this->updateCoinData();
            
            // Execute trading strategies
            $this->executeStrategies();
            
            // Monitor and manage existing positions
            $this->managePositions();
            
            echo PHP_EOL . "===== TRADING CYCLE COMPLETE =====" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . PHP_EOL;
            logEvent("Autonomous trading error: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Update coin data from exchanges
     */
    private function updateCoinData() {
        echo "Updating coin data..." . PHP_EOL;
        
        // This would typically call an API or database update function
        // For now, we'll assume the data is updated by other cron jobs
        
        // You could add code here to call:
        // - /crons/fetch_coins.php
        // - /crons/update_prices.php
        
        echo "Coin data update complete" . PHP_EOL;
    }
    
    /**
     * Execute configured trading strategies
     */
    private function executeStrategies() {
        // Execute new coin strategy if enabled
        if ($this->config['strategies']['new_coin']['enabled']) {
            echo PHP_EOL . "Executing new coin strategy..." . PHP_EOL;
            $trendingResults = $this->strategy->executeTrendingStrategy();
            
            echo "Trades attempted: " . $trendingResults['trades_attempted'] . PHP_EOL;
            echo "Trades successful: " . $trendingResults['trades_successful'] . PHP_EOL;
            
            if (!empty($trendingResults['details'])) {
                echo "Trade details:" . PHP_EOL;
                foreach ($trendingResults['details'] as $detail) {
                    echo "- {$detail['symbol']}: {$detail['status']}" . PHP_EOL;
                }
            }
        }
        
        // Execute profitable sell strategy if enabled
        if ($this->config['strategies']['profitable_sell']['enabled']) {
            echo PHP_EOL . "Checking for profitable coins to sell..." . PHP_EOL;
            $profitTarget = $this->config['strategies']['profitable_sell']['profit_target'];
            $sellResults = $this->strategy->checkAndSellProfitableCoins($profitTarget);
            
            echo "Sells attempted: " . $sellResults['sells_attempted'] . PHP_EOL;
            echo "Sells successful: " . $sellResults['sells_successful'] . PHP_EOL;
            
            if (!empty($sellResults['details'])) {
                echo "Sell details:" . PHP_EOL;
                foreach ($sellResults['details'] as $detail) {
                    echo "- {$detail['symbol']}: {$detail['status']}" . PHP_EOL;
                }
            }
        }
    }
    
    /**
     * Monitor and manage existing positions
     */
    private function managePositions() {
        echo PHP_EOL . "Managing existing positions..." . PHP_EOL;
        
        // Implement stop-loss logic
        if (isset($this->config['strategies']['profitable_sell']['stop_loss'])) {
            $stopLoss = $this->config['strategies']['profitable_sell']['stop_loss'];
            
            // Get active trades
            $stmt = $this->db->prepare("
                SELECT t.*, c.symbol, c.price as current_price
                FROM trades t
                JOIN coins c ON t.symbol = c.symbol
                WHERE t.action = 'buy' AND t.sold = 0
            ");
            $stmt->execute();
            $activeTrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            foreach ($activeTrades as $trade) {
                $buyPrice = $trade['price'];
                $currentPrice = $trade['current_price'];
                $priceChange = (($currentPrice - $buyPrice) / $buyPrice) * 100;
                
                // Check if stop loss is triggered
                if ($priceChange <= $stopLoss) {
                    echo "Stop loss triggered for {$trade['symbol']} ({$priceChange}%)" . PHP_EOL;
                    
                    // Execute sell at market price
                    $tradingPair = "{$trade['symbol']}/EUR";
                    $sellOrder = $this->strategy->trader->marketSell($tradingPair, $trade['amount']);
                    
                    if (!isset($sellOrder['error'])) {
                        // Mark trade as sold
                        $this->strategy->markTradeAsSold($trade['id']);
                        
                        echo "Sold {$trade['symbol']} at stop loss price {$currentPrice}" . PHP_EOL;
                        logEvent("Stop loss executed for {$trade['symbol']}", 'info', [
                            'buy_price' => $buyPrice,
                            'sell_price' => $currentPrice,
                            'loss_percentage' => $priceChange
                        ]);
                    }
                }
            }
        }
        
        echo "Position management complete" . PHP_EOL;
    }
}

// Execute the autonomous trader
try {
    $trader = new AutonomousTrader($tradingConfig);
    $trader->runTradingCycle();
} catch (Exception $e) {
    die("Fatal error: " . $e->getMessage() . PHP_EOL);
}
