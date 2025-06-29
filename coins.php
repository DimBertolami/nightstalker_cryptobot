<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

// Initialize showAll flag with default value
$showAll = false;

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
//requireAuth();

// Initialize live data array
$liveData = [
    'price' => 0,
    'volume' => 0,
    'market_cap' => 0,
    'age_hours' => 0
];

// Get live market data
try {
    $marketData = fetchFromCMC();
    if (!$marketData) {
        throw new Exception("Failed to fetch live market data");
    }
    
    // Process the show_all parameter
    $showAll = isset($_GET['show_all']) ? (bool)$_GET['show_all'] : false;
    
    // Get filter values with defaults
    $maxAge = null;
    if (isset($_GET['max_age']) && is_numeric($_GET['max_age'])) {
        $maxAge = max(1, (int)$_GET['max_age']); // Ensure at least 1 hour
    }
    
    $minMarketCap = null;
    if (isset($_GET['min_marketcap']) && is_numeric($_GET['min_marketcap'])) {
        $minMarketCap = max(0, (float)$_GET['min_marketcap']); // Ensure non-negative
    }
    
    $minVolume = null;
    if (isset($_GET['min_volume']) && is_numeric($_GET['min_volume'])) {
        $minVolume = max(0, (float)$_GET['min_volume']); // Ensure non-negative
    }
    
    // Initialize coins array
    $coins = [];
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Log filter values for debugging
    error_log("Filters - showAll: " . ($showAll ? 'true' : 'false') . 
             ", maxAge: " . ($maxAge ?? 'null') . 
             ", minMarketCap: " . ($minMarketCap ?? 'null') . 
             ", minVolume: " . ($minVolume ?? 'null'));
    
    // Always use filtered query to ensure consistent behavior
    // Use filtered query if any filter is active or showAll is true
    $query = "SELECT c.*, TIMESTAMPDIFF(HOUR, c.date_added, NOW()) as age_hours ";
    $from = "FROM coins c ";
    $where = "WHERE 1=1 ";
    $order = "";
    $limit = "";
    $params = [];
    $types = "";
    
    // Add filters
    if ($maxAge !== null) {
        $where .= "AND c.date_added >= DATE_SUB(NOW(), INTERVAL ? HOUR) ";
        $params[] = $maxAge;
        $types .= "i";
    }
    
    if ($minMarketCap !== null) {
        $where .= "AND c.market_cap >= ? ";
        $params[] = $minMarketCap;
        $types .= "d";
    }
    
    if ($minVolume !== null) {
        $where .= "AND c.volume_24h >= ? ";
        $params[] = $minVolume;
        $types .= "d";
    }
    
    if ($showAll) {
        // For show all, just order by market cap
        $order = "ORDER BY c.market_cap DESC ";
        $limit = "LIMIT 1000";
    } else {
        // For filtered view, include trending coins
        $from .= "LEFT JOIN trending_coins tc ON c.id = tc.coin_id ";
        // Only apply trending/volume condition if no specific filters are set
        if ($minMarketCap === null && $minVolume === null && $maxAge === null) {
            $where .= "AND (tc.coin_id IS NOT NULL OR c.volume_24h > 0) ";
        }
        $order = "ORDER BY c.volume_24h DESC, c.market_cap DESC ";
        $limit = "LIMIT 200";
    }
    
    // Build final query
    $query = "$query $from $where $order $limit";
    
    // Debug logging
    error_log("=== DEBUG FILTERS ===");
    error_log("Show All: " . ($showAll ? 'true' : 'false'));
    error_log("Max Age: " . ($maxAge ?? 'null'));
    error_log("Min Market Cap: " . ($minMarketCap ?? 'null'));
    error_log("Min Volume: " . ($minVolume ?? 'null'));
    error_log("SQL Query: " . $query);
    error_log("Params: " . print_r($params, true));
    error_log("Param types: " . $types);
    
    // Log the full URL with parameters
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    error_log("Current URL: " . $currentUrl);
    
    // Prepare and execute query
    $stmt = $db->prepare($query);
    
    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Failed to get result set: " . $db->error);
    }
    
    // Process results
    while ($row = $result->fetch_assoc()) {
        // Ensure all required fields are present
        $row['volume_24h'] = $row['volume_24h'] ?? 0;
        $row['price_change_percentage_24h'] = $row['price_change_percentage_24h'] ?? 0;
        $row['market_cap'] = $row['market_cap'] ?? 0;
        
        $coins[] = $row;
    }
    
    // Initialize coins array if not set
    if (!isset($coins) || !is_array($coins)) {
        $coins = [];
    }
    
    // Merge live data with database data
    $coins = array_map(function($coin) use ($marketData) {
        if (!is_array($coin)) {
            error_log("Invalid coin data found, skipping");
            $coin = [];
        }
        
        $symbol = $coin['symbol'] ?? '';
        $liveData = is_array($marketData) && isset($marketData[$symbol]) ? $marketData[$symbol] : [];
        
        // Ensure we have valid price data with proper null checks
        $currentPrice = 0.0;
        if (is_array($liveData) && isset($liveData['price']) && is_numeric($liveData['price'])) {
            $currentPrice = (float)$liveData['price'];
        } elseif (isset($coin['current_price']) && is_numeric($coin['current_price'])) {
            $currentPrice = (float)$coin['current_price'];
        }
        
        // Safely get numeric values with defaults
        $priceChange24h = 0.0;
        if (isset($liveData['change']) && is_numeric($liveData['change'])) {
            $priceChange24h = (float)$liveData['change'];
        } elseif (isset($coin['price_change_24h']) && is_numeric($coin['price_change_24h'])) {
            $priceChange24h = (float)$coin['price_change_24h'];
        }
        
        $volume24h = 0.0;
        if (is_array($liveData) && isset($liveData['volume']) && is_numeric($liveData['volume'])) {
            $volume24h = (float)$liveData['volume'];
        } elseif (isset($coin['volume_24h']) && is_numeric($coin['volume_24h'])) {
            $volume24h = (float)$coin['volume_24h'];
        }
        
        $marketCap = 0.0;
        if (isset($liveData['market_cap']) && is_numeric($liveData['market_cap'])) {
            $marketCap = (float)$liveData['market_cap'];
        } elseif (isset($coin['market_cap']) && is_numeric($coin['market_cap'])) {
            $marketCap = (float)$coin['market_cap'];
        }
        
        return [
            'id' => isset($coin['id']) ? (int)$coin['id'] : 0,
            'name' => $coin['name'] ?? 'Unknown',
            'symbol' => $symbol,
            'current_price' => $currentPrice,
            'price_change_24h' => $priceChange24h,
            'volume_24h' => $volume24h,
            'market_cap' => $marketCap,
            'date_added' => $liveData['date_added'] ?? $coin['date_added'] ?? null,
            'age_hours' => (isset($coin['age_hours']) && is_numeric($coin['age_hours'])) ? (int)$coin['age_hours'] : 0,
            'is_trending' => !empty($coin['is_trending']),
            'volume_spike' => !empty($coin['volume_spike']),
            'last_updated' => $coin['last_updated'] ?? null
        ];
    }, $coins);

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
                        <div class="d-flex align-items-center">
                            <button id="refresh-data" class="btn btn-sm btn-primary me-2">
                                <i class="fas fa-sync-alt"></i> Refresh Data
                            </button>
                            <div class="input-group input-group-sm ms-2" style="width: 200px;">
                                <span class="input-group-text">Age < 24h</span>
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0 filter-toggle" type="checkbox" id="filter-age" data-target="age">
                                </div>
                            </div>
                            <div class="input-group input-group-sm ms-2" style="width: 300px;">
                                <span class="input-group-text">Market Cap ></span>
                                <input type="number" class="form-control form-control-sm filter-input" id="filter-marketcap" placeholder="1M" data-type="marketcap" disabled>
                                <span class="input-group-text">USD</span>
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0 filter-toggle" type="checkbox" id="filter-marketcap-toggle" data-target="marketcap">
                                </div>
                            </div>
                            <div class="input-group input-group-sm ms-2" style="width: 300px;">
                                <span class="input-group-text">24h Volume ></span>
                                <input type="number" class="form-control form-control-sm filter-input" id="filter-volume" placeholder="1M" data-type="volume" disabled>
                                <span class="input-group-text">USD</span>
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0 filter-toggle" type="checkbox" id="filter-volume-toggle" data-target="volume">
                                </div>
                            </div>
                        </div>
                        <div id="last-updated" class="text-muted small"></div>
                        <div class="form-check form-switch d-inline-block">
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
                                    <td>$<?= $coin['current_price'] !== null ? number_format((float)$coin['current_price'], $coin['current_price'] >= 1 ? 2 : 8) : 'N/A' ?></td>
                                    <td class="<?= $priceChangeClass ?>">
                                        <?php if ($coin['price_change_24h'] >= 0): ?>
                                            <i class="fas fa-caret-up"></i>
                                        <?php else: ?>
                                            <i class="fas fa-caret-down"></i>
                                        <?php endif; ?>
                                        <?= $coin['price_change_24h'] !== null ? number_format(abs((float)$coin['price_change_24h']), 2) : '0.00' ?>%
                                    </td>
                                    <td>$<?= isset($coin['volume_24h']) ? number_format((float)$coin['volume_24h']) : '0' ?></td>
                                    <td>$<?= isset($coin['market_cap']) ? number_format((float)$coin['market_cap']) : '0' ?></td>
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
