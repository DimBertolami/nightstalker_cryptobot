<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

// Set title before including header
$title = "All Cryptocurrencies - Real-time Data";

// Add custom CSS for new coin highlighting and real-time updates
$customCSS = <<<EOT
<style>
    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .new-coin {
        animation: blink 1s infinite;
        font-weight: bold;
        color: #dc3545;
    }
    
    .data-updated {
        animation: highlight 2s ease-out;
    }
    
    @keyframes highlight {
        0% { background-color: rgba(255, 255, 0, 0.5); }
        100% { background-color: transparent; }
    }
    
    #last-update {
        font-size: 0.8rem;
        color: #6c757d;
        margin-left: 10px;
    }
    
    #refresh-btn {
        margin-left: 10px;
    }
    
    #auto-refresh {
        margin-left: 10px;
    }
</style>
EOT;

require_once __DIR__ . '/includes/header.php';

// Verify authentication
//quireAuth();

// Get live market data
try {
    $marketData = fetchFromCMC();
    if (!$marketData) {
        throw new Exception("Failed to fetch live market data");
    }
    
    // Debug: Log market data structure
    error_log("Market data structure: " . print_r($marketData, true));

    // Get coins from database with additional data
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Try to get data from cryptocurrencies table first (new schema)
    $coinsQuery = "SELECT * FROM cryptocurrencies ORDER BY market_cap DESC";
    $coinsResult = $db->query($coinsQuery);
    
    // If cryptocurrencies table doesn't have data, try the coins table (old schema)
    if (!$coinsResult || $coinsResult->num_rows == 0) {
        $coinsQuery = "SELECT * FROM coins ORDER BY market_cap DESC";
        $coinsResult = $db->query($coinsQuery);
    }
    
    // Get the data as an associative array
    $coinsData = $coinsResult ? $coinsResult->fetch_all(MYSQLI_ASSOC) : [];
    
    // Initialize $coins variable before using it
    $coins = []; 
echo "</pre>";

    // Merge live data with database data
    $coins = array_map(function($coin) use ($marketData) {
        $symbol = $coin['symbol'];
        $liveData = $marketData[$symbol] ?? null;
        
        return [
            'id' => $coin['id'],
            'name' => $coin['name'],
            'symbol' => $symbol,
            'price' => (float)($liveData['price'] ?? $coin['current_price'] ?? 0),
            'price_change_24h' => (float)($liveData['change'] ?? $coin['price_change_24h'] ?? 0),
            'volume' => (float)($liveData['volume'] ?? $coin['volume_24h'] ?? 0),
            'market_cap' => (float)($liveData['market_cap'] ?? $coin['market_cap'] ?? 0),
            'date_added' => $liveData['date_added'] ?? $coin['date_added'] ?? null,
            'is_trending' => (bool)($coin['is_trending'] ?? false),
            'volume_spike' => (bool)($coin['volume_spike'] ?? false),
            'last_updated' => $coin['last_updated'] ?? null
        ];
    }, $coinsData);

} catch (Exception $e) {
    error_log("Market data error: " . $e->getMessage());
    $_SESSION['error'] = "Market data temporarily unavailable. Showing cached data.";
    $coins = [];
}

// Get user balances
$balances = [];
try {
    // Check if user is logged in, if not use a default user ID for testing
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    $balances = getUserBalance($userId);
} catch (Exception $e) {
    error_log("Balance error: " . $e->getMessage());
    $_SESSION['error'] = "Could not load portfolio balances.";
}
?>

