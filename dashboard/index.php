<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';
require_once __DIR__ . '/../includes/auth.php';

// Initialize the trading logger
$logger = new TradingLogger();

// Get selected strategy from session or default to new_coin_strategy
session_start();
if (isset($_POST['strategy'])) {
    $_SESSION['selected_strategy'] = $_POST['strategy'];
}
$selectedStrategy = $_SESSION['selected_strategy'] ?? 'new_coin_strategy';

// Check if user is logged in
requireAuth();

// Get trading statistics
$stats = $logger->getStats($selectedStrategy);

// Get recent trading events
$recentEvents = $logger->getRecentEvents($selectedStrategy, 50);

// Get performance metrics for different time periods
$dayPerformance = $logger->getPerformance($selectedStrategy, 'day');
$weekPerformance = $logger->getPerformance($selectedStrategy, 'week');
$monthPerformance = $logger->getPerformance($selectedStrategy, 'month');
$allTimePerformance = $logger->getPerformance($selectedStrategy, 'all');

// These functions are now imported from functions.php
// No need to define them here anymore

// formatDuration is now imported from functions.php
// No need to define it here anymore

// Check if there's an active trade
$activeTrade = !empty($stats['active_trade_symbol']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Night Stalker Trading Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta http-equiv="refresh" content="300"> <!-- Auto refresh every 5 minutes -->
</head>
<body>
    <?php 
    // Check if we're in test mode
    $testMode = true; // Default to test mode for safety
    $configFile = __DIR__ . '/../crons/execute_new_coin_strategy.php';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        if (preg_match('/\$testMode\s*=\s*(true|false)/', $configContent, $matches)) {
            $testMode = $matches[1] === 'true';
        }
    }
    
    // Include navigation
    include_once('nav.php'); 
    ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Trading Dashboard</h1>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">Last updated: <?php echo date('Y-m-d H:i'); ?></span>
                <button id="refresh-btn" class="btn btn-sm btn-primary">Refresh</button>
            </div>
        </div>

        <!-- Active Trade Section -->
        <?php if ($activeTrade): ?>
        <div class="card mb-4" id="active-trade-section">
            <div class="card-header">
                <i class="bi bi-lightning-charge"></i> Active Trade
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stats['active_trade_symbol']; ?></div>
                            <div class="stat-label">Symbol</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo formatCurrency($stats['buy_price'], 8); ?></div>
                            <div class="stat-label">Buy Price</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo formatCurrency($stats['current_price'], 8); ?></div>
                            <div class="stat-label">Current Price</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value" id="profit-percentage">Calculating...</div>
                            <div class="stat-label">Current Profit/Loss</div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-value" id="holding-time">
                                <?php 
                                    $holdingTime = isset($stats['holding_time_seconds']) ? $stats['holding_time_seconds'] : 0;
                                    echo formatDuration($holdingTime);
                                ?>
                            </div>
                            <div class="stat-label">Holding Time</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-value" id="time-to-sell">
                                <?php 
                                    $timeToSell = isset($stats['time_to_sell_seconds']) ? $stats['time_to_sell_seconds'] : 0;
                                    echo formatDuration($timeToSell);
                                ?>
                            </div>
                            <div class="stat-label">Time to Sell</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="price-chart-container">
                            <canvas id="price-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Performance Overview -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bar-chart"></i> Performance Overview
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-trophy"></i> Trading Statistics
                    </div>
                    <div class="card-body">
                        <div class="stat-card">
                            <div class="stat-value <?php echo ($allTimePerformance['total_profit'] ?? 0) > 0 ? 'positive' : (($allTimePerformance['total_profit'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                <?php echo formatCurrency($allTimePerformance['total_profit'] ?? 0, 2); ?> EUR
                            </div>
                            <div class="stat-label">Total Profit</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php echo $allTimePerformance['buy_count'] ?? 0; ?> / <?php echo $allTimePerformance['sell_count'] ?? 0; ?>
                            </div>
                            <div class="stat-label">Buys / Sells</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php echo $stats['total_trades'] ?? 0; ?>
                            </div>
                            <div class="stat-label">Total Trades</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php echo $stats['profitable_trades'] ?? 0; ?>
                            </div>
                            <div class="stat-label">Profitable Trades</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php echo $stats['loss_making_trades'] ?? 0; ?>
                            </div>
                            <div class="stat-label">Loss-Making Trades</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php echo formatPercentage($stats['win_rate'] ?? 0); ?>
                            </div>
                            <div class="stat-label">Win Rate</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value <?php echo ($stats['avg_profit_percentage'] ?? 0) > 0 ? 'positive' : (($stats['avg_profit_percentage'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                <?php echo formatPercentage($stats['avg_profit_percentage'] ?? 0); ?>
                            </div>
                            <div class="stat-label">Avg. Profit/Loss</div>
                        </div>
                        <?php if (!empty($allTimePerformance['best_trade'])): ?>
                        <div class="stat-card">
                            <div class="stat-value positive"><?php echo formatPercentage($allTimePerformance['best_trade']['profit_percentage']); ?></div>
                            <div class="stat-label">Best Trade (<?php echo $allTimePerformance['best_trade']['symbol']; ?>)</div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($allTimePerformance['worst_trade'])): ?>
                        <div class="stat-card">
                            <div class="stat-value negative"><?php echo formatPercentage($allTimePerformance['worst_trade']['profit_percentage']); ?></div>
                            <div class="stat-label">Worst Trade (<?php echo $allTimePerformance['worst_trade']['symbol']; ?>)</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trading Performance -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Trading Performance</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" id="view-profit-chart">Profit</button>
                        <button type="button" class="btn btn-outline-info" id="view-cumulative-chart">Cumulative</button>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="performance-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Trading Activity -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Trading Activity</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-success" data-filter="buy">Buys</button>
                        <button type="button" class="btn btn-outline-danger" data-filter="sell">Sells</button>
                        <button type="button" class="btn btn-outline-warning" data-filter="monitor">Monitor</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="recent-events-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Event</th>
                                    <th>Symbol</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEvents as $event): ?>
                                <tr>
                                    <td><?php echo $event['event_time']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getEventBadgeClass($event['event_type']); ?>">
                                            <?php echo ucfirst($event['event_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $eventData = json_decode($event['event_data'], true);
                                        echo isset($eventData['symbol']) ? $eventData['symbol'] : 'N/A'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($event['event_type'] == 'buy'): ?>
                                            Buy Price: <?php echo formatCurrency($eventData['buy_price'], 8); ?>, 
                                            Amount: <?php echo $eventData['amount']; ?>, 
                                            Total: <?php echo formatCurrency($eventData['total'], 2); ?>
                                        <?php elseif ($event['event_type'] == 'sell'): ?>
                                            Sell Price: <?php echo formatCurrency($eventData['sell_price'], 8); ?>, 
                                            Amount: <?php echo $eventData['amount']; ?>, 
                                            Total: <?php echo formatCurrency($eventData['total'], 2); ?>, 
                                            Profit: <span class="<?php echo ($eventData['profit'] ?? 0) > 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo formatCurrency($eventData['profit'] ?? 0, 2); ?> (<?php echo formatPercentage($eventData['profit_percentage'] ?? 0); ?>)
                                            </span>
                                            <div class="d-inline-flex align-items-center ms-2">
                                                <div class="ms-2">
                                                    <?php if (isset($eventData['holding_time_seconds'])): ?>
                                                    <div class="custom-tooltip">
                                                        <i class="bi bi-clock-history"></i>
                                                        <span class="tooltip-text">
                                                            Buy Price: <?php echo formatCurrency($eventData['buy_price'], 8); ?><br>
                                                            Highest Price: <?php echo formatCurrency($eventData['highest_price'], 8); ?><br>
                                                            Holding Time: <?php echo formatDuration($eventData['holding_time_seconds']); ?>
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($event['event_type'] == 'monitor'): ?>
                                            Price: <?php echo formatCurrency($eventData['current_price'], 8); ?>, 
                                            Change: <span class="<?php echo ($eventData['price_change'] ?? 0) > 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo formatPercentage($eventData['price_change'] ?? 0); ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo json_encode($eventData); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentEvents)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No trading activity yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 11"></div>

    <!-- Controls -->
    <div class="position-fixed bottom-0 start-0 p-3 d-flex gap-3" style="z-index: 11">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="auto-refresh-toggle">
            <label class="form-check-label text-white bg-dark p-2 rounded" for="auto-refresh-toggle">Auto Refresh</label>
        </div>
    </div>

    <!-- Refresh Button -->
    <a href="index.php" class="btn btn-primary btn-lg refresh-btn">
        <i class="bi bi-arrow-clockwise"></i>
    </a>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Performance Chart Initialization -->
    <script>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
                datasets: [{
                    label: 'Profit/Loss',
                    data: [
                        <?php echo $dayPerformance['total_profit'] ?? 0; ?>,
                        <?php echo ($weekPerformance['total_profit'] ?? 0) / 7; ?>,
                        <?php echo ($weekPerformance['total_profit'] ?? 0) / 7; ?>,
                        <?php echo ($weekPerformance['total_profit'] ?? 0) / 7; ?>,
                        <?php echo ($weekPerformance['total_profit'] ?? 0) / 7; ?>,
                        <?php echo ($weekPerformance['total_profit'] ?? 0) / 7; ?>,
                        <?php echo ($weekPerformance['total_profit'] ?? 0) / 7; ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    
    <!-- Custom Dashboard JavaScript -->
    <script src="dashboard.js"></script>
</body>
</html>
