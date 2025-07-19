<?php
// MUST COME FIRST
$title = "Night Stalker - built from the remains of a decommmissioned tsunami prediction warning system's Artificial Intelligence, it's new mission objectives to track and exploit a vulnerability discovered in all the new coins, which allows this system to predict and benefit from their price movements."; // Set title first

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Verify authentication
if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';

// Fetch data with error handling
try {
    // Use the PDO-compatible versions of these functions
    $newCoins = getNewCryptocurrenciesPDO() ?? [];
    $trendingCoins = getTrendingCoinsPDO() ?? [];
    $recentTrades = getRecentTradesPDO(10) ?? [];
    $stats = getTradingStatsPDO() ?? [
        'total_trades' => 0,
        'active_trades' => 0, 
        'total_profit' => 0,
        'total_volume' => 0
    ];
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    // Initialize empty data on error
    $newCoins = $trendingCoins = $recentTrades = [];
    $stats = [
        'total_trades' => 0,
        'active_trades' => 0,
        'total_profit' => 0,
        'total_volume' => 0
    ];
}
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mt-4">
                <i class="fas fa-ghost"></i> Night Stalker Mode
                <small class="text-muted">Tracking crypto volume spikes</small>
            </h1>
            
            <div class="alert alert-dark">
                <div class="row">
                    <div class="col-md-4">
                        <strong>New Coins:</strong> <?= count($newCoins) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Trending:</strong> <?= count($trendingCoins) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Last Scan:</strong> <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($trendingCoins)): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-bolt"></i> Trending Coins (High Volume)
                        </h3>
                        <small>Volume > $<?= number_format(MIN_VOLUME_THRESHOLD) ?></small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-3 g-4" id="trending-coins">
                        <?php foreach ($trendingCoins as $coin): ?>
                            <?php include __DIR__ . '/includes/coin_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-coins"></i> All New Coins (Last 24h)
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 g-4" id="all-new-coins">
                        <?php foreach ($newCoins as $coin): ?>
                            <?php include __DIR__ . '/includes/coin_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-exchange-alt"></i> Recent Trades
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recentTrades)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Coin</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTrades as $trade): ?>
                                    <tr class="<?= $trade['trade_type'] === 'buy' ? 'table-success' : 'table-danger' ?>">
                                        <td><?= htmlspecialchars($trade['symbol']) ?></td>
                                        <td><?= strtoupper($trade['trade_type']) ?></td>
                                        <td class="text-end"><?= number_format($trade['amount'], 4) ?></td>
                                        <td class="text-end">$<?= number_format($trade['total_value'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exchange-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No recent trades</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line"></i> Trading Stats
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Trades
                            <span class="badge bg-primary rounded-pill"><?= $stats['total_trades'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active Positions
                            <span class="badge bg-success rounded-pill"><?= $stats['active_trades'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total P/L
                            <span class="badge bg-<?= $stats['total_profit'] >= 0 ? 'success' : 'danger' ?> rounded-pill">
                                $<?= number_format($stats['total_profit'], 2) ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Volume
                            <span class="badge bg-info rounded-pill">
                                $<?= number_format($stats['total_volume'], 2) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
