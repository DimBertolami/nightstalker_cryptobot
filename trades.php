<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdo_functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$title = "Trade History";
require_once __DIR__ . '/includes/header.php';

try {
    $trades = getRecentTradesWithMarketDataPDO(100);
} catch (Exception $e) {
    $_SESSION['error'] = "Could not load trade history. Please try again later.";
    error_log("Trade history error: " . $e->getMessage());
    $trades = [];
}
?>

<?php
// Fetch latest coin prices from DB
$db = getDBConnection();
$coinPrices = [];
if (!empty($trades)) {
    $symbols = array_unique(array_column($trades, 'symbol'));
    $stmtPrice = $db->prepare("SELECT symbol, current_price FROM coins WHERE symbol = ?");
    foreach ($symbols as $sym) {
        try {
            $stmtPrice->execute([$sym]);
            $r = $stmtPrice->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $coinPrices[$r['symbol']] = (float)$r['current_price'];
            }
        } catch (Exception $e) {
            error_log("Error fetching price for $sym: " . $e->getMessage());
        }
    }
}
?>

<div class="container-fluid background-color:rgb(69, 3, 75); color: rgb(241, 207, 10);">
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
                <div class="card-header background-color: #061e36; color: rgb(241, 207, 10);">
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
                <div class="card-body background-color:rgb(10, 88, 167); color: rgb(241, 207, 10);">
                    <?php if (empty($trades)): ?>
                        <div class="alert alert-warning">
                            No trade history found or market data unavailable.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped datatable background-color: #061e36; color: rgb(241, 207, 10);">
                                <thead background-color: #061e36; color: rgb(241, 207, 10);>
                                    <tr>
                                        <th>Date</th>
                                        <th>Coin</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Entry Price</th>
                                        <th>Current Price</th>
                                        <th>Invested</th>
                                        
                                        <th>P/L</th>
                                    </tr>
                                </thead>
                                <tbody background-color: #061e36; color: rgb(241, 207, 10);>
                                    <?php foreach ($trades as $trade): ?>
                                    <?php
                                        // Use backend-calculated values
                                        $entryPrice = $trade['entry_price'];
                                        $currentPrice = $trade['current_price'];
                                        $invested = $trade['invested'];
                                        $profitLoss = $trade['profit_loss'];
                                        $profitLossPercent = $trade['profit_loss_percent'];
                                        $isBuy = strtolower($trade['trade_type']) === 'buy';
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
                                        <td><?= is_numeric($trade['amount']) ? rtrim(rtrim(number_format($trade['amount'], 4, '.', ''), '0'), '.') : '–' ?></td>
                                        <td>$<?= is_numeric($entryPrice) ? rtrim(rtrim(number_format($entryPrice, 4, '.', ''), '0'), '.') : '–' ?></td>
                                        <td>$<?= is_numeric($currentPrice) ? rtrim(rtrim(number_format($currentPrice, 4, '.', ''), '0'), '.') : '–' ?></td>
                                        <td>$<?= is_numeric($invested) ? number_format($invested, 2) : '–' ?></td>
                                        
                                        <td class="<?= (is_numeric($profitLoss) && $profitLoss >= 0) ? 'text-success' : ((is_numeric($profitLoss)) ? 'text-danger' : '') ?>">
                                            <?php if (!is_numeric($profitLoss)): ?>
                                                –
                                            <?php else: ?>
                                                $<?= number_format($profitLoss, 2) ?>
                                                (<?= is_numeric($profitLossPercent) ? number_format($profitLossPercent, 2) : '–' ?>%)
                                            <?php endif; ?>
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
