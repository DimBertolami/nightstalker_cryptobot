<?php
/**
 * Night Stalker Trade Diagnostics Tool
 * 
 * This tool helps diagnose issues with trade operations across all supported exchanges.
 * It verifies database connections, trade recording, and exchange API connectivity.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdo_functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/vendor/autoload.php';
// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('display_startup_errors', 0);

// Check if we're running from command line or system-tools
$isSystemTools = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'system-tools') !== false;

// Start output buffering if we're running from system-tools
if ($isSystemTools) {
    ob_start();
}

class TradeOperationVerifier {
    private $db;
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        try {
            $this->db = getDBConnection();
            $this->addResult('database_connection', 'DB connection OK');
        } catch (Exception $e) {
            $this->addError('database_connection', 'DB connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Run all diagnostic tests
     */
    public function runDiagnostics() {
        $this->verifyTables();
        $this->verifyTradeRecording();
        $this->verifyExchangeConnections();
        $this->verifyPortfolioConsistency();
        
        return [
            'results' => $this->results,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Verify database tables exist and have correct structure
     */
    private function verifyTables() {
        $requiredTables = ['trades', 'portfolio', 'coins', 'cryptocurrencies', 'price_history'];
        $existingTables = [];
        
        try {
            $stmt = $this->db->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $existingTables[] = $row[0];
            }
            
            foreach ($requiredTables as $table) {
                if (in_array($table, $existingTables)) {
                    $this->addResult('table_' . $table, "$table: exists");
                    
                    // Check table structure
                    $this->verifyTableStructure($table);
                } else {
                    $this->addError('table_' . $table, "$table: missing");
                }
            }
        } catch (Exception $e) {
            $this->addError('tables_check', 'Failed to check tables: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify table structure has required columns
     */
    private function verifyTableStructure($table) {
        $requiredColumns = [
            'trades' => ['id', 'coin_id', 'trade_type', 'amount', 'price', 'total_value', 'trade_time'],
            'portfolio' => ['user_id', 'coin_id', 'amount', 'avg_buy_price'],
            'coins' => ['id', 'symbol', 'coin_name', 'current_price'],
            'cryptocurrencies' => ['id', 'symbol', 'name', 'price'],
            'price_history' => ['id', 'coin_id', 'price', 'recorded_at']
        ];
        
        try {
            $stmt = $this->db->query("DESCRIBE $table");
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
            
            if (isset($requiredColumns[$table])) {
                foreach ($requiredColumns[$table] as $column) {
                    if (in_array($column, $columns)) {
                        $this->addResult('column_' . $table . '_' . $column, "$table.$column: exists");
                    } else {
                        $this->addWarning('column_' . $table . '_' . $column, "$table.$column: missing");
                    }
                }
            }
        } catch (Exception $e) {
            $this->addError('structure_' . $table, "Failed to check structure of '$table': " . $e->getMessage());
        }
    }
    
    /**
     * Verify trade recording functionality
     */
    private function verifyTradeRecording() {
        try {
            // Test buy operation
            $testCoinId = 'DIAGNOSTIC_TEST_COIN';
            $testAmount = 0.001; // Very small amount for testing
            $testPrice = 1.0;
            
            // First, ensure the test coin exists
            $stmt = $this->db->prepare("INSERT IGNORE INTO cryptocurrencies (id, symbol, name, price, created_at) VALUES (?, 'TEST', 'Test Coin', 1.0, NOW())");
            $stmt->execute([$testCoinId]);
            
            // Test executeBuyPDO function
            try {
                $this->db->beginTransaction();
                
                // Insert a test buy trade directly
                $stmt = $this->db->prepare("INSERT INTO trades (coin_id, trade_type, amount, price, total_value, trade_time) VALUES (?, 'buy', ?, ?, ?, NOW())");
                $totalValue = $testAmount * $testPrice;
                $stmt->execute([$testCoinId, $testAmount, $testPrice, $totalValue]);
                $buyTradeId = $this->db->lastInsertId();
                
                if ($buyTradeId) {
                    $this->addResult('trade_recording_buy', "Test buy trade recorded successfully (ID: $buyTradeId)");
                    
                    // Clean up the test trade
                    $stmt = $this->db->prepare("DELETE FROM trades WHERE id = ?");
                    $stmt->execute([$buyTradeId]);
                    $this->addResult('trade_cleanup', "Test trade cleaned up successfully");
                } else {
                    $this->addError('trade_recording_buy', "Failed to record test buy trade");
                }
                
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                $this->addError('trade_recording_buy', "Exception during buy test: " . $e->getMessage());
            }
            
            // Test FIFO matching logic
            try {
                $this->verifyFIFOLogic();
            } catch (Exception $e) {
                $this->addError('fifo_logic', "Exception testing FIFO logic: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            $this->addError('trade_recording', "Failed to test trade recording: " . $e->getMessage());
        }
    }
    
    /**
     * Verify FIFO matching logic
     */
    private function verifyFIFOLogic() {
        // Get a sample of recent trades
        $stmt = $this->db->prepare("SELECT * FROM trades ORDER BY trade_time DESC LIMIT 20");
        $stmt->execute();
        $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($trades) > 0) {
            // Get enriched trades with FIFO matching
            $enrichedTrades = getRecentTradesWithMarketDataPDO(20);
            
            // Check if any sell trades have entry_price, invested, and profit_loss calculated
            $fifoWorking = false;
            foreach ($enrichedTrades as $trade) {
                if (strtolower($trade['trade_type']) === 'sell' && 
                    isset($trade['entry_price']) && 
                    isset($trade['invested']) && 
                    isset($trade['profit_loss'])) {
                    $fifoWorking = true;
                    break;
                }
            }
            
            if ($fifoWorking) {
                $this->addResult('fifo_logic', "FIFO matching logic is working correctly");
            } else {
                $this->addWarning('fifo_logic', "FIFO matching may not be working correctly - no sell trades with calculated P/L found");
            }
        } else {
            $this->addWarning('fifo_logic', "Not enough trades to verify FIFO logic");
        }
    }
    
    /**
     * Verify exchange connections
     */
    private function verifyExchangeConnections() {
        // Check for CCXT
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            $this->addWarning('ccxt', "CCXT may not be installed (vendor/autoload.php not found)");
            return;
        }
        
        // Check for exchange trader classes
        $exchangeDir = __DIR__ . '/../includes/exchanges';
        if (!is_dir($exchangeDir)) {
            $this->addWarning('exchange_classes', "Exchange directory not found at $exchangeDir");
            return;
        }
        
        $exchangeFiles = glob($exchangeDir . '/*Trader.php');
        if (empty($exchangeFiles)) {
            $this->addWarning('exchange_classes', "No exchange trader classes found in $exchangeDir");
            return;
        }
        
        $this->addResult('exchange_classes', count($exchangeFiles) . " exchange trader classes found");
        
        // Try to load one exchange class as a test
        try {
            if (file_exists($exchangeDir . '/BitvavoTrader.php')) {
                require_once $exchangeDir . '/BitvavoTrader.php';
                $this->addResult('exchange_class_load', "Successfully loaded BitvavoTrader class");
            } else {
                $this->addWarning('exchange_class_load', "BitvavoTrader.php not found");
            }
        } catch (Exception $e) {
            $this->addError('exchange_class_load', "Failed to load exchange class: " . $e->getMessage());
        }
    }
    
    /**
     * Verify portfolio consistency with trades
     */
    private function verifyPortfolioConsistency() {
        try {
            // Get portfolio balances
            $stmt = $this->db->query("SELECT coin_id, amount FROM portfolio WHERE amount > 0");
            $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($portfolio as $position) {
                $coinId = $position['coin_id'];
                $portfolioAmount = (float)$position['amount'];
                
                // Calculate expected balance from trades
                $stmt = $this->db->prepare("
                    SELECT 
                        SUM(CASE WHEN trade_type = 'buy' THEN amount ELSE -amount END) as net_amount 
                    FROM trades 
                    WHERE coin_id = ?
                ");
                $stmt->execute([$coinId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $tradeNetAmount = (float)($result['net_amount'] ?? 0);
                
                // Compare with tolerance for floating point errors
                $difference = abs($portfolioAmount - $tradeNetAmount);
                if ($difference < 0.000001) {
                    $this->addResult('portfolio_' . $coinId, "Portfolio balance for $coinId matches trade history");
                } else {
                    $this->addWarning('portfolio_' . $coinId, "Portfolio balance for $coinId ($portfolioAmount) doesn't match trade history ($tradeNetAmount)");
                }
            }
        } catch (Exception $e) {
            $this->addError('portfolio_consistency', "Failed to check portfolio consistency: " . $e->getMessage());
        }
    }
    
    private function addResult($key, $message) {
        $this->results[$key] = $message;
    }
    
    private function addError($key, $message) {
        $this->errors[$key] = $message;
    }
    
    private function addWarning($key, $message) {
        $this->warnings[$key] = $message;
    }
}

// Output function - can output either HTML or plain text depending on context
function outputResults($diagnosticResults) {
    global $isSystemTools;
    
    if ($isSystemTools) {
        // Plain text output for system-tools
        echo "=== NIGHT STALKER TRADE DIAGNOSTICS ===\n\n";
        
        echo "=== SUMMARY ===\n";
        echo "✓ " . count($diagnosticResults['results']) . " passed\n";
        echo "⚠ " . count($diagnosticResults['warnings']) . " warnings\n";
        echo "✗ " . count($diagnosticResults['errors']) . " errors\n\n";
        
        if (!empty($diagnosticResults['errors'])) {
            echo "=== ERRORS ===\n";
            foreach ($diagnosticResults['errors'] as $key => $message) {
                echo "✗ {$message}\n";
            }
            echo "\n";
        }
        
        if (!empty($diagnosticResults['warnings'])) {
            echo "=== WARNINGS ===\n";
            foreach ($diagnosticResults['warnings'] as $key => $message) {
                echo "⚠ {$message}\n";
            }
            echo "\n";
        }
        
        echo "=== SUCCESSFUL CHECKS ===\n";
        foreach ($diagnosticResults['results'] as $key => $message) {
            echo "✓ {$message}\n";
        }
    } else {
        // HTML output for direct browser access
        ?><!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Night Stalker Trade Diagnostics</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                .result-item {
                    margin-bottom: 8px;
                    padding: 8px;
                    border-bottom: 1px solid #eee;
                }
                .success {
                    color: green;
                    font-weight: bold;
                }
                .warning {
                    color: orange;
                    font-weight: bold;
                }
                .error {
                    color: red;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="container mt-4">
                <h1 class="mb-4">Night Stalker Trade Diagnostics</h1>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Summary</h3>
                            </div>
                            <div class="card-body">
                                <p><span class="success">✓</span> <strong><?= count($diagnosticResults['results']) ?></strong> checks passed</p>
                                <p><span class="warning">⚠</span> <strong><?= count($diagnosticResults['warnings']) ?></strong> warnings</p>
                                <p><span class="error">✗</span> <strong><?= count($diagnosticResults['errors']) ?></strong> errors</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($diagnosticResults['errors'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Errors</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($diagnosticResults['errors'] as $key => $message): ?>
                                    <div class="result-item">
                                        <span class="error">✗</span> <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($message) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($diagnosticResults['warnings'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Warnings</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($diagnosticResults['warnings'] as $key => $message): ?>
                                    <div class="result-item">
                                        <span class="warning">⚠</span> <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($message) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Successful Checks</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($diagnosticResults['results'] as $key => $message): ?>
                                    <div class="result-item">
                                        <span class="success">✓</span> <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($message) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <a href="/" class="btn btn-primary">Return to Dashboard</a>
                    </div>
                </div>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
    }
}

// Run diagnostics
$verifier = new TradeOperationVerifier();
$diagnosticResults = $verifier->runDiagnostics();

// Output results
outputResults($diagnosticResults);

// End output buffering if we're running from system-tools
if ($isSystemTools) {
    $output = ob_get_clean();
    echo $output;
    
    // Save to log file if running from system-tools
    $logDir = '/opt/lampp/htdocs/NS/system-tools/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Ymd_His');
    $logFile = "{$logDir}/trade_diagnostics_{$timestamp}.log";
    file_put_contents($logFile, $output);
}
