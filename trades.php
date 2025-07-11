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
    
    // Sort trades by date descending (newest first)
    usort($trades, function($a, $b) {
        return strtotime($b['trade_time']) <=> strtotime($a['trade_time']);
    });
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
                            <button id="filter-buy" class="badge bg-success me-2 border-0" style="cursor: pointer;">
                                <?= count(array_filter($trades, fn($t) => $t['trade_type'] === 'buy')) ?> Buys
                            </button>
                            <button id="filter-sell" class="badge bg-danger me-2 border-0" style="cursor: pointer;">
                                <?= count(array_filter($trades, fn($t) => $t['trade_type'] === 'sell')) ?> Sells
                            </button>
                            <button id="filter-all" class="badge bg-secondary me-2 border-0" style="cursor: pointer;">
                                All Trades
                            </button>
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
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get filter buttons
    const filterBuyBtn = document.getElementById('filter-buy');
    const filterSellBtn = document.getElementById('filter-sell');
    const filterAllBtn = document.getElementById('filter-all');
    
    // Get all trade rows
    const tradeRows = document.querySelectorAll('table.datatable tbody tr');
    
    // Function to filter trades
    function filterTrades(tradeType) {
        tradeRows.forEach(row => {
            // Find the trade type cell (3rd column)
            const typeCell = row.querySelector('td:nth-child(3)');
            
            if (tradeType === 'all') {
                // Show all rows
                row.style.display = '';
            } else {
                // Check if the trade type matches
                const badgeText = typeCell.querySelector('.badge').textContent.trim().toLowerCase();
                if (badgeText === tradeType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
        
        // Update active button styling
        [filterBuyBtn, filterSellBtn, filterAllBtn].forEach(btn => {
            btn.classList.remove('bg-primary');
            if (tradeType === 'buy' && btn === filterBuyBtn) {
                btn.classList.remove('bg-success');
                btn.classList.add('bg-primary');
            } else if (tradeType === 'sell' && btn === filterSellBtn) {
                btn.classList.remove('bg-danger');
                btn.classList.add('bg-primary');
            } else if (tradeType === 'all' && btn === filterAllBtn) {
                btn.classList.remove('bg-secondary');
                btn.classList.add('bg-primary');
            } else if (btn === filterBuyBtn) {
                btn.classList.add('bg-success');
            } else if (btn === filterSellBtn) {
                btn.classList.add('bg-danger');
            } else if (btn === filterAllBtn) {
                btn.classList.add('bg-secondary');
            }
        });
    }
    
    // Add click event listeners
    filterBuyBtn.addEventListener('click', () => filterTrades('buy'));
    filterSellBtn.addEventListener('click', () => filterTrades('sell'));
    filterAllBtn.addEventListener('click', () => filterTrades('all'));
});
</script>
