<?php
/**
 * Night Stalker Trade Tables Synchronization Tool
 * 
 * This tool synchronizes trades between the trade_log and trades tables
 * to ensure all trades are properly recorded and displayed in the trade history.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdo_functions.php';
require_once __DIR__ . '/../includes/database.php';

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

class TradeSynchronizer {
    private $db;
    private $syncResults = [
        'processed' => 0,
        'synced' => 0,
        'skipped' => 0,
        'errors' => 0,
        'details' => []
    ];
    
    public function __construct() {
        try {
            $this->db = getDBConnection();
        } catch (Exception $e) {
            die("Failed to connect to database: " . $e->getMessage());
        }
    }
    
    /**
     * Synchronize trades from trade_log to trades table
     */
    public function synchronizeTrades() {
        try {
            // Get all trades from trade_log
            $stmt = $this->db->query("SELECT * FROM trade_log ORDER BY trade_date ASC");
            $logTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->syncResults['processed'] = count($logTrades);
            
            foreach ($logTrades as $logTrade) {
                try {
                    // Check if this trade already exists in trades table
                    $stmt = $this->db->prepare("
                        SELECT COUNT(*) as count FROM trades 
                        WHERE coin_id = ? AND amount = ? AND price = ? AND trade_type = ? AND 
                              ABS(TIMESTAMPDIFF(SECOND, trade_time, ?)) < 10
                    ");
                    $stmt->execute([
                        $logTrade['coin_id'], 
                        $logTrade['amount'], 
                        $logTrade['price'], 
                        $logTrade['action'],
                        $logTrade['trade_date']
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['count'] > 0) {
                        // Trade already exists, skip
                        $this->syncResults['skipped']++;
                        $this->syncResults['details'][] = [
                            'status' => 'skipped',
                            'message' => "exists: {$logTrade['symbol']} {$logTrade['action']} " . number_format((float)$logTrade['amount'], 6, '.', '') . " at " . number_format((float)$logTrade['price'], 2, '.', '') . " on {$logTrade['trade_date']}",
                            'trade_log_id' => $logTrade['id']
                        ];
                        continue;
                    }
                    
                    // Insert into trades table
                    $this->db->beginTransaction();
                    
                    $totalValue = $logTrade['amount'] * $logTrade['price'];
                    $stmt = $this->db->prepare("
                        INSERT INTO trades 
                            (coin_id, trade_type, amount, price, total_value, trade_time) 
                        VALUES 
                            (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $logTrade['coin_id'],
                        $logTrade['action'],
                        $logTrade['amount'],
                        $logTrade['price'],
                        $totalValue,
                        $logTrade['trade_date']
                    ]);
                    
                    // Update portfolio for buy trades
                    if (strtolower($logTrade['action']) === 'buy') {
                        $this->updatePortfolioAfterBuy(
                            $logTrade['coin_id'],
                            $logTrade['amount'],
                            $logTrade['price']
                        );
                    }
                    
                    $this->db->commit();
                    
                    $this->syncResults['synced']++;
                    $this->syncResults['details'][] = [
                        'status' => 'synced',
                        'message' => "synced: {$logTrade['symbol']} {$logTrade['action']} " . number_format((float)$logTrade['amount'], 6, '.', '') . " at " . number_format((float)$logTrade['price'], 2, '.', '') . " on {$logTrade['trade_date']}",
                        'trade_log_id' => $logTrade['id']
                    ];
                    
                } catch (Exception $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    
                    $this->syncResults['errors']++;
                    $this->syncResults['details'][] = [
                        'status' => 'error',
                        'message' => "Error syncing trade {$logTrade['id']}: " . $e->getMessage(),
                        'trade_log_id' => $logTrade['id']
                    ];
                }
            }
            
            return $this->syncResults;
            
        } catch (Exception $e) {
            die("Synchronization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update portfolio after buy
     */
    private function updatePortfolioAfterBuy($coinId, $amount, $price) {
        // Check if we already have this coin in portfolio
        $stmt = $this->db->prepare("
            SELECT * FROM portfolio 
            WHERE user_id = 1 AND coin_id = ?
        ");
        $stmt->execute([$coinId]);
        $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
    }
}

// Output function - can output either HTML or plain text depending on context
function outputResults($results) {
    global $isSystemTools;
    
    if ($isSystemTools) {
        // Plain text output for system-tools
        echo "=== NIGHT STALKER TRADE TABLES SYNCHRONIZATION ===\n\n";
        
        echo "=== SUMMARY ===\n";
        echo "Processed: {$results['processed']} trades\n";
        echo "✓ Synced: {$results['synced']} trades\n";
        echo "⚠ Skipped: {$results['skipped']} trades (already exist)\n";
        echo "✗ Errors: {$results['errors']} trades\n\n";
        
        echo "=== DETAILS ===\n";
        foreach ($results['details'] as $detail) {
            $symbol = $detail['status'] === 'synced' ? '✓' : ($detail['status'] === 'skipped' ? '⚠' : '✗');
            echo "{$symbol} {$detail['trade_log_id']}: {$detail['message']}\n";
        }
    } else {
        // HTML output for direct browser access
        ?><!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Night Stalker Trade Tables Synchronization</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #1a1a2e;
                    color: #e6e6e6;
                }
                .card {
                    background-color: #16213e;
                    border: 1px solid #0f3460;
                    margin-bottom: 20px;
                }
                .card-header {
                    background-color: #0f3460;
                    color: #e6e6e6;
                }
                .success {
                    color: #4cd137;
                }
                .warning {
                    color: #fbc531;
                }
                .error {
                    color: #e84118;
                }
                .result-item {
                    padding: 8px;
                    border-bottom: 1px solid #2c3e50;
                }
            </style>
        </head>
        <body>
            <div class="container mt-4">
                <h1 class="mb-4">Night Stalker Trade Tables Synchronization</h1>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Summary</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>Processed:</strong> <?= $results['processed'] ?> trades</p>
                                <p><span class="success">✓</span> <strong>Synced:</strong> <?= $results['synced'] ?> trades</p>
                                <p><span class="warning">⚠</span> <strong>Skipped:</strong> <?= $results['skipped'] ?> trades (already exist)</p>
                                <p><span class="error">✗</span> <strong>Errors:</strong> <?= $results['errors'] ?> trades</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3>Details</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($results['details'] as $detail): ?>
                                    <div class="result-item">
                                        <?php if ($detail['status'] === 'synced'): ?>
                                            <span class="success">✓</span>
                                        <?php elseif ($detail['status'] === 'skipped'): ?>
                                            <span class="warning">⚠</span>
                                        <?php else: ?>
                                            <span class="error">✗</span>
                                        <?php endif; ?>
                                        <strong><?= $detail['trade_log_id'] ?>:</strong> <?= htmlspecialchars($detail['message']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <a href="/" class="btn btn-primary">Return to Dashboard</a>
                        <a href="/NS/trades.php" class="btn btn-success">View Trade History</a>
                    </div>
                </div>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
    }
}

// Run synchronization
$synchronizer = new TradeSynchronizer();
$results = $synchronizer->synchronizeTrades();

// Output results
outputResults($results);

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
    $logFile = "{$logDir}/sync_trade_tables_{$timestamp}.log";
    file_put_contents($logFile, $output);
}
