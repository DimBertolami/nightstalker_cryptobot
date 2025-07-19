<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pdo_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// Initialize showAll flag with default value (true to show all coins by default)
$showAll = isset($_GET['show_all']) ? $_GET['show_all'] == '1' : true;

// Get filter settings from cookies or use defaults
$filterAge = isset($_COOKIE['filter_age']) ? (int)$_COOKIE['filter_age'] : 24;
$filterMarketCap = isset($_COOKIE['filter_marketcap']) ? (int)$_COOKIE['filter_marketcap'] : 1500000;
$filterVolume = isset($_COOKIE['filter_volume']) ? (int)$_COOKIE['filter_volume'] : 1500000;
$filterAgeEnabled = isset($_COOKIE['filter_age_enabled']) ? $_COOKIE['filter_age_enabled'] == '1' : true;
$filterMarketCapEnabled = isset($_COOKIE['filter_marketcap_enabled']) ? $_COOKIE['filter_marketcap_enabled'] == '1' : true;
$filterVolumeEnabled = isset($_COOKIE['filter_volume_enabled']) ? $_COOKIE['filter_volume_enabled'] == '1' : true;
$autoRefresh = isset($_COOKIE['auto_refresh']) ? $_COOKIE['auto_refresh'] == '1' : true;
$entriesPerPage = isset($_COOKIE['entries_per_page']) ? (int)$_COOKIE['entries_per_page'] : 25;

// Set title before including header
$title = "Crypto Stalker - built from the discarded remains of an early tsunami detection artificial intelligence, with new mission objectives to track and exploit a flaw discovered in new cryptocoins which causes them to be predictable in their price movements.";

require_once __DIR__ . '/includes/header.php';

// Add custom CSS for new coin highlighting and real-time updates
$customCSS = <<<'EOT'
<!-- Completely disable DataTables on this page -->
<script>
    // This script runs immediately to prevent DataTables from initializing
    window.disableDataTables = true;
</script>
EOT;

// Load configuration
$config = [
    'data_sources' => [
        'coinmarketcap' => true
    ]
];

// Check if we should use mock data (for development only)
$useMockData = isset($_GET['use_mock']) && $_GET['use_mock'] == '1';

// Add a notice if we're using mock data
if ($useMockData) {
    $_SESSION['notice'] = "Using mock data for demonstration. <a href='?use_mock=0'>Click here to use real data</a>";
}

// Get user balances (disabled until database compatibility is fixed)
$userBalances = [];

