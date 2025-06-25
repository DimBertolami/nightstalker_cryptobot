<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

// Set title before including header
$title = "Crypto Stalker - an early tsunami detection system but for crypto";

// Add custom CSS for new coin highlighting and real-time updates
$customCSS = <<<EOT
<style>
    div{
        background-color: #061e36;
        color: rgb(241, 207, 10);
        font-weight: bold;
    }
    
    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    .text-field{
        background-color: #061e36;
        color: rgb(241, 207, 10);
        font-weight: bold;
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
    
    /* Portfolio item styles */
    .portfolio-item {
        background-color: #061e36;
        color: rgb(241, 207, 10);
        font-weight: bold;
        portfolio-padding: 1px;
        portfolio-border-radius: 1px;
        portfolio-border: 1px solid #320755;
        portfolio-box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }
    
    .portfolio-item.zero-balance {
        opacity: 0.7;
    }
    
    .portfolio-item.zero-balance .font-weight-bold {
        color: #6c757d;
    }
    
    .portfolio-item:hover {
        background-color: rgba(0, 0, 0, 0.03);
    }
    
    /* Tooltip styles */
    .tooltip-inner {
        max-width: 300px;
        padding: 8px 12px;
        background-color: #343a40;
        font-size: 0.875rem;
        text-align: left;
    }
    
    .bs-tooltip-auto[x-placement^=left] .arrow::before, 
    .bs-tooltip-left .arrow::before {
        border-left-color: #343a40;
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

    // Process the show_all parameter if it's set via AJAX
    $showAll = isset($_GET['show_all']) ? (bool)$_GET['show_all'] : false;
    
    // Debug: Log the showAll flag
    error_log("showAll: " . ($showAll ? 'true' : 'false'));
    
    if ($showAll) {
        // Show all coins, but still sort by market cap (highest first) and limit to 1000 for performance
        $db = getDBConnection();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        $coinsQuery = "SELECT *, TIMESTAMPDIFF(HOUR, date_added, NOW()) as age_hours 
                      FROM coins 
                      ORDER BY market_cap DESC 
                      LIMIT 1000";
                      
        $stmt = $db->prepare($coinsQuery);
        $stmt->execute();
        $coinsResult = $stmt->get_result();
        $coinsData = $coinsResult ? $coinsResult->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        // Use getTrendingCoins() to get trending coins
        $coinsData = getTrendingCoins();
        //print_r($coinsData);
        if (empty($coinsData)) {
            error_log("No trending coins found");
            // Fallback to new coins if no trending coins
            $coinsData = getNewCryptocurrencies();
            error_log("Falling back to " . count($coinsData) . " new coins");
        } else {
            error_log("Found " . count($coinsData) . " trending coins");
        }
        
        // Sort by volume_24h DESC (highest volume first)
        usort($coinsData, function($a, $b) {
            return $b['volume_24h'] <=> $a['volume_24h'];
        });
    }
    
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
            'current_price' => (float)($liveData['price'] ?? $coin['current_price'] ?? 0),
            'price_change_24h' => (float)($liveData['change'] ?? $coin['price_change_24h'] ?? 0),
            'volume_24h' => (float)($liveData['volume'] ?? $coin['volume_24h'] ?? 0),
            'market_cap' => (float)($liveData['market_cap'] ?? $coin['market_cap'] ?? 0),
            'date_added' => $liveData['date_added'] ?? $coin['date_added'] ?? null,
            'age_hours' => (int)($coin['age_hours'] ?? 0),
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

// Remove coins with price exactly 0.00000000
$coins = array_filter($coins, function($coin) {
    return isset($coin['current_price']) && floatval($coin['current_price']) > 0;
});
?>

<style>
    /* Table styles */
    #coins-table {
        background-color: #061e36;
        color: rgb(241, 207, 10);
        border-left: 4px solid #17a2b8;
        width: 100%;
        margin: 20px 0;
    }
    #coins-table FILTER{
        background-color: #061e36;
        color: rgb(241, 207, 10);
        border-left: 4px solid #17a2b8;
    }
    #coins-table th {
        background: #343a40;
        color: white;
        padding: 12px;
        white-space: nowrap;
    }
    #coins-table td {
        background-color: #061e36;
        color: rgb(241, 207, 10);
        border-left: 4px solid #17a2b8;
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
        background-color:rgb(20, 5, 63);
        color: rgb(241, 207, 10);
    }
    
    /* Portfolio buttons */
    .sell-portfolio-btn {
        min-width: 100px;
        transition: all 0.2s;
    }
    .sell-portfolio-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mt-4">
                <i class="fas fa-coins"></i> <?php echo $showAll ? 'All Coins' : 'New Coins'; ?>
                <small class="text-muted">Live Market Data</small>
            </h1>
            
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- User Balances Display -->
            <div class="alert portfolio-alert">
                <div class="d-flex align-items-center">
                    <strong>Your Portfolio:</strong>
                    <div id="portfolio-loading" class="ms-2"></div>
                    <div id="portfolio" class="ms-2 d-flex flex-wrap gap-2">
                        <!-- Portfolio items will be loaded here via JavaScript -->
                        <span class="text-muted">Loading portfolio...</span>
                    </div>
                    <div id="total-portfolio-value" class="ms-auto fw-bold">
                        Total: $0.00
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-coins me-2"></i>
                        <?php echo isset($_GET['show_all']) ? 'All Coins' : 'High-Value Coins'; ?>
                        <span id="last-update"></span>
                        <button id="refresh-btn" class="btn btn-sm btn-light">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <div class="form-check form-switch d-inline-block" id="auto-refresh">
                            <input class="form-check-input" type="checkbox" id="auto-refresh-toggle" checked>
                            <label class="form-check-label text-white" for="auto-refresh-toggle">Auto-refresh</label>
                        </div>
                        <div class="form-check form-switch d-inline-block ms-3">
                            <input class="form-check-input" type="checkbox" id="show-all-coins-toggle" <?= $showAll ? 'checked' : '' ?>>
                            <label class="form-check-label text-white" for="show-all-coins-toggle">Show All Coins (<?= $showAll ? 'All' : 'Filtered' ?>)</label>
                        </div>
                    </h5>
                </div>
                
                <?php if (empty($coins)): ?>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            No data found.
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
                                    
                                    // Determine if coin is new based on age
                                    $isNew = $coin['age_hours'] < 24;
                                    $ageClass = $isNew ? 'new-coin' : '';
                                    
                                    // Format age display based on how old it is
                                    if ($coin['age_hours'] < 24) {
                                        $ageDisplay = $coin['age_hours'] . ' hours';
                                    } else if ($coin['age_hours'] < 48) {
                                        $ageDisplay = '1 day';
                                    } else if ($coin['age_hours'] < 720) { // 30 days
                                        $ageDisplay = floor($coin['age_hours'] / 24) . ' days';
                                    } else {
                                        $ageDisplay = floor($coin['age_hours'] / 720) . ' months';
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
                                    <td>$<?= number_format($coin['current_price'], $coin['current_price'] >= 1 ? 2 : 8) ?></td>
                                    <td class="<?= $priceChangeClass ?>">
                                        <?php if ($coin['price_change_24h'] >= 0): ?>
                                            <i class="fas fa-caret-up"></i>
                                        <?php else: ?>
                                            <i class="fas fa-caret-down"></i>
                                        <?php endif; ?>
                                        <?= number_format(abs($coin['price_change_24h']), 2) ?>%
                                    </td>
                                    <td>$<?= number_format($coin['volume_24h'] ?? 0) ?></td>
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
                                                    data-price="<?= $coin['current_price'] ?>">
                                                    <i class="fas fa-shopping-cart"></i> Buy
                                                </button>
                                                <button type="button" class="btn btn-danger sell-btn" 
                                                    data-coin-id="<?= $coin['id'] ?>" 
                                                    data-symbol="<?= $coin['symbol'] ?>" 
                                                    data-price="<?= $coin['current_price'] ?>" 
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
