<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

$title = "Settings";
require_once __DIR__ . '/includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real application, you'd validate and save settings here
    $message = "Settings updated successfully";
    $alertType = "success";
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mt-4">
                <i class="fas fa-cog"></i> Settings
                <small class="text-muted">Configure Night Stalker</small>
            </h1>
        </div>
    </div>
    
    <!-- Alerts container -->
    <div id="alerts-container"></div>
    
    <form method="POST" id="settings-form">
        <div class="row mb-4">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Save All Settings
                </button>
            </div>
        </div>

    <?php if (isset($message)): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-<?php echo $alertType; ?>">
                <?php echo $message; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Trading Parameters</h3>
                </div>
                <div class="card-body">
                        <div class="mb-3">
                            <label for="minVolume" class="form-label">Minimum Volume Threshold</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="minVolume" name="minVolume" 
                                    value="<?php echo MIN_VOLUME_THRESHOLD; ?>" step="100000">
                            </div>
                            <small class="text-muted">Minimum trade volume to trigger alerts</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="maxAge" class="form-label">Maximum Coin Age (hours)</label>
                            <input type="number" class="form-control" id="maxAge" name="maxAge" 
                                value="<?php echo MAX_COIN_AGE; ?>">
                            <small class="text-muted">Only track coins younger than this age</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="checkInterval" class="form-label">Check Interval (seconds)</label>
                            <input type="number" class="form-control" id="checkInterval" name="checkInterval" 
                                value="<?php echo CHECK_INTERVAL; ?>">
                            <small class="text-muted">How often to check for new data</small>
                        </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Wallet Configuration</h3>
                </div>
                <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Supported Wallets</label>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="phantomWallet" name="wallets[phantom]" checked>
                                <label class="form-check-label" for="phantomWallet">
                                    <img src="assets/images/wallets/phantom.png" alt="Phantom" width="20" class="me-2">
                                    Phantom (Solana)
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="metamaskWallet" name="wallets[metamask]">
                                <label class="form-check-label" for="metamaskWallet">
                                    <img src="assets/images/wallets/metamask.png" alt="MetaMask" width="20" class="me-2">
                                    MetaMask (Ethereum)
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="keplrWallet" name="wallets[keplr]">
                                <label class="form-check-label" for="keplrWallet">
                                    <img src="assets/images/wallets/keplr.png" alt="Keplr" width="20" class="me-2">
                                    Keplr (Cosmos)
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="trustWallet" name="wallets[trust]">
                                <label class="form-check-label" for="trustWallet">
                                    <img src="assets/images/wallets/trust.png" alt="Trust Wallet" width="20" class="me-2">
                                    Trust Wallet (Multi-chain)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="defaultWallet" class="form-label">Default Wallet</label>
                            <select class="form-select" id="defaultWallet" name="defaultWallet">
                                <option value="phantom" selected>Phantom</option>
                                <option value="metamask">MetaMask</option>
                                <option value="keplr">Keplr</option>
                                <option value="trust">Trust Wallet</option>
                            </select>
                        </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">API Configuration</h3>
                </div>
                <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="masterFetchToggle" name="masterFetchToggle" checked>
                                <label class="form-check-label fw-bold" for="masterFetchToggle">
                                    Enable Fetching of New Coins
                                </label>
                                <small class="text-muted d-block">Master switch to turn on/off all coin fetching</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data Sources</label>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="coinGeckoSource" name="sources[coingecko]" checked>
                                <label class="form-check-label" for="coinGeckoSource">CoinGecko</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="cmcSource" name="sources[cmc]" checked>
                                <label class="form-check-label" for="cmcSource">CoinMarketCap</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="bitvavoSource" name="sources[bitvavo]">
                                <label class="form-check-label" for="bitvavoSource">Bitvavo</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="jupiterSource" name="sources[jupiter]">
                                <label class="form-check-label" for="jupiterSource">Jupiter</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="alpacaSource" name="sources[alpaca]">
                                <label class="form-check-label" for="alpacaSource">Alpaca</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="liveCoinWatchSource" name="sources[livecoinwatch]">
                                <label class="form-check-label" for="liveCoinWatchSource">LiveCoinWatch</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <input type="text" class="form-control" id="coingeckoKey" name="coingeckoKey" 
                                value="<?php echo COINGECKO_API_KEY; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="cmcKey" class="form-label">CoinMarketCap API Key</label>
                            <input type="text" class="form-control" id="cmcKey" name="cmcKey" 
                                value="<?php echo API_KEY; ?>">
                        </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Exchange Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h4 class="alert-heading">CCXT Integration</h4>
                        <p>Configure cryptocurrency exchanges for trading. API keys will be securely stored.</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Active Exchanges</label>
                        <div class="active-exchanges">
                            <?php
                            // Include exchange config
                            require_once __DIR__ . '/includes/exchange_config.php';
                            
                            // Get configured exchanges
                            $exchanges = get_exchanges();
                            $default_exchange = get_default_exchange();
                            
                            // Display configured exchanges
                            foreach ($exchanges as $exchange_id => $exchange) {
                                $checked = !empty($exchange['enabled']) ? 'checked' : '';
                                $exchange_name = $exchange['name'] ?? ucfirst($exchange_id);
                                echo <<<HTML
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="{$exchange_id}Exchange" name="exchanges[{$exchange_id}]" {$checked}>
                                    <label class="form-check-label" for="{$exchange_id}Exchange">
                                        <img src="assets/images/exchanges/{$exchange_id}.png" alt="{$exchange_name}" width="20" class="me-2" onerror="this.src='assets/images/exchanges/generic.png';">
                                        {$exchange_name}
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2 edit-exchange" data-exchange-id="{$exchange_id}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-exchange" data-exchange-id="{$exchange_id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                HTML;
                            }
                            
                            // If no exchanges are configured, show a message
                            if (empty($exchanges)) {
                                echo '<div class="alert alert-warning">No exchanges configured yet. Add one below.</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="defaultExchange" class="form-label">Default Exchange</label>
                        <select class="form-select" id="defaultExchange" name="defaultExchange">
                            <?php
                            // Display options for configured exchanges
                            foreach ($exchanges as $exchange_id => $exchange) {
                                $selected = ($exchange_id === $default_exchange) ? 'selected' : '';
                                $exchange_name = $exchange['name'] ?? ucfirst($exchange_id);
                                echo "<option value=\"{$exchange_id}\" {$selected}>{$exchange_name}</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">The default exchange will be used for trading operations</small>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addExchangeModal">
                            <i class="fas fa-plus-circle"></i> Add New Exchange
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h3 class="mb-0">Danger Zone</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Warning!</h4>
                        <p>Actions in this section can result in data loss and cannot be undone.</p>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" name="resetData" class="btn btn-outline-danger" onclick="if(confirm('Are you sure? This cannot be undone!')) document.getElementById('reset-data').submit();">
                            <i class="fas fa-trash-alt"></i> Reset All Data
                        </button>
                        <small class="text-muted d-block mt-2">This will delete all tracked coins and settings.</small>
                    </div>
                    <input type="hidden" name="reset_action" value="reset_data" form="settings-form" id="reset-data">
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h3 class="mb-0">Danger Zone</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                            <i class="fas fa-trash me-1"></i> Reset All Data
                        </button>
                        <small class="text-muted d-block mt-1">This will delete all trade history and coin data</small>
                    </div>
                    
                    <div class="mb-3">
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearCacheModal">
                            <i class="fas fa-broom me-1"></i> Clear Cache
                        </button>
                        <small class="text-muted d-block mt-1">Clear all cached API responses</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Modal -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Reset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset all data? This action cannot be undone.</p>
                <p class="text-danger"><strong>All trade history and coin data will be permanently deleted.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger">Confirm Reset</button>
            </div>
        </div>
    </div>
</div>

<!-- Clear Cache Modal -->
<div class="modal fade" id="clearCacheModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Confirm Cache Clear</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear all cached data?</p>
                <p class="text-muted">This will force fresh data to be fetched from APIs on next request.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning">Clear Cache</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Exchange Modal -->
<div class="modal fade" id="addExchangeModal" tabindex="-1" aria-labelledby="addExchangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addExchangeModalLabel">Add New Exchange</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="exchangeSelect" class="form-label">Select Exchange</label>
                    <select class="form-select" id="exchangeSelect" name="exchange_id">
                        <option value="">-- Select an Exchange --</option>
                        <optgroup label="Popular Exchanges">
                            <option value="binance">Binance</option>
                            <option value="coinbase">Coinbase</option>
                            <option value="kraken">Kraken</option>
                            <option value="kucoin">KuCoin</option>
                            <option value="bitvavo">Bitvavo</option>
                            <option value="bybit">Bybit</option>
                        </optgroup>
                        <optgroup label="DEX">
                            <option value="jupiter">Jupiter (Solana)</option>
                            <option value="uniswap">Uniswap (Ethereum)</option>
                            <option value="pancakeswap">PancakeSwap (BSC)</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="apiKey" class="form-label">API Key</label>
                    <input type="text" class="form-control" id="apiKey" name="api_key" placeholder="Enter your API key">
                </div>
                
                <div class="mb-3">
                    <label for="apiSecret" class="form-label">API Secret</label>
                    <input type="password" class="form-control" id="apiSecret" name="api_secret" placeholder="Enter your API secret">
                </div>
                
                <div class="mb-3">
                    <label for="additionalParams" class="form-label">Additional Parameters (Optional)</label>
                    <textarea class="form-control" id="additionalParams" name="additional_params" rows="3" placeholder="Enter as JSON: {&quot;param1&quot;: &quot;value1&quot;, &quot;param2&quot;: &quot;value2&quot;}"></textarea>
                    <small class="text-muted">Some exchanges require additional parameters like password or passphrase</small>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="testMode" name="test_mode">
                    <label class="form-check-label" for="testMode">
                        Enable Test Mode (Sandbox)
                    </label>
                    <small class="text-muted d-block">Use exchange's test environment instead of production</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveExchange" form="settings-form">
                    <i class="fas fa-plus-circle me-2"></i>Add Exchange
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Add exchange-config.js to the footer scripts
$additional_scripts = [
    'assets/js/exchange-config.js'
];
include 'includes/footer.php'; 
?>