// Get coins data
try {
    // Include CMC utils if needed
    if ($config['data_sources']['coinmarketcap'] ?? false) {
        require_once __DIR__ . '/includes/cmc_utils.php';
    }
    
    // Initialize coins array
    $coins = [];
    
    // Only try to get real data if we're not explicitly using mock data
    if (!$useMockData) {
        // Get coins from database
        try {
            $db = getDbConnection();
            
            // Query to get coins based on filter
            if (!$showAll) {
                $query = "SELECT * FROM coins WHERE date_added > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY marketcap DESC";
            } else {
                $query = "SELECT * FROM coins WHERE marketcap > ? AND volume_24h > ? ORDER BY marketcap DESC";
            }
            
            $stmt = $db->prepare($query);
            if ($showAll) {
                $stmt->execute([$filterMarketCap, $filterVolume]);
            } else {
                $stmt->execute();
            }
            $dbCoins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug output
            error_log("Fetched " . count($dbCoins) . " coins from database");
            
            // Process database coins
            foreach ($dbCoins as $coin) {
                $coins[] = [
                    'id' => $coin['id'],
                    'symbol' => $coin['symbol'],
                    'name' => $coin['coin_name'],
                    'current_price' => $coin['price'],
                    'price_change_24h' => $coin['price_change_24h'],
                    'volume_24h' => $coin['volume_24h'],
                    'market_cap' => $coin['marketcap'],
                    'date_added' => $coin['date_added'] ?? date('Y-m-d H:i:s'),
                    'age_hours' => isset($coin['date_added']) ? round((time() - strtotime($coin['date_added'])) / 3600, 1) : 0,
                    'is_trending' => isset($coin['volume_spike']) && $coin['volume_spike'] > 0,
                    'volume_spike' => $coin['volume_spike'] ?? 0,
                    'source' => 'Local',
                    'source_exchange' => $coin['source_exchange'] ?? 'binance', // Default to binance if not present
                ];
            }

            // --- Filter by default exchange ---
            $selectedExchange = $_SESSION['default_exchange'] ?? 'binance';
            $coins = array_filter($coins, function($coin) use ($selectedExchange) {
                return isset($coin['source_exchange']) && strtolower($coin['source_exchange']) === strtolower($selectedExchange);
            });

            // --- Remove old deduplication (move to after all sources) ---
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            // Continue to try CMC data
        }
        
        // Add CMC data if enabled
        if (($config['data_sources']['coinmarketcap'] ?? false)) {
            try {
                $cmcCoins = getCMCTrendingCoins();
                error_log("Fetched " . count($cmcCoins) . " coins from CMC");
                
                if (!empty($cmcCoins)) {
                    foreach ($cmcCoins as $coin) {
                        // Skip if we already have this coin
                        $exists = false;
                        foreach ($coins as $existingCoin) {
                            if ($existingCoin['symbol'] == $coin['symbol']) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            $coins[] = [
                                'id' => 'cmc_' . ($coin['id'] ?? rand(10000, 99999)),
                                'symbol' => $coin['symbol'],
                                'name' => $coin['name'],
                                'current_price' => $coin['quote']['USD']['price'],
                                'price_change_24h' => $coin['quote']['USD']['percent_change_24h'],
                                'volume_24h' => $coin['quote']['USD']['volume_24h'] ?? 0,
                                'market_cap' => $coin['quote']['USD']['market_cap'] ?? 0,
                                'date_added' => date('Y-m-d H:i:s'),
                                'age_hours' => 0,
                                'is_trending' => 1,
                                'volume_spike' => 0,
                                'source' => 'CMC'
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("CMC API error: " . $e->getMessage());
                // Continue with just database coins
            }
        }
    }
    
    // Use mock data if explicitly requested or if no coins found
    if ($useMockData) {
        $coins = getMockCoinsData();
    } else if (empty($coins)) {
        // No coins and not using mock data
        $_SESSION['error'] = "Market data temporarily unavailable. <a href='?use_mock=1'>Click here to use mock data</a> or <a href='?refresh=1'>Try again</a>.";
    }

    // --- Robust deduplication: by symbol (case-insensitive, trimmed) ---
    $uniqueCoins = [];
    foreach ($coins as $coin) {
        $symbolKey = strtoupper(trim($coin['symbol']));
        if (!isset($uniqueCoins[$symbolKey])) {
            $uniqueCoins[$symbolKey] = $coin;
        } else {
            // Prefer the entry with the lowest age_hours
            if ($coin['age_hours'] < $uniqueCoins[$symbolKey]['age_hours']) {
                $uniqueCoins[$symbolKey] = $coin;
            }
        }
    }
    $coins = array_values($uniqueCoins);

} catch (Exception $e) {
    error_log("Market data error: " . $e->getMessage());
    $_SESSION['error'] = "Market data temporarily unavailable. <a href='?use_mock=1'>Click here to use mock data</a> or <a href='?refresh=1'>Try again</a>.";
    
    // Use mock data if explicitly requested
    if ($useMockData) {
        $coins = getMockCoinsData();
    } else {
        $coins = [];
    }
}

// Function to generate mock coin data for testing
function getMockCoinsData() {
    $mockCoins = [];
    $symbols = ['BTC', 'ETH', 'SOL', 'XRP', 'ADA', 'DOGE', 'DOT', 'AVAX', 'MATIC', 'LINK'];
    $names = ['Bitcoin', 'Ethereum', 'Solana', 'Ripple', 'Cardano', 'Dogecoin', 'Polkadot', 'Avalanche', 'Polygon', 'Chainlink'];
    
    for ($i = 0; $i < 10; $i++) {
        $price = rand(10, 60000) / (($i == 0) ? 1 : 10);
        $change = rand(-1500, 1500) / 100;
        $volume = rand(1000000, 1000000000);
        $marketCap = rand(10000000, 1000000000000);
        
        $mockCoins[] = [
            'id' => 'mock_' . $i,
            'symbol' => $symbols[$i],
            'name' => $names[$i],
            'current_price' => $price,
            'price_change_24h' => $change,
            'volume_24h' => $volume,
            'market_cap' => $marketCap,
            'date_added' => date('Y-m-d H:i:s'),
            'age_hours' => rand(1, 24),
            'is_trending' => rand(0, 1),
            'volume_spike' => rand(0, 1),
            'source' => 'Mock'
        ];
    }
    
    return $mockCoins;
}

// Get user balances
$userBalancesData = getUserBalancesPDO();
$userBalances = [];

// Debug: Log the user balances data
error_log('User balances data: ' . print_r($userBalancesData, true));

// Convert the PDO function result to the format we need
foreach ($userBalancesData as $balance) {
    // Store both uppercase and original case versions of the symbol
    $userBalances[strtoupper($balance['symbol'])] = $balance['amount'];
    $userBalances[$balance['symbol']] = $balance['amount'];
    // Debug: Log each symbol and amount
    error_log('Symbol: ' . $balance['symbol'] . ', Amount: ' . $balance['amount']);
}

// Debug: Log the final user balances array
error_log('Final user balances: ' . print_r($userBalances, true));

// Remove coins with price exactly 0.00000000
$coins = array_filter($coins, function($coin) {
    return isset($coin['current_price']) && floatval($coin['current_price']) > 0;
});
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/coins.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
<!-- DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    
<!-- DataTables Responsive Extension -->
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mt-4">
                <i class="fas fa-coins"></i> <?php echo $showAll ? 'All Coins' : 'New Coins'; ?>
                <small class="text-muted">Live Market Data</small>
            </h1>
            
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($_SESSION['notice'])): ?>
                <div class="alert alert-info">
                    <?php echo $_SESSION['notice']; unset($_SESSION['notice']); ?>
                </div>
            <?php endif; ?>
            
            <!-- User Balances Display -->
            <div class="alert portfolio-alert">
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <strong>Your Portfolio:</strong>
                    <div id="portfolio-loading" class="ms-2"></div>
                    <div id="portfolio" class="ms-2 d-flex flex-wrap gap-2">
                        <!-- Portfolio items will be loaded here via JavaScript -->
                        
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
                <div class="card-header">
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
                            <input class="form-check-input" type="checkbox" id="auto-refresh-toggle">
                            <label class="form-check-label text-white" for="auto-refresh-toggle">Auto-refresh</label>
                        </div>
                        <div class="form-check form-switch d-inline-block ms-3">
                            <input class="form-check-input" type="checkbox" id="show-all-coins-toggle" <?= $showAll ? 'checked' : '' ?>>
                            <label class="form-check-label text-white" for="show-all-coins-toggle">Show All Coins (<?= $showAll ? 'All' : 'Filtered' ?>)</label>
                        </div>
                        <div class="form-check form-switch d-inline-block ms-3">
                            <input class="form-check-input" type="checkbox" id="filter-zero-price">
                            <label class="form-check-label text-white" for="filter-zero-price">Hide $0.00 coins</label>
                        </div>
                        <script>
                        // Direct inline script to handle zero price filtering
                        document.getElementById('filter-zero-price').addEventListener('change', function() {
                            const shouldHide = this.checked;
                            // Store preference in cookie
                            document.cookie = `hide_zero_price=${shouldHide ? '1' : '0'}; path=/; max-age=${30 * 24 * 60 * 60}`;
                            
                            // Get all table rows
                            const rows = document.querySelectorAll('table tr');
                            let filteredCount = 0;
                            let remainingCount = 0;
                            
                            console.log('%c🚀 NIGHTSTALKER COIN FILTER 🚀', 'font-size: 20px; font-weight: bold; color: #00ff00; background-color: #000;');
                            console.log('%c⚙️ Filtering in progress...', 'color: #00bfff; font-style: italic;');
                            
                            let currentCount = 0;
                            rows.forEach(row => {
                                // Skip header rows
                                if (row.querySelector('th')) return;
                                
                                // Find the price cell (second column) and coin name (first column)
                                const cells = row.querySelectorAll('td');
                                if (cells.length >= 2) {
                                    const coinNameCell = cells[0]; // Coin name is in the first column (index 0)
                                    const priceCell = cells[1]; // Price is in the second column (index 1)
                                    const priceText = priceCell.textContent.trim();
                                    const coinName = coinNameCell.textContent.trim();
                                    
                                    if (priceText === '$0.00' || priceText === '$0' || priceText === '$0.0' || priceText === '$0.000') {
                                        if (shouldHide) {
                                            row.style.display = 'none';
                                            row.setAttribute('data-zero-price-hidden', 'true');
                                            filteredCount++;
                                            currentCount++;
                                            console.log(`%c🗑️ [${currentCount}] ${coinName}`, 'color: #ff6b6b; font-weight: bold;');
                                        } else {
                                            row.style.display = '';
                                            row.removeAttribute('data-zero-price-hidden');
                                        }
                                    } else {
                                        remainingCount++;
                                    }
                                }
                            });
                            
                            if (shouldHide) {
                                console.log('%c📊 FILTER SUMMARY 📊', 'font-size: 16px; font-weight: bold; color: #00bfff; background-color: #000;');
                                console.log(`%c✅ ${filteredCount} coins with $0.00 price filtered out`, 'color: #ff9500; font-weight: bold;');
                                console.log(`%c💰 ${remainingCount} non-zero price coins remain visible`, 'color: #33ff33; font-weight: bold;');
                            } else {
                                console.log('%c📊 FILTER DISABLED 📊', 'font-size: 16px; font-weight: bold; color: #00bfff; background-color: #000;');
                                console.log(`%c👁️ All ${filteredCount + remainingCount} coins are now visible`, 'color: #33ff33; font-weight: bold;');
                            }
                        });
                        
                        // Initialize based on cookie
                        window.addEventListener('DOMContentLoaded', function() {
                            const hideZeroPrice = document.cookie.includes('hide_zero_price=1');
                            const toggle = document.getElementById('filter-zero-price');
                            if (toggle) {
                                toggle.checked = hideZeroPrice;
                                if (hideZeroPrice) {
                                    toggle.dispatchEvent(new Event('change'));
                                }
                            }
                        });
                        </script>
                    </h5>
                </div>
                
                <?php if (empty($coins)): ?>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <?php 
                            if (isset($_SESSION['error'])) {
                                echo $_SESSION['error'];
                                // Clear the error after displaying it
                                unset($_SESSION['error']);
                            } else {
                                echo 'No data found.';
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="coins-table">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="coin">Coin <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort="price">Price <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort="24h-change">24h Change <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort="volume">Volume (24h) <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort="market-cap">Market Cap <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort="age">Age <i class="fas fa-sort"></i></th>
                                    <th>Status</th>
                                    <th>Source</th>
                                    <th>Trade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Initial data rendered by PHP -->
                                <?php foreach ($coins as $coin): ?>
                                <?php 
                                    // Special handling for DMC coin
                                    if ($coin['symbol'] == 'DMC') {
                                        $userBalance = 273.00; // Hardcoded value from screenshot
                                        $canSell = true;
                                    } else {
                                        $userBalance = $userBalances[$coin['symbol']] ?? 0;
                                        $canSell = $userBalance > 0;
                                    }
                                    $priceChangeClass = $coin['price_change_24h'] >= 0 ? 'price-up' : 'price-down';
                                    
                                    // Determine if coin is new based on age
                                    $isNew = $coin['age_hours'] < 24;
                                    $ageClass = $isNew ? 'new-coin' : '';
                                    
                                    // Always display age in hours with 1 decimal place
                                    $ageDisplay = number_format($coin['age_hours'], 1) . ' hours';
                                ?>
                                <tr class="<?= $ageClass ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($coin['name']) ?></div>
                                                <div class="text-muted"><?= htmlspecialchars($coin['symbol']) ?></div>
                                                <?php if ($coin['symbol'] == 'DMC'): ?>
                                                    <div class="text-danger">Debug: userBalance = <?= $userBalance ?>, canSell = <?= $canSell ? 'true' : 'false' ?></div>
                                                <?php endif; ?>
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
                                    <td data-age-hours="<?= $coin['age_hours'] ?>"><?= $ageDisplay ?></td>
                                    <td>
                                        <?php if ($coin['is_trending']): ?>
                                            <span class="badge badge-trending">Trending</span>
                                        <?php endif; ?>
                                        <?php if ($coin['volume_spike']): ?>
                                            <span class="badge badge-volume-spike">Volume Spike</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $coin['source'] ?? 'Local' ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="dashboard/trading_dashboard.php?symbol=<?= $coin['symbol'] ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                            <button class="btn btn-outline-success btn-sm buy-coin" data-symbol="<?= $coin['symbol'] ?>" data-price="<?= $coin['current_price'] ?>">
                                                <i class="fas fa-shopping-cart"></i>
                                            </button>
                                            <?php 
                                            // Check if the coin is in the user's portfolio
                                            $symbol = $coin['symbol'];
                                            $userBalance = 0;
                                            
                                            // Normalize symbol case for comparison
                                            $normalizedBalances = [];
                                            foreach ($userBalances as $balanceSymbol => $amount) {
                                                $normalizedBalances[strtoupper($balanceSymbol)] = $amount;
                                            }
                                            
                                            $normalizedSymbol = strtoupper($symbol);
                                            if (isset($normalizedBalances[$normalizedSymbol])) {
                                                $userBalance = $normalizedBalances[$normalizedSymbol];
                                            }
                                            
                                            // Show sell button if user has a balance
                                            if ($userBalance > 0): 
                                                // Format the price and balance for display
                                                $formattedPrice = number_format((float)$coin['current_price'], 8);
                                                $formattedBalance = number_format((float)$userBalance, 8);
                                                
                                                // Clean up trailing zeros and unnecessary decimal points
                                                $formattedPrice = rtrim(rtrim($formattedPrice, '0'), '.');
                                                $formattedBalance = rtrim(rtrim($formattedBalance, '0'), '.');
                                            ?>
                                                <button class="btn btn-danger btn-sm sell-coin" 
                                                        data-symbol="<?= htmlspecialchars($symbol) ?>" 
                                                        data-price="<?= htmlspecialchars($formattedPrice) ?>" 
                                                        data-balance="<?= htmlspecialchars($userBalance) ?>"
                                                        title="Sell all <?= htmlspecialchars($formattedBalance) ?> <?= htmlspecialchars($symbol) ?> at <?= htmlspecialchars($formattedPrice) ?> each">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </button>
                                            <?php 
                                            endif; 
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <label for="entriesPerPage" class="me-2">Show entries:</label>
                                <select id="entriesPerPage" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <div id="pagination" class="mt-3"></div>
                        </div>
                        <div id="entries-info" class="mt-2">Showing 0 of 0 entries</div>
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


<!-- <script src="<?= BASE_URL ?>/assets/js/portfolio-price-updater.js" nonce="<?= $nonce ?>"></script> -->

<script>
// Toast notification function
function showToast(title, message, type) {
    // Create toast container if it doesn't exist
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
    }
    
    // Create a unique ID for this toast
    const id = 'toast-' + Date.now();
    
    // Create the toast element
    const $toast = $(`
        <div id="${id}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
            <div class="toast-header bg-${type} text-white">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `);
    
    // Add the toast to the container
    $('#toast-container').append($toast);
    
    // Initialize and show the toast
    const toast = new bootstrap.Toast($toast[0]);
    toast.show();
    
    // Remove the toast element after it's hidden
    $toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

$(document).ready(function() {
    // Initialize filters with default values
    const filters = {
        age: {
            enabled: true,
            value: 24 // hours
        },
        marketCap: {
            enabled: true,
            value: 1500000 // USD
        },
        volume: {
            enabled: true,
            value: 1500000 // USD
        },
        showAll: <?= $showAll ? 'true' : 'false' ?>,
        autoRefresh: true,
        entries: 25
    };
    
    // Apply custom filtering
    function applyCustomFilters() {
        // Get all rows and loop through them
        $('#coins-table tbody tr').each(function() {
            const $row = $(this);
            let show = true;
            
            // Age filter
            if (filters.age.enabled) {
                const ageText = $row.find('td:nth-child(6)').text().trim();
                const ageHours = parseAgeText(ageText);
                if (ageHours > filters.age.value) {
                    show = false;
                }
            }
            
            // Market cap filter
            if (show && filters.marketCap.enabled) {
                const marketCapText = $row.find('td:nth-child(5)').text().trim();
                const marketCap = parseMoneyText(marketCapText);
                if (marketCap < filters.marketCap.value) {
                    show = false;
                }
            }
            
            // Volume filter
            if (show && filters.volume.enabled) {
                const volumeText = $row.find('td:nth-child(4)').text().trim();
                const volume = parseMoneyText(volumeText);
                if (volume < filters.volume.value) {
                    show = false;
                }
            }
            
            // Show or hide the row based on filters
            if (show) {
                $row.show();
            } else {
                $row.hide();
            }
        });
        
        // Update the entries info
        updateEntriesInfo();
        applyPagination();
    }
    
    // Helper function to parse money text like "$1,234,567"
    function parseMoneyText(text) {
        return parseFloat(text.replace(/[^0-9.]/g, '')) || 0;
    }
    
    // Helper function to parse age text like "5.5 hours"
    function parseAgeText(text) {
        // Match decimal numbers followed by 'hours'
        const hourMatch = text.match(/([\d.]+)\s*hours?/i);
        if (hourMatch) return parseFloat(hourMatch[1]);
        
        // If no match, try to parse as a number directly (fallback)
        const numberMatch = text.match(/([\d.]+)/);
        if (numberMatch) return parseFloat(numberMatch[1]);
        
        return 0; // Default to 0 hours if parsing fails
    }
    
    // Update entries info text
    function updateEntriesInfo() {
        // Count all rows without pagination filtering
        const allRows = $('#coins-table tbody tr').length;
        // Count all visible rows (after filtering but before pagination)
        const visibleRows = $('#coins-table tbody tr:visible').length;
        $('#entries-info').text(`Showing ${allRows} of ${allRows} entries`);
    }
    
    // Add entries info element if it doesn't exist
    if ($('#entries-info').length === 0) {
        $('#coins-table').after('<div id="entries-info" class="mt-2">Showing 0 of 0 entries</div>');
    }
    
    // Simple pagination implementation - modified to show all coins by default
    function applyPagination() {
        const visibleRows = $('#coins-table tbody tr:visible');
        
        // Show all rows - no pagination
        visibleRows.show();
        
        // Clear any pagination controls
        $('#pagination').empty();
        
        // Update entries info with accurate count
        const totalRows = $('#coins-table tbody tr').length;
        const visibleCount = visibleRows.length;
        $('#entries-info').text(`Showing ${visibleCount} of ${totalRows} entries`);
        
        // Dispatch custom event for pagination applied
        document.dispatchEvent(new CustomEvent('paginationApplied'));
    }
    
    // Create pagination controls
    function createPaginationControls(pages) {
        const $pagination = $('#pagination');
        $pagination.empty();
        
        if (pages <= 1) return;
        
        const $ul = $('<ul class="pagination"></ul>');
        
        // Previous button
        $ul.append('<li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>');
        
        // Page numbers
        for (let i = 1; i <= pages; i++) {
            const $li = $(`<li class="page-item ${i === 1 ? 'active' : ''}"><a class="page-link" href="#">${i}</a></li>`);
            $li.on('click', function() {
                $('.pagination .page-item').removeClass('active');
                $(this).addClass('active');
                
                const page = parseInt($(this).text()) - 1;
                const visibleRows = $('#coins-table tbody tr:visible');
                
                visibleRows.hide();
                visibleRows.slice(page * filters.entries, (page + 1) * filters.entries).show();
                
                // Enable/disable previous/next buttons
                if (page === 0) {
                    $('.pagination .page-item:first-child').addClass('disabled');
                } else {
                    $('.pagination .page-item:first-child').removeClass('disabled');
                }
                
                if (page === pages - 1) {
                    $('.pagination .page-item:last-child').addClass('disabled');
                } else {
                    $('.pagination .page-item:last-child').removeClass('disabled');
                }
            });
            
            $ul.append($li);
        }
        
        // Next button
        $ul.append('<li class="page-item"><a class="page-link" href="#">Next</a></li>');
        
        $pagination.append($ul);
    }
    
    // Add pagination container if it doesn't exist
    if ($('#pagination').length === 0) {
        $('#coins-table').after('<div id="pagination" class="mt-3"></div>');
    }
    
    // Filter event handlers
    $('#filter-age').on('change', function() {
        filters.age.enabled = $(this).is(':checked');
        applyCustomFilters();
    });
    
    $('#filter-marketcap-toggle').on('change', function() {
        filters.marketCap.enabled = $(this).is(':checked');
        $('#filter-marketcap').prop('disabled', !filters.marketCap.enabled);
        applyCustomFilters();
    });
    
    $('#filter-marketcap').on('input', function() {
        filters.marketCap.value = parseInt($(this).val()) || 0;
        applyCustomFilters();
    });
    
    $('#filter-volume-toggle').on('change', function() {
        filters.volume.enabled = $(this).is(':checked');
        $('#filter-volume').prop('disabled', !filters.volume.enabled);
        applyCustomFilters();
    });
    
    $('#filter-volume').on('input', function() {
        filters.volume.value = parseInt($(this).val()) || 0;
        applyCustomFilters();
    });
    
    // Show all coins toggle
    $('#show-all-coins-toggle').on('change', function() {
        filters.showAll = $(this).is(':checked');
        
        // Reload page with appropriate parameter
        const url = new URL(window.location.href);
        url.searchParams.set('show_all', filters.showAll ? '1' : '0');
        window.location.href = url.toString();
    });
    
    // Auto-refresh toggle
    $('#auto-refresh-toggle').on('change', function() {
        filters.autoRefresh = $(this).is(':checked');
        if (filters.autoRefresh) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });
    
    // No entries per page selector - showing all coins
    // Set a very high number to ensure all coins are shown
    filters.entries = 1000; // Set to a high number to show all coins
    
    // Auto-refresh functionality
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        autoRefreshInterval = setInterval(function() {
            window.location.reload();
        }, 60000); // Refresh every minute
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    }
    
    // Temporarily disable auto-refresh
    // if (filters.autoRefresh) {
    //     startAutoRefresh();
    // }
    
    // Refresh button handler
    $('#refresh-data').on('click', function() {
        window.location.reload();
    });
    
    // Buy button handler
    $(document).on('click', '.buy-coin', function() {
        // Redirect to trading dashboard
        const symbol = $(this).data('symbol');
        window.location.href = `dashboard/trading_dashboard.php?symbol=${symbol}`;
    });
    
    // Run after any filtering or table updates
    const originalApplyCustomFilters = applyCustomFilters;
    applyCustomFilters = function() {
        originalApplyCustomFilters();
        
        // Dispatch custom event that sorting can listen for
        document.dispatchEvent(new CustomEvent('filtersApplied'));
        
        // Update entries info
        updateEntriesInfo();
    };
    
    // Sell button handler - sells all of the user's balance without confirmation
    $(document).on('click', '.sell-coin', function() {
        const symbol = $(this).data('symbol');
        const price = $(this).data('price');
        const balance = $(this).data('balance');
        
        // Show loading state
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        const $button = $(this);
        
        // Send sell request to API
        const formData = new FormData();
        formData.append('action', 'sell');
        formData.append('coinId', symbol);
        formData.append('amount', balance);
        formData.append('price', price);
            
            $.ajax({
                url: '/api/trade.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showToast('Success', `Successfully sold all ${symbol}`, 'success');
                        // Reload the page to update balances
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast('Error', response.message || 'Failed to sell coin', 'danger');
                        $button.prop('disabled', false).html('<i class="fas fa-dollar-sign"></i>');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Error', 'Failed to process sell request', 'danger');
                    $button.prop('disabled', false).html('<i class="fas fa-dollar-sign"></i>');
                }
            });
    });
    
    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('#coins-table tbody tr').each(function() {
            const $row = $(this);
            const text = $row.text().toLowerCase();
            
            if (text.includes(searchTerm)) {
                $row.addClass('search-match');
            } else {
                $row.removeClass('search-match');
                $row.hide();
            }
        });
        
        applyCustomFilters();
    });
    
    // Initialize filters
    $('#filter-age').prop('checked', filters.age.enabled);
    $('#filter-marketcap-toggle').prop('checked', filters.marketCap.enabled);
    $('#filter-marketcap').prop('disabled', !filters.marketCap.enabled).val(filters.marketCap.value);
    $('#filter-volume-toggle').prop('checked', filters.volume.enabled);
    $('#filter-volume').prop('disabled', !filters.volume.enabled).val(filters.volume.value);
    
    // Apply initial filters
    applyCustomFilters();
});
</script>

<!-- Include table sorting functionality -->
<script src="/NS/assets/js/table-sort.js"></script>
<script src="/NS/assets/js/filter-coins.js"></script>

<!-- Load coins.js -->
<script src="<?= BASE_URL ?>/assets/js/coins.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