<style>
    /* Table styles */
    #coins-table {
        width: 100%;
        margin: 20px 0;
    }
    #coins-table th {
        background: #343a40;
        color: white;
        padding: 12px;
        white-space: nowrap;
    }
    #coins-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
    }
    .positive-change {
        color: #28a745;
    }
    .negative-change {
        color: #dc3545;
    }
    .table-responsive {
        overflow-x: auto;
    }
    
    /* Trading form styles */
    .trade-form {
        display: flex;
        gap: 5px;
        margin-bottom: 5px;
    }
    .trade-form input[type="number"] {
        width: 80px;
    }
    .btn-buy {
        background-color: #28a745;
        border-color: #28a745;
    }
    .btn-sell {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    /* Balance display */
    .balance-badge {
        margin-right: 8px;
        margin-bottom: 5px;
    }
    
    /* Price indicators */
    .price-up {
        color: #28a745;
    }
    .price-down {
        color: #dc3545;
    }
    
    /* Status badges */
    .badge-trending {
        background-color: #ffc107;
        color: #212529;
    }
    .badge-volume-spike {
        background-color: #17a2b8;
        color: white;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mt-4">
                <i class="fas fa-coins"></i> All Cryptocurrencies
                <small class="text-muted">Live Market Data</small>
            </h1>
            
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- User Balances Display -->
<!-- User Balances Display -->
<div class="alert alert-info">
    <h5>Your Portfolio:</h5>
    <?php 
    // Get balances once at the start
    $balances = getUserBalance($_SESSION['user_id'] ?? 0); 
    ?>
    
    <?php if (!empty($balances)): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($balances as $symbol => $data): ?>
                <span class="badge bg-primary balance-badge">
                    <?= htmlspecialchars($symbol) ?>: 
                    <?= number_format((float)$data['balance'], 8) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <span class="text-muted">No cryptocurrency holdings yet</span>
    <?php endif; ?>
</div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-coins me-2"></i>All Cryptocurrencies
                        <span id="last-update"></span>
                        <button id="refresh-btn" class="btn btn-sm btn-light">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <div class="form-check form-switch d-inline-block" id="auto-refresh">
                            <input class="form-check-input" type="checkbox" id="auto-refresh-toggle" checked>
                            <label class="form-check-label text-white" for="auto-refresh-toggle">Auto-refresh</label>
                        </div>
                    </h5>
                    </div>
                </div>
                
                <?php if (empty($coins)): ?>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            No cryptocurrency data found. Please try again later.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="coins-table">
                            <thead>
                                <tr>
                                    <th>Coin</th>
                                    <th>Price</th>
                                    <th>24h Change</th>
                                    <th>Volume (24h)</th>
                                    <th>Market Cap</th>
                                    <th>Age</th>
                                    <th>Status</th>
                                    <th>Trade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Initial data rendered by PHP, will be replaced by AJAX -->
                                <?php foreach ($coins as $coin): ?>
                                <?php 
                                    $userBalance = $balances[$coin['symbol']] ?? 0;
                                    $canSell = $userBalance > 0;
                                    $priceChangeClass = $coin['price_change_24h'] >= 0 ? 'price-up' : 'price-down';
                                    
                                    // Calculate coin age
                                    $dateAdded = null;
                                    $isNew = false;
                                    $ageDisplay = 'Unknown';
                                    $ageClass = '';
                                    
                                    if (!empty($coin['date_added'])) {
                                        $dateAdded = new DateTime($coin['date_added']);
                                        $now = new DateTime();
                                        $interval = $dateAdded->diff($now);
                                        
                                        if ($interval->days > 0) {
                                            $ageDisplay = $interval->days . ' days';
                                        } else {
                                            $hours = $interval->h + ($interval->days * 24);
                                            $ageDisplay = $hours . ' hours';
                                            
                                            // Highlight if less than 24 hours old
                                            if ($hours < 24) {
                                                $isNew = true;
                                                $ageClass = 'new-coin';
                                            }
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($coin['name']) ?></div>
                                                <div class="text-muted"><?= htmlspecialchars($coin['symbol']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>$<?= number_format($coin['price'], $coin['price'] >= 1 ? 2 : 8) ?></td>
                                    <td class="<?= $priceChangeClass ?>">
                                        <?php if ($coin['price_change_24h'] >= 0): ?>
                                            <i class="fas fa-caret-up"></i>
                                        <?php else: ?>
                                            <i class="fas fa-caret-down"></i>
                                        <?php endif; ?>
                                        <?= number_format(abs($coin['price_change_24h']), 2) ?>%
                                    </td>
                                    <td>$<?= number_format($coin['volume'] ?? 0) ?></td>
                                    <td>$<?= number_format($coin['market_cap']) ?></td>
                                    <td class="<?= $ageClass ?>">
                                        <?= $ageDisplay ?>
                                        <?php if ($isNew): ?>
                                            <span class="badge bg-danger">NEW</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($coin['is_trending']): ?>
                                            <span class="badge badge-trending">Trending</span>
                                        <?php endif; ?>
                                        <?php if ($coin['volume_spike']): ?>
                                            <span class="badge badge-volume">Volume Spike</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="input-group input-group-sm me-2" style="width: 120px;">
                                                <input type="number" class="form-control trade-amount" 
                                                    placeholder="Amount" step="0.01" min="0.01">
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-success buy-btn" 
                                                    data-coin-id="<?= $coin['id'] ?>" 
                                                    data-symbol="<?= $coin['symbol'] ?>" 
                                                    data-price="<?= $coin['price'] ?>">
                                                    <i class="fas fa-shopping-cart"></i> Buy
                                                </button>
                                                <button type="button" class="btn btn-danger sell-btn" 
                                                    data-coin-id="<?= $coin['id'] ?>" 
                                                    data-symbol="<?= $coin['symbol'] ?>" 
                                                    data-price="<?= $coin['price'] ?>" 
                                                    <?= $canSell ? '' : 'disabled' ?>>
                                                    <i class="fas fa-money-bill-wave"></i> Sell
                                                </button>
                                            </div>
                                        </div>
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

<!-- Pass PHP variables to JavaScript -->
<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>

<!-- Load coins.js -->
<script src="<?= BASE_URL ?>/assets/js/coins.js" nonce="<?= $nonce ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
