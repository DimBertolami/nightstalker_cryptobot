<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__.'/../includes/cmc_utils.php';

// Start session if not already started and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

// Initialize the trading logger
$logger = new TradingLogger();

// Get selected exchange from session or default to binance
if (isset($_GET['exchange'])) {
    $_SESSION['selected_exchange'] = $_GET['exchange'];
}
$selectedExchange = $_SESSION['selected_exchange'] ?? 'binance';

// Include the header template
$pageTitle = 'Trading Dashboard';

// Add custom CSS for price history table and select2
$customCSS = '
<link href="/NS/assets/css/price-history.css" rel="stylesheet">
<link href="/NS/assets/css/crypto-widget.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
';

// Add custom JS for select2 and load jQuery first
$customJS = '
<!-- Load jQuery first to prevent "$ is not defined" errors -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="' . BASE_URL . '/assets/js/cmc_utils.js"></script>
';

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Tutorial Modal -->
    <div class="modal fade" id="tutorialModal" tabindex="-1" aria-labelledby="tutorialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tutorialModalLabel">Trading Dashboard Tutorial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Tutorial Carousel -->
                    <div id="tutorialCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                            <button type="button" data-bs-target="#tutorialCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
                        </div>
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="/NS/assets/images/tutorial/wallet-connect.png" class="d-block w-100" alt="Connect your wallet">
                                    </div>
                                    <div class="col-md-6">
                                        <h3>Step 1: Connect Your Wallet</h3>
                                        <p>Start by connecting your cryptocurrency wallet to access your funds and enable trading.</p>
                                        <ul>
                                            <li>Click the "Connect Wallet" button in the top right</li>
                                            <li>Select your wallet provider (Phantom, MetaMask, etc.)</li>
                                            <li>Approve the connection request in your wallet</li>
                                        </ul>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> Your wallet credentials are never stored on our servers.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="/NS/assets/images/tutorial/portfolio.png" class="d-block w-100" alt="View your portfolio">
                                    </div>
                                    <div class="col-md-6">
                                        <h3>Step 2: View Your Portfolio</h3>
                                        <p>Once connected, you can view your current portfolio holdings and performance.</p>
                                        <ul>
                                            <li>See your balance for each cryptocurrency</li>
                                            <li>Monitor price changes over different time periods</li>
                                            <li>Track your overall portfolio value</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="/NS/assets/images/tutorial/trading.png" class="d-block w-100" alt="Trading interface">
                                    </div>
                                    <div class="col-md-6">
                                        <h3>Step 3: Start Trading</h3>
                                        <p>Use our intuitive trading interface to buy and sell cryptocurrencies.</p>
                                        <ul>
                                            <li>Select a trading pair from the dropdown</li>
                                            <li>Enter the amount you want to buy or sell</li>
                                            <li>Review the transaction details</li>
                                            <li>Confirm the trade</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="/NS/assets/images/tutorial/safety.png" class="d-block w-100" alt="Safety features">
                                    </div>
                                    <div class="col-md-6">
                                        <h3>Step 4: Trading Safely</h3>
                                        <p>We've implemented several safety features to protect you while trading.</p>
                                        <ul>
                                            <li>Confirmation dialogs for all transactions</li>
                                            <li>Warning for potentially risky trades</li>
                                            <li>Transaction limits for new users</li>
                                            <li>Real-time price monitoring</li>
                                        </ul>
                                        <div class="alert alert-warning">
                                            <i class="bi bi-shield-exclamation"></i> Always double-check transaction details before confirming.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#tutorialCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#tutorialCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="showTutorialAgain">Show on next login</button>
                    <button type="button" class="btn btn-secondary me-auto" id="create-tutorial-images">Create Tutorial Images</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Tutorial Images Script -->
    <script>
        // Function to create tutorial images directory and placeholder images
        function createTutorialImages() {
            // First try to create the directory
            $.ajax({
                url: '/NS/api/system/create-tutorial-directory.php',
                type: 'GET',
                dataType: 'json',
                success: function(dirResponse) {
                    console.log('Directory creation response:', dirResponse);
                    
                    if (dirResponse.success) {
                        // Now try to create the images
                        $.ajax({
                            url: '/NS/api/system/create-tutorial-images.php',
                            type: 'GET',
                            dataType: 'json',
                            success: function(response) {
                                console.log('Image creation response:', response);
                                
                                if (response.success) {
                                    console.log('Tutorial images created successfully');
                                    // Force reload images with cache busting
                                    $('.carousel-item img').each(function() {
                                        const src = $(this).attr('src');
                                        $(this).attr('src', src + '?v=' + new Date().getTime());
                                    });
                                    alert('Tutorial images created successfully!');
                                } else {
                                    console.error('Failed to create tutorial images:', response.message);
                                    alert('Failed to create tutorial images: ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error creating tutorial images:', error);
                                alert('Error creating tutorial images: ' + error);
                            }
                        });
                    } else {
                        console.error('Failed to create tutorial directory:', dirResponse.message);
                        alert('Failed to create tutorial directory: ' + dirResponse.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error creating tutorial directory:', error);
                    alert('Error creating tutorial directory: ' + error);
                }
            });
        }

        // Check if tutorial images exist, create them if not
        $(document).ready(function() {
            // Add click handler for the button
            $(document).on('click', '#create-tutorial-images', function() {
                createTutorialImages();
            });
            
            // Check if first tutorial image exists
            $.get('/NS/assets/images/tutorial/wallet-connect.png')
                .fail(function() {
                    console.log('Tutorial images not found, creating them...');
                    createTutorialImages();
                });
        });
    </script>

    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5">Night Stalker Trading</h1>
                    <p class="lead">Real-time trading dashboard</p>
                </div>
                <div>
                    <button id="show-tutorial" class="btn btn-outline-primary">
                        <i class="bi bi-question-circle me-2"></i>Tutorial
                    </button>
                </div>
            </div>
            <hr>
        </div>
    </div>
    
    <!-- Price History Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Portfolio Price History</h5>
                    <div>
                        <button id="refresh-price-history" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="loading-price-history" class="loading">
                        <div class="spinner-border text-primary loading-spinner" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading price history...</p>
                    </div>
                    <div class="table-responsive">
                        <table id="price-history-table" class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Coin</th>
                                    <th class="text-end">Current Price</th>
                                    <th>1h</th>
                                    <th>4h</th>
                                    <th>12h</th>
                                    <th>24h</th>
                                    <th>7d</th>
                                    <th>Price Trend</th>
                                </tr>
                            </thead>
                            <tbody id="price-history-tbody">
                                <!-- Price history data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Balances -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Wallet Balances</h5>
                    <div>
                        <select id="exchange-select" class="form-select form-select-sm" style="width: 150px; display: inline-block;">
                            <option value="binance" <?= $selectedExchange === 'binance' ? 'selected' : '' ?>>Binance</option>
                            <option value="bitvavo" <?= $selectedExchange === 'bitvavo' ? 'selected' : '' ?>>Bitvavo</option>
                            <option value="kraken" <?= $selectedExchange === 'kraken' ? 'selected' : '' ?>>Kraken</option>
                        </select>
                        <button id="refresh-balances" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Wallet Connection Status -->
                    <div id="wallet-connection-status" class="p-3">
                        <?php
                        // Check if wallet is connected
                        $walletConnected = isset($_SESSION['connected_wallets']) && !empty($_SESSION['connected_wallets']);
                        $statusClass = $walletConnected ? 'wallet-status-connected' : 'wallet-status-disconnected';
                        $statusIcon = $walletConnected ? 'bi-wallet2' : 'bi-wallet';
                        ?>
                        <div class="wallet-status <?= $statusClass ?>">
                            <div class="wallet-status-icon">
                                <i class="bi <?= $statusIcon ?>"></i>
                            </div>
                            <div class="wallet-status-text">
                                <?php if ($walletConnected): ?>
                                    <strong>Wallet Connected</strong>
                                    <div class="small text-muted">
                                        <?php 
                                        $walletCount = count($_SESSION['connected_wallets']);
                                        echo $walletCount > 1 
                                            ? "$walletCount wallets connected" 
                                            : "1 wallet connected";
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <strong>No Wallet Connected</strong>
                                    <div class="small text-muted">Connect a wallet to view your balances</div>
                                <?php endif; ?>
                            </div>
                            <div class="wallet-status-actions">
                                <?php if ($walletConnected): ?>
                                    <button class="btn btn-sm btn-outline-secondary manage-wallets-btn" data-bs-toggle="modal" data-bs-target="#manageWalletsModal">
                                        <i class="bi bi-gear"></i> Manage
                                    </button>
                                <?php else: ?>
                                    <a href="/NS/link-wallet.php" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Connect Wallet
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="loading-balances" class="loading">
                        <div class="spinner-border text-primary loading-spinner" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading balances...</p>
                    </div>
                    <div id="balances-container" class="list-group list-group-flush">
                        <!-- Balances will be loaded here -->
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- Order Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="orderTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="market-tab" data-bs-toggle="tab" 
                                data-bs-target="#market-order" type="button" role="tab">Market Order</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="limit-tab" data-bs-toggle="tab" 
                                data-bs-target="#limit-order" type="button" role="tab">Limit Order</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stop-tab" data-bs-toggle="tab" 
                                data-bs-target="#stop-order" type="button" role="tab">Stop Order</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="orderTabsContent">
                        <!-- Market Order -->
                        <div class="tab-pane fade show active" id="market-order" role="tabpanel">
                            <form id="market-order-form" class="order-form">
                                <input type="hidden" name="type" value="market">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Trading Pair</label>
                                        <select name="symbol" class="form-select select2" required>
                                            <option value="BTC/USDT">BTC/USDT</option>
                                            <option value="ETH/USDT">ETH/USDT</option>
                                            <option value="BNB/USDT">BNB/USDT</option>
                                            <option value="SOL/USDT">SOL/USDT</option>
                                            <option value="XRP/USDT">XRP/USDT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Side</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="side" id="market-buy" value="buy" checked>
                                            <label class="btn btn-outline-success" for="market-buy">Buy</label>
                                            
                                            <input type="radio" class="btn-check" name="side" id="market-sell" value="sell">
                                            <label class="btn btn-outline-danger" for="market-sell">Sell</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="market-amount" class="form-label">Amount</label>
                                    <div class="input-group">
                                        <input type="number" step="0.00000001" class="form-control" id="market-amount" name="amount" required>
                                        <span class="input-group-text">BTC</span>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Place Market Order</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Limit Order -->
                        <div class="tab-pane fade" id="limit-order" role="tabpanel">
                            <form id="limit-order-form" class="order-form">
                                <input type="hidden" name="type" value="limit">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Trading Pair</label>
                                        <select name="symbol" class="form-select select2" required>
                                            <option value="BTC/USDT">BTC/USDT</option>
                                            <option value="ETH/USDT">ETH/USDT</option>
                                            <option value="BNB/USDT">BNB/USDT</option>
                                            <option value="SOL/USDT">SOL/USDT</option>
                                            <option value="XRP/USDT">XRP/USDT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Side</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="side" id="limit-buy" value="buy" checked>
                                            <label class="btn btn-outline-success" for="limit-buy">Buy</label>
                                            
                                            <input type="radio" class="btn-check" name="side" id="limit-sell" value="sell">
                                            <label class="btn btn-outline-danger" for="limit-sell">Sell</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="limit-price" class="form-label">Price</label>
                                    <div class="input-group">
                                        <input type="number" step="0.00000001" class="form-control" id="limit-price" name="price" required>
                                        <span class="input-group-text">USDT</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="limit-amount" class="form-label">Amount</label>
                                    <div class="input-group">
                                        <input type="number" step="0.00000001" class="form-control" id="limit-amount" name="amount" required>
                                        <span class="input-group-text">BTC</span>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Place Limit Order</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Stop Order -->
                        <div class="tab-pane fade" id="stop-order" role="tabpanel">
                            <form id="stop-order-form" class="order-form">
                                <input type="hidden" name="type" value="stop_loss">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Trading Pair</label>
                                        <select name="symbol" class="form-select select2" required>
                                            <option value="BTC/USDT">BTC/USDT</option>
                                            <option value="ETH/USDT">ETH/USDT</option>
                                            <option value="BNB/USDT">BNB/USDT</option>
                                            <option value="SOL/USDT">SOL/USDT</option>
                                            <option value="XRP/USDT">XRP/USDT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Side</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="side" id="stop-buy" value="buy">
                                            <label class="btn btn-outline-success" for="stop-buy">Buy Stop</label>
                                            
                                            <input type="radio" class="btn-check" name="side" id="stop-sell" value="sell" checked>
                                            <label class="btn btn-outline-danger" for="stop-sell">Sell Stop</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="stop-price" class="form-label">Stop Price</label>
                                    <div class="input-group">
                                        <input type="number" step="0.00000001" class="form-control" id="stop-price" name="stopPrice" required>
                                        <span class="input-group-text">USDT</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="stop-amount" class="form-label">Amount</label>
                                    <div class="input-group">
                                        <input type="number" step="0.00000001" class="form-control" id="stop-amount" name="amount" required>
                                        <span class="input-group-text">BTC</span>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Place Stop Order</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Open Orders -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Open Orders</h5>
                    <button id="refresh-orders" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="loading-orders" class="loading">
                        <div class="spinner-border text-primary loading-spinner" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading orders...</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Symbol</th>
                                    <th>Type</th>
                                    <th>Side</th>
                                    <th>Price</th>
                                    <th>Amount</th>
                                    <th>Filled</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="orders-container">
                                <!-- Orders will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="toast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Manage Wallets Modal -->
<div class="modal fade" id="manageWalletsModal" tabindex="-1" aria-labelledby="manageWalletsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageWalletsModalLabel">Manage Wallets</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h6>Connected Wallets</h6>
                    <div id="connected-wallets-list">
                        <?php if (isset($_SESSION['connected_wallets']) && !empty($_SESSION['connected_wallets'])): ?>
                            <?php foreach ($_SESSION['connected_wallets'] as $wallet): ?>
                                <div class="card mb-2 wallet-card">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($wallet['type'] ?? 'Wallet') ?></strong>
                                                <div class="small wallet-address"><?= htmlspecialchars($wallet['address'] ?? '') ?></div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger disconnect-wallet-btn" 
                                                    data-wallet-id="<?= htmlspecialchars($wallet['id'] ?? '') ?>"
                                                    data-wallet-address="<?= htmlspecialchars($wallet['address'] ?? '') ?>">
                                                <i class="bi bi-x-circle"></i> Disconnect
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> No wallets connected
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="/NS/link-wallet.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i> Connect New Wallet
                    </a>
                    <a href="/NS/settings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-2"></i> Advanced Wallet Settings
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Wallet Disconnection Confirmation Modal -->
<div class="modal fade" id="disconnectWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i> Disconnect Wallet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <p><strong>Warning:</strong> You are about to disconnect your wallet from Night Stalker.</p>
                    <p>This action will:</p>
                    <ul>
                        <li>Remove the wallet connection from your current session</li>
                        <li>Require you to reconnect your wallet to view balances or make trades</li>
                    </ul>
                    <p>Your funds will remain safe in your wallet, but you won't be able to trade with them until you reconnect.</p>
                </div>
                
                <p>Are you sure you want to disconnect this wallet?</p>
                
                <form id="disconnect-wallet-form">
                    <input type="hidden" id="disconnect-wallet-id" name="wallet_id" value="">
                    <input type="hidden" id="disconnect-wallet-address" name="wallet_address" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-disconnect-wallet">
                    <i class="bi bi-x-circle me-2"></i> Disconnect Wallet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script>
    // Check if jQuery is loaded, if not, load it dynamically
    if (typeof jQuery === 'undefined') {
        const jqueryScript = document.createElement('script');
        jqueryScript.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
        jqueryScript.async = true;
        document.head.appendChild(jqueryScript);
    }
    
    // Define a safe $ function that will work even if jQuery isn't loaded yet
    function safeJQuery(callback) {
        if (window.jQuery) {
            callback(window.jQuery);
        } else {
            setTimeout(function() { safeJQuery(callback); }, 50);
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 on all select2 elements
    function initializeSelect2() {
        try {
            // Check if jQuery is loaded
            if (typeof $ === 'undefined' || typeof $.fn === 'undefined') {
                console.error('jQuery not loaded, cannot initialize Select2');
                return;
            }
            
            // Check if Select2 is loaded
            if (typeof $.fn.select2 === 'function') {
                // Safely initialize Select2
                $('.select2').each(function() {
                    try {
                        // Check if this element already has Select2 initialized
                        if (!$(this).data('select2')) {
                            $(this).select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $('body')
                            });
                        }
                    } catch (elementError) {
                        console.warn('Error initializing Select2 on element:', $(this).attr('id') || 'unknown', elementError);
                    }
                });
                console.log('Select2 initialized successfully');
            } else {
                console.warn('Select2 library not loaded properly. Attempting to load dynamically...');
                
                // Try to load Select2 dynamically if not available
                const select2Css = document.createElement('link');
                select2Css.rel = 'stylesheet';
                select2Css.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
                document.head.appendChild(select2Css);
                
                const select2ThemeCss = document.createElement('link');
                select2ThemeCss.rel = 'stylesheet';
                select2ThemeCss.href = 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css';
                document.head.appendChild(select2ThemeCss);
                
                const select2Script = document.createElement('script');
                select2Script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
                select2Script.onload = function() {
                    console.log('Select2 loaded dynamically, initializing...');
                    setTimeout(initializeSelect2, 500); // Try again after a delay
                };
                document.body.appendChild(select2Script);
            }
        } catch (e) {
            console.error('Error initializing Select2:', e);
        }
    }
    
    // Initialize on page load with a slight delay to ensure DOM is ready
    setTimeout(initializeSelect2, 300);
    
    // Re-initialize Select2 after tab changes
    $('.nav-tabs a').on('shown.bs.tab', function() {
        try {
            // First destroy any existing instances to prevent duplicates
            $('.select2').each(function() {
                try {
                    if ($(this).data('select2')) {
                        $(this).select2('destroy');
                    }
                } catch (e) {
                    // Ignore errors during destroy
                }
            });
            
            // Then re-initialize
            initializeSelect2();
        } catch (e) {
            console.error('Error re-initializing Select2 after tab change:', e);
        }
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize toast
    var toastEl = document.getElementById('toast');
    var toast = new bootstrap.Toast(toastEl, { 
        autohide: true, 
        delay: 5000 
    });
    
    // Handle Select2 in modals
    $(document).on('select2:open', () => {
        document.querySelector('.select2-search__field').focus();
    });
    
    // Show toast message
    function showToast(message, type = 'success') {
        var toast = bootstrap.Toast.getOrCreateInstance(toastEl);
        var $toast = $(toastEl);
        $toast.removeClass('bg-success bg-danger bg-warning bg-info');
        $toast.addClass('bg-' + type);
        $toast.find('.toast-body').text(message);
        toast.show();
    }

    // Global variables for request handling
    let balanceRetryCount = 0;
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 3000; // 3 seconds
    let balanceTimeout = null;
    let ordersTimeout = null;
    let isPageVisible = true;

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        isPageVisible = !document.hidden;
        if (isPageVisible) {
            // Page became visible, refresh data
            loadBalances();
            loadOpenOrders();
        } else {
            // Page is hidden, clear timeouts
            clearTimeout(balanceTimeout);
            clearTimeout(ordersTimeout);
        }
    });

    // Load wallet balances with retry logic
    function loadBalances() {
        if (!isPageVisible) return;
        
        var exchange = $('#exchange-select').val();
        $('#loading-balances').show();
        
        // Clear any existing timeouts
        clearTimeout(balanceTimeout);
        
        $.ajax({
            url: '/NS/api/trading/balance.php',
            method: 'GET',
            data: { exchange: exchange },
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            success: function(response) {
                balanceRetryCount = 0; // Reset retry counter on success
                
                if (response.success && response.balances) {
                    var html = '';
                    var hasBalances = false;
                    
                    // Sort by balance value (highest first)
                    var sortedBalances = Object.entries(response.balances)
                        .sort((a, b) => parseFloat(b[1].total) - parseFloat(a[1].total));
                    
                    sortedBalances.forEach(([currency, balance]) => {
                        var total = parseFloat(balance.total || 0);
                        var available = parseFloat(balance.free || 0);
                        var inOrders = parseFloat(balance.used || 0);
                        
                        // Only show currencies with non-zero balance
                        if (total > 0) {
                            hasBalances = true;
                            var percentage = total > 0 ? (inOrders / total * 100).toFixed(1) : 0;
                            
                            html += `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="fw-bold">${currency}</div>
                                        <div class="text-end">
                                            <div>${total.toFixed(8)}</div>
                                            <small class="text-muted">Available: ${available.toFixed(8)}</small>
                                        </div>
                                    </div>
                                    ${inOrders > 0 ? `
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>In Orders: ${inOrders.toFixed(8)} (${percentage}%)</span>
                                        </div>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                style="width: ${percentage}%" 
                                                aria-valuenow="${percentage}" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>` : ''}
                                </div>
                            `;
                        }
                    });
                    
                    if (!hasBalances) {
                        html = '<div class="list-group-item text-center text-muted py-3">No balances found</div>';
                    }
                    
                    $('#balances-container').html(html);
                } else {
                    showToast(response.error || 'Failed to load balances', 'danger');
                }
                
                // Schedule next refresh (30 seconds)
                balanceTimeout = setTimeout(loadBalances, 30000);
            },
            error: function(xhr, status, error) {
                balanceRetryCount++;
                
                if (balanceRetryCount <= MAX_RETRIES) {
                    // Exponential backoff: 3s, 6s, 12s
                    const delay = RETRY_DELAY * Math.pow(2, balanceRetryCount - 1);
                    showToast(`Error loading balances (${balanceRetryCount}/${MAX_RETRIES}). Retrying in ${delay/1000}s...`, 'warning');
                    balanceTimeout = setTimeout(loadBalances, delay);
                } else {
                    showToast('Failed to load balances after ' + MAX_RETRIES + ' attempts. Please check your connection.', 'danger');
                    $('#loading-balances').hide();
                    // Schedule normal refresh after longer delay
                    balanceTimeout = setTimeout(loadBalances, 60000);
                }
            },
            complete: function() {
                // Don't hide if we're retrying
                if (balanceRetryCount === 0) {
                    $('#loading-balances').hide();
                }
            }
        });
    }

    // Load open orders with retry logic
    let ordersRetryCount = 0;
    
    function loadOpenOrders() {
        if (!isPageVisible) return;
        
        var exchange = $('#exchange-select').val();
        $('#loading-orders').show();
        
        // Clear any existing timeouts
        clearTimeout(ordersTimeout);
        
        // This would be replaced with an actual API call
        // For now, we'll simulate a response
        $.ajax({
            url: '/NS/api/trading/orders.php', // This endpoint needs to be implemented
            method: 'GET',
            data: { exchange: exchange },
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            success: function(response) {
                ordersRetryCount = 0; // Reset retry counter on success
                
                if (response.success && response.orders) {
                    var html = '';
                    
                    if (response.orders.length > 0) {
                        response.orders.forEach(function(order) {
                            var sideClass = order.side === 'buy' ? 'text-success' : 'text-danger';
                            var filledPercent = order.amount > 0 ? 
                                (parseFloat(order.filled) / parseFloat(order.amount) * 100).toFixed(2) : 0;
                            
                            html += `
                                <tr>
                                    <td>${new Date(order.created_at).toLocaleString()}</td>
                                    <td>${order.symbol}</td>
                                    <td><span class="badge bg-secondary">${order.type}</span></td>
                                    <td class="${sideClass}">${order.side.toUpperCase()}</td>
                                    <td>${parseFloat(order.price).toFixed(8)}</td>
                                    <td>${parseFloat(order.amount).toFixed(8)}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                style="width: ${filledPercent}%" 
                                                aria-valuenow="${filledPercent}" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                                ${filledPercent}%
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">${order.status}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger cancel-order" data-order-id="${order.id}">
                                            Cancel
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html = '<tr><td colspan="9" class="text-center text-muted py-3">No open orders</td></tr>';
                    }
                    
                    $('#orders-container').html(html);
                } else {
                    showToast(response.error || 'Failed to load orders', 'danger');
                }
                
                // Schedule next refresh (30 seconds)
                ordersTimeout = setTimeout(loadOpenOrders, 30000);
            },
            error: function(xhr, status, error) {
                ordersRetryCount++;
                
                if (ordersRetryCount <= MAX_RETRIES) {
                    // Exponential backoff: 3s, 6s, 12s
                    const delay = RETRY_DELAY * Math.pow(2, ordersRetryCount - 1);
                    showToast(`Error loading orders (${ordersRetryCount}/${MAX_RETRIES}). Retrying in ${delay/1000}s...`, 'warning');
                    ordersTimeout = setTimeout(loadOpenOrders, delay);
                } else {
                    showToast('Failed to load orders after ' + MAX_RETRIES + ' attempts. Please check your connection.', 'danger');
                    $('#loading-orders').hide();
                    // Schedule normal refresh after longer delay
                    ordersTimeout = setTimeout(loadOpenOrders, 60000);
                }
            },
            complete: function() {
                // Don't hide if we're retrying
                if (ordersRetryCount === 0) {
                    $('#loading-orders').hide();
                }
            }
        });
    }

    // Submit order form
    function submitOrderForm(form, orderType) {
        var formData = $(form).serializeArray().reduce(function(obj, item) {
            obj[item.name] = item.value;
            return obj;
        }, {});
        
        // Add exchange
        formData.exchange = $('#exchange-select').val();
        
        // Show loading state
        var $submitBtn = $(form).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Placing Order...'
        );
        
        // Submit order
        $.ajax({
            url: '/NS/api/trading/order.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(`${orderType} order placed successfully!`, 'success');
                    $(form)[0].reset();
                    loadOpenOrders();
                    loadBalances();
                } else {
                    showToast(response.error || 'Failed to place order', 'danger');
                }
            },
            error: function(xhr, status, error) {
                showToast('Error: ' + (xhr.responseJSON?.error || error), 'danger');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
        
        return false;
    }

    // Event Listeners
    $('#refresh-balances, #exchange-select').on('change click', function() {
        loadBalances();
    });
    
    $('#refresh-orders').on('click', function() {
        loadOpenOrders();
    });
    
    // Form submissions
    $('#market-order-form').on('submit', function(e) {
        e.preventDefault();
        return submitOrderForm(this, 'Market');
    });
    
    $('#limit-order-form').on('submit', function(e) {
        e.preventDefault();
        return submitOrderForm(this, 'Limit');
    });
    
    $('#stop-order-form').on('submit', function(e) {
        e.preventDefault();
        return submitOrderForm(this, 'Stop');
    });
    
    // Cancel order
    $(document).on('click', '.cancel-order', function() {
        if (!confirm('Are you sure you want to cancel this order?')) {
            return;
        }
        
        var orderId = $(this).data('order-id');
        var $btn = $(this);
        $btn.prop('disabled', true).html('Canceling...');
        
        // This would be an API call to cancel the order
        setTimeout(function() {
            showToast('Order canceled successfully', 'success');
            loadOpenOrders();
            loadBalances();
        }, 1000);
    });
    
    // Load initial data
    loadBalances();
    loadOpenOrders();
    
    // Auto-refresh every 30 seconds
    setInterval(loadBalances, 30000);
    setInterval(loadOpenOrders, 30000);

    // Integrate CMC data into trading logic
    function getCMCGainersLosers() {
        console.log('getCMCGainersLosers function called but not fully implemented');
        return {
            then: function(callback) {
                // Return empty arrays for gainers and losers
                callback({
                    gainers: [],
                    losers: []
                });
                return this;
            },
            catch: function(errorCallback) {
                return this;
            }
        };
    }
    
    getCMCGainersLosers().then(function(cmcData) {
        if (!cmcData || !cmcData.gainers) {
            console.warn('No CMC gainers data available');
            return;
        }
        
        var topGainers = cmcData.gainers.slice(0, 5); // Top 5 gainers

        topGainers.forEach(function(coin) {
            var symbol = coin.symbol;
            var change = coin.quote.USD.percent_change_24h;
            
            // Example trading rule: if 24h change > 15%
            if (change > 15) {
                // Your existing trade execution logic here
                var tradeAmount = Math.min(maxPositionSize, change/100 * capital);
                executeTrade(symbol, 'BUY', tradeAmount);
            }
        });
    }).catch(function(error) {
        console.error('Failed to get CMC data:', error);
    });
});
</script>

<!-- Price History Table JavaScript -->
<script src="/NS/assets/js/price-history.js"></script>

<!-- Wallet Management JavaScript -->
<script src="/NS/assets/js/wallet-management.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
