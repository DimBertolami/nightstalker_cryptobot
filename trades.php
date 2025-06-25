<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$title = "Trade History";
require_once __DIR__ . '/includes/header.php';

try {
    $trades = getRecentTradesWithMarketData(100);
} catch (Exception $e) {
    $_SESSION['error'] = "Could not load trade history. Please try again later.";
    error_log("Trade history error: " . $e->getMessage());
    $trades = [];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mt-4">
                <i class="fas fa-exchange-alt"></i> Trade History
                <small class="text-muted">All executed trades with real-time values</small>
            </h1>
            
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Recent Trades</h3>
                        <div>
                            <span class="badge bg-success me-2">
                                <?= count(array_filter($trades, fn($t) => $t['trade_type'] === 'buy')) ?> Buys
                            </span>
                            <span class="badge bg-danger">
                                <?= count(array_filter($trades, fn($t) => $t['trade_type'] === 'sell')) ?> Sells
                            </span>
                            <span class="badge bg-info ms-2" title="Last updated">
                                <i class="fas fa-sync-alt"></i> <?= date('H:i') ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($trades)): ?>
                        <div class="alert alert-warning">
                            No trade history found or market data unavailable.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped datatable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Coin</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Entry Price</th>
                                        <th>Current Price</th>
                                        <th>Invested</th>
                                        <th>Current Value</th>
                                        <th>P/L</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trades as $trade): ?>
                                    <?php
                                        $currentValue = $trade['amount'] * $trade['current_price'];
                                        $profitLoss = $currentValue - $trade['total_value'];
                                        // Check for division by zero
                                        $profitLossPercent = ($trade['total_value'] != 0) ? ($profitLoss / $trade['total_value']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($trade['trade_time']))) ?></td>
                                        <td>
                                            <?= htmlspecialchars($trade['symbol']) ?>
                                            <?php if ($trade['price_change_24h']): ?>
                                                <span class="badge bg-<?= $trade['price_change_24h'] >= 0 ? 'success' : 'danger' ?> ms-1">
                                                    <?= number_format($trade['price_change_24h'], 2) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $trade['trade_type'] === 'buy' ? 'success' : 'danger' ?>">
                                                <?= strtoupper($trade['trade_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($trade['amount'], 4) ?></td>
                                        <td>$<?= number_format($trade['price'], 4) ?></td>
                                        <td>$<?= number_format($trade['current_price'], 4) ?></td>
                                        <td>$<?= number_format($trade['total_value'], 2) ?></td>
                                        <td>$<?= number_format($currentValue, 2) ?></td>
                                        <td class="<?= $profitLoss >= 0 ? 'text-success' : 'text-danger' ?>">
                                            $<?= number_format($profitLoss, 2) ?>
                                            (<?= number_format($profitLossPercent, 2) ?>%)
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
