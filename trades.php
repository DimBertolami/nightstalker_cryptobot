<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdo_functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';


// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug database connection and trade_log table
try {
    $db = getDBConnection();
    if (!$db) {
        error_log("Database connection failed in trades.php");
    } else {
        // Check if trade_log table exists
        $checkTable = $db->query("SHOW TABLES LIKE 'trade_log'");
        if ($checkTable->rowCount() > 0) {
            error_log("trade_log table exists");
            
            // Check count of records
            $countStmt = $db->query("SELECT COUNT(*) as count FROM trade_log");
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            error_log("trade_log record count: " . ($countResult['count'] ?? 'unknown'));
            
            // Check structure
            $structureStmt = $db->query("DESCRIBE trade_log");
            $columns = $structureStmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("trade_log columns: " . implode(", ", $columns));
        } else {
            error_log("trade_log table does not exist");
        }
    }
} catch (Exception $e) {
    error_log("Database debug error: " . $e->getMessage());
}

$title = "Trade History";
require_once __DIR__ . '/includes/header.php';

try {
    error_log("Starting to fetch trades in trades.php");
    // Use the new function to get trade data from trade_log table
    $trades = getTradeLogWithMarketDataPDO(100);
    error_log("Trade data fetched: " . json_encode([
        'count' => count($trades),
        'first_few' => array_slice($trades, 0, 3)
    ]));

    // Additional debug: log entire trades array if count is zero
    if (count($trades) === 0) {
        error_log("No trades found in trades.php after fetching from getTradeLogWithMarketDataPDO");
    } else {
        error_log("Trades array sample: " . json_encode(array_slice($trades, 0, 10)));
    }
    
    // Sort trades by date descending (newest first)
    usort($trades, function($a, $b) {
        return strtotime($b['trade_time']) <=> strtotime($a['trade_time']);
    });
    error_log("Trades sorted successfully");
} catch (Exception $e) {
    $_SESSION['error'] = "Could not load trade history. Please try again later.";
    error_log("Trade history error: " . $e->getMessage());
    $trades = [];
}
?>

<?php
error_log("Rendering trades.php with trades count: " . count($trades));
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
                            <?php error_log("No trades available to display in trades.php"); ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped datatable background-color: #061e36; color: rgb(241, 207, 10);">
                                <thead background-color: #061e36; color: rgb(241, 207, 10);">
                                    <tr>
                                        <th>Date</th>
                                        <th>Coin</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Price</th>
                                        <th>Current</th>
                                        <th>Value</th>
                                        <th>Strategy</th>
                                    </tr>
                                </thead>
                                <tbody background-color: #061e36; color: rgb(241, 207, 10);">
                                    <?php foreach ($trades as $trade): ?>
                                    <?php
                                        $currentPrice = $trade['current_price'] ?? 0;
                                        $entryPrice = $trade['price'] ?? 0;
                                        $amount = $trade['amount'] ?? 0;
                                        $invested = $trade['total_value'] ?? ($amount * $entryPrice);
                                        $symbol = $trade['symbol'] ?? 'UNKNOWN';
                                        $tradeType = strtolower($trade['trade_type'] ?? 'unknown');
                                        $isBuy = $tradeType === 'buy';
                                        $tradeTime = $trade['trade_time'] ?? date('Y-m-d H:i:s');
                                        $strategy = $trade['strategy'] ?? 'manual';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($tradeTime))) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="assets/img/coins/<?= strtolower($symbol) ?>.png" 
                                                     alt="<?= htmlspecialchars($symbol) ?>"
                                                     class="coin-icon me-2"
                                                     onerror="this.onerror=null; this.src='assets/img/coins/generic.png';">
                                                <div>
                                                    <strong><?= htmlspecialchars($symbol) ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $isBuy ? 'bg-success' : 'bg-danger' ?>">
                                                <?= strtoupper($tradeType) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($amount, 8) ?></td>
                                        <td>$<?= is_numeric($entryPrice) ? rtrim(rtrim(number_format($entryPrice, 4, '.', ''), '0'), '.') : '–' ?></td>
                                        <td>$<?= is_numeric($currentPrice) ? rtrim(rtrim(number_format($currentPrice, 4, '.', ''), '0'), '.') : '–' ?></td>
                                        <td>$<?= is_numeric($invested) ? number_format($invested, 2) : '–' ?></td>
                                        <td><?= htmlspecialchars($strategy) ?></td>
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
<script>
	document.addEventListener('DOMContentLoaded', function() {
	// List of your background images (use paths relative to your web root)
	const backgroundImages = ['/NS/assets/images/oni1.png','/NS/assets/images/oni2.png','/NS/assets/images/oni3.png','/NS/assets/images/oni4.png']
        let currentIndex = 0;
        const body = document.body;

	// Set initial background properties (from our previous discussion)
	body.style.backgroundSize = 'cover';
	body.style.backgroundPosition = 'center center';
	body.style.backgroundRepeat = 'no-repeat';
	body.style.backgroundAttachment = 'fixed'; // Keeps the image fixed while scrolling

	function changeBackground() {
		currentIndex = (currentIndex + 1) % backgroundImages.length; // Cycle through images
		body.style.backgroundImage = `url('${backgroundImages[currentIndex]}')`;
	}
	// Set the very first background image immediately when the page loads
	body.style.backgroundImage = `url('${backgroundImages[currentIndex]}')`;
	// Change background every 20 seconds (20000 milliseconds)
	setInterval(changeBackground, 20000);
	});
</script>

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
