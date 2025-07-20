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
$pageTitle = 'Night Stalker - built from the remains of a decommmissioned tsunami prediction warning system Artificial Intelligence, its new mission objectives to track and exploit a vulnerability discovered in all the new coins, which allows this system to predict and benefit from their price movements.';

// Add custom CSS for price history table and select2
$customCSS = '
<link href="/NS/assets/css/price-history.css" rel="stylesheet">
<link href="/NS/assets/css/crypto-widget.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
                                    <a href="/NS/link-wallet.php" class="btn btn-primary">
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





<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- safeJQuery definition -->
<script>
    function safeJQuery(callback) {
        if (window.jQuery) {
            callback(window.jQuery);
        } else {
            setTimeout(function() { safeJQuery(callback); }, 50);
        }
    }
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- CMC Utils JS -->
<script src="/NS/assets/js/cmc_utils.js"></script>

<!-- Price History Table JavaScript -->
<script src="/NS/assets/js/price-history.js"></script>

<!-- Wallet Management JavaScript -->
<script src="/NS/assets/js/wallet-management.js"></script>

<!-- Main Trading Dashboard JavaScript -->
<script src="/NS/dashboard/trading-dashboard-main.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
