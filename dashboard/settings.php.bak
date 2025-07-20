<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TradingLogger.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

// Check if form was submitted
$message = '';
$messageType = '';

// Get current settings
$configFile = __DIR__ . '/../crons/execute_new_coin_strategy.php';
$settings = [
    'testMode' => true,
    'buyAmount' => 50,
    'profitTarget' => 5,
    'stopLoss' => -5,
    'maxHoldingTime' => 3600,
    'refreshInterval' => 3
];

// Initialize API data sources if missing
$tradingConfigPath = __DIR__ . '/../config/trading_config.json';
if (file_exists($tradingConfigPath)) {
    $tradingConfig = json_decode(file_get_contents($tradingConfigPath), true);
    if (!isset($tradingConfig['api_data_sources'])) {
        $tradingConfig['api_data_sources'] = [
            'coingecko' => ['enabled' => true],
            'coinmarketcap' => ['enabled' => true],
            'bitvavo' => ['enabled' => false],
            'jupiter' => ['enabled' => false],
            'alpaca' => ['enabled' => false],
            'livecoinwatch' => ['enabled' => false]
        ];
        file_put_contents($tradingConfigPath, json_encode($tradingConfig, JSON_PRETTY_PRINT));
    }
}

// Read current settings from the config file
if (file_exists($configFile)) {
    $configContent = file_get_contents($configFile);
    
    // Extract settings using regex
    if (preg_match('/\\$testMode\s*=\s*(true|false)/', $configContent, $matches)) {
        $settings['testMode'] = $matches[1] === 'true';
    }
    
    if (preg_match('/\\$buyAmount\s*=\s*([0-9.]+)/', $configContent, $matches)) {
        $settings['buyAmount'] = (float)$matches[1];
    }
    
    if (preg_match('/\\$profitTarget\s*=\s*([0-9.]+)/', $configContent, $matches)) {
        $settings['profitTarget'] = (float)$matches[1];
    }
    
    if (preg_match('/\\$stopLoss\s*=\s*-[0-9.]+/', $configContent, $matches)) {
        $settings['stopLoss'] = (float)$matches[1];
    }
    
    if (preg_match('/\\$maxHoldingTime\s*=\s*([0-9]+)/', $configContent, $matches)) {
        $settings['maxHoldingTime'] = (int)$matches[1];
    }
    
    if (preg_match('/\\$refreshInterval\s*=\s*([0-9]+)/', $configContent, $matches)) {
        $settings['refreshInterval'] = (int)$matches[1];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which form was submitted
    if (isset($_POST['saveStrategy'])) {
        // Process New Coin Strategy settings
        $strategyConfig = [
            'enabled' => isset($_POST['strategyEnabled']),
            'maxCoinAge' => intval($_POST['maxCoinAge']),
            'minMarketCap' => intval($_POST['minMarketCap']),
            'minVolume' => intval($_POST['minVolume']),
            'monitoringInterval' => intval($_POST['monitoringInterval']),
            'sellTriggerSeconds' => intval($_POST['sell_trigger_duration'])
        ];
        
        // Validate strategy settings
        $errors = [];
        if ($strategyConfig['maxCoinAge'] < 1 || $strategyConfig['maxCoinAge'] > 72) {
            $errors[] = "Maximum coin age must be between 1 and 72 hours";
        }
        
        if ($strategyConfig['minMarketCap'] < 100000) {
            $errors[] = "Minimum market cap must be at least $100,000";
        }
        
        if ($strategyConfig['minVolume'] < 100000) {
            $errors[] = "Minimum volume must be at least $100,000";
        }
        
        if ($strategyConfig['monitoringInterval'] < 1 || $strategyConfig['monitoringInterval'] > 30) {
            $errors[] = "Monitoring interval must be between 1 and 30 seconds";
        }
        
        if ($strategyConfig['sellTriggerSeconds'] < 5 || $strategyConfig['sellTriggerSeconds'] > 300) {
            $errors[] = "Sell trigger duration must be between 5 and 300 seconds";
        }
        
        if (empty($errors)) {
            // Save strategy settings
            $configFile = __DIR__ . '/../config/new_coin_strategy.json';
            if (file_put_contents($configFile, json_encode($strategyConfig, JSON_PRETTY_PRINT))) {
                $message = "Strategy settings updated successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to write strategy settings to file. Check permissions.";
                $messageType = "danger";
            }
        } else {
            $message = "Please fix the following errors: " . implode(", ", $errors);
            $messageType = "danger";
        }
    } else if (isset($_POST['defaultExchange'])) {
        // Process exchange configuration
        require_once __DIR__ . '/../includes/exchange_config.php';
        
        // Update default exchange
        set_default_exchange($_POST['defaultExchange']);
        
        // Update exchange enabled status
        $exchanges = get_exchanges();
        foreach ($exchanges as $exchange_id => $exchange) {
            $enabled = isset($_POST['exchanges'][$exchange_id]);
            update_exchange_status($exchange_id, $enabled);
        }
        
        $message = "Exchange settings updated successfully!";
        $messageType = "success";
    } else {
        // Process trading bot settings
        $newSettings = [
            'testMode' => isset($_POST['testMode']) ? true : false,
            'buyAmount' => isset($_POST['buyAmount']) ? (float)$_POST['buyAmount'] : $settings['buyAmount'],
            'profitTarget' => isset($_POST['profitTarget']) ? (float)$_POST['profitTarget'] : $settings['profitTarget'],
            'stopLoss' => isset($_POST['stopLoss']) ? (float)$_POST['stopLoss'] : $settings['stopLoss'],
            'maxHoldingTime' => isset($_POST['maxHoldingTime']) ? (int)$_POST['maxHoldingTime'] : $settings['maxHoldingTime'],
            'refreshInterval' => isset($_POST['refreshInterval']) ? (int)$_POST['refreshInterval'] : $settings['refreshInterval']
        ];
        
        // Validate values
        $errors = [];
        if ($newSettings['buyAmount'] <= 0) {
            $errors[] = "Buy amount must be greater than 0";
        }
        
        if ($newSettings['profitTarget'] <= 0) {
            $errors[] = "Profit target must be greater than 0";
        }
        
        if ($newSettings['stopLoss'] >= 0) {
            $errors[] = "Stop loss must be less than 0";
        }
        
        if ($newSettings['maxHoldingTime'] <= 0) {
            $errors[] = "Maximum holding time must be greater than 0";
        }
        
        if ($newSettings['refreshInterval'] <= 0 || $newSettings['refreshInterval'] > 60) {
            $errors[] = "Refresh interval must be between 1 and 60 seconds";
        }
        
        if (empty($errors)) {
            // Update the config file
            if (file_exists($configFile)) {
                $configContent = file_get_contents($configFile);
                
                // Replace settings in the file
                $configContent = preg_replace('/\\$testMode\s*=\s*(true|false)/', '$testMode = ' . ($newSettings['testMode'] ? 'true' : 'false'), $configContent);
                $configContent = preg_replace('/\\$buyAmount\s*=\s*[0-9.]+/', '$buyAmount = ' . $newSettings['buyAmount'], $configContent);
                $configContent = preg_replace('/\\$profitTarget\s*=\s*[0-9.]+/', '$profitTarget = ' . $newSettings['profitTarget'], $configContent);
                $configContent = preg_replace('/\\$stopLoss\s*=\s*-[0-9.]+/', '$stopLoss = ' . $newSettings['stopLoss'], $configContent);
                $configContent = preg_replace('/\\$maxHoldingTime\s*=\s*[0-9]+/', '$maxHoldingTime = ' . $newSettings['maxHoldingTime'], $configContent);
                $configContent = preg_replace('/\\$refreshInterval\s*=\s*[0-9]+/', '$refreshInterval = ' . $newSettings['refreshInterval'], $configContent);
                
                if (file_put_contents($configFile, $configContent)) {
                    $message = "Settings updated successfully!";
                    $messageType = "success";
                    $settings = $newSettings; // Update current settings
                } else {
                    $message = "Failed to write settings to file. Check permissions.";
                    $messageType = "danger";
                }
            } else {
                $message = "Config file not found!";
                $messageType = "danger";
            }
        } else {
            $message = "Please fix the following errors: " . implode(", ", $errors);
            $messageType = "danger";
        }
    }
}

// Get trading statistics
$logger = new TradingLogger();
$allTimePerformance = $logger->getPerformance('new_coin_strategy', 'all');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Night Stalker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
<style>
    .tab-card {
        transition: all 0.3s ease;
        opacity: 0.8;
        cursor: pointer;
    }
    
    .tab-card:hover {
        transform: translateY(-3px);
    }
    
    .active-tab {
        opacity: 1 !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
</style>
</head>
<body>
    <?php include_once('nav.php'); ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Settings</h2>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i>Save All Settings
            </button>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Configuration Tabs -->
        <div class="row mb-4 justify-content-center" style="max-width: 900px; margin-left: auto; margin-right: auto;">
            <div class="col-md-4 px-1">
                <div class="card bg-dark color-yellow tab-card" id="strategy-tab" onclick="showTab('strategy')">
                    <div class="card-body py-3">
                        <h5 class="card-title mb-0 text-center">Strategy Configuration</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4 px-1">
                <div class="card bg-dark color-yellow tab-card" id="exchange-tab" onclick="showTab('exchange')">
                    <div class="card-body py-3">
                        <h5 class="card-title mb-0 text-center">Exchange Configuration</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4 px-1">
                <div class="card bg-dark color-yellow tab-card" id="api-tab" onclick="showTab('api')">
                    <div class="card-body py-3">
                        <h5 class="card-title mb-0 text-center">API Configuration</h5>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="POST" id="settings-form">
        <!-- Configuration Content -->
        <div class="row justify-content-center" style="max-width: 900px; margin-left: auto; margin-right: auto;">
            <!-- Strategy Configuration Content -->
            <div class="col-12 mb-4 tab-content" id="strategy-content">
                <div class="card">
                            <div class="card-header bg-dark color-yellow text-white d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0 color-yellow">Strategy Configuration</h3>
                                </div>
                                <div class="form-group mb-0">
                                    <select class="form-select form-select-sm" id="strategy-selector" onchange="switchStrategy(this.value)">
                                        <option value="general">5% Strategy</option>
                                        <option value="new_coin">New Coin Strategy</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- General Trading Settings (formerly Trading Bot Configuration) -->
                                <div id="general-settings" class="strategy-section">
                                    <div class="alert alert-info">
                                        <h4 class="alert-heading">5% Strategy</h4>
                                        <p>Sell when the profit reaches 5%</p>
                                    </div>
                                    
                                    <form method="POST" action="" id="generalForm">
                                        <div class="mb-3 form-check form-switch">
                                            <input class="form-check-input bg-dark color-yellow" type="checkbox" id="testMode" name="testMode" <?php echo $settings['testMode'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="testMode">Simulation Mode</label>
                                            <div class="form-text">When enabled, trades are simulated and no real money is used.</div>
                                        </div>
                                
                                        <div class="mb-3">
                                            <label for="buyAmount" class="form-label">Buy Amount (EUR)</label>
                                            <input type="number" class="form-control" id="buyAmount" name="buyAmount" value="<?php echo $settings['buyAmount']; ?>" step="0.01" min="0" required>
                                            <div class="form-text">Amount in EUR to spend on each trade.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="profitTarget" class="form-label">Profit Target (%)</label>
                                            <input type="number" class="form-control" id="profitTarget" name="profitTarget" value="<?php echo $settings['profitTarget']; ?>" step="0.1" min="0.1" required>
                                            <div class="form-text">Percentage profit at which to sell.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="stopLoss" class="form-label">Stop Loss (%)</label>
                                            <input type="number" class="form-control" id="stopLoss" name="stopLoss" value="<?php echo $settings['stopLoss']; ?>" step="0.1" max="0" required>
                                            <div class="form-text">Percentage loss at which to sell (negative number).</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="maxHoldingTime" class="form-label">Maximum Holding Time (seconds)</label>
                                            <input type="number" class="form-control" id="maxHoldingTime" name="maxHoldingTime" value="<?php echo $settings['maxHoldingTime']; ?>" min="60" required>
                                            <div class="form-text">Maximum time to hold a position before selling (in seconds).</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="refreshInterval" class="form-label">Price Refresh Interval (seconds)</label>
                                            <input type="number" class="form-control" id="refreshInterval" name="refreshInterval" value="<?php echo $settings['refreshInterval']; ?>" min="1" max="60" required>
                                            <div class="form-text">How often to check prices (in seconds).</div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary" name="saveGeneral">Save General Settings</button>
                            </form>
                        </div>
                        
                        <!-- New Coin Strategy Settings -->
                        <div id="new-coin-settings" class="strategy-section" style="display: none;">
                            <div class="alert alert-info">
                                <h4 class="alert-heading">New Coin Strategy</h4>
                                <p>Monitors new cryptocurrencies for potential investment.</p>
                            </div>
                            <form method="POST" action="" id="newCoinForm">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="strategyEnabled" name="strategyEnabled" checked>
                                    <label class="form-check-label" for="strategyEnabled">Enable New Coin Strategy</label>
                                    <div class="form-text">When enabled, this bot will look for newly listed coins and trade.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="maxCoinAge" class="form-label">Maximum Coin Age (hours)</label>
                                    <input type="number" class="form-control" id="maxCoinAge" name="maxCoinAge" value="24" min="1" max="72" required>
                                    <div class="form-text">Only consider coins listed within this timeframe.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="minMarketCap" class="form-label">Minimum Market Cap ($)</label>
                                    <input type="number" class="form-control" id="minMarketCap" name="minMarketCap" value="1500000" min="100000" required>
                                    <div class="form-text">Only consider coins with market cap above this threshold.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="minVolume" class="form-label">Minimum 24h Volume ($)</label>
                                    <input type="number" class="form-control" id="minVolume" name="minVolume" value="1500000" min="100000" required>
                                    <div class="form-text">Only consider coins with 24h trading volume above this threshold.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="monitoringInterval" class="form-label">Price Monitoring Interval (seconds)</label>
                                    <input type="number" class="form-control" id="monitoringInterval" name="monitoringInterval" value="3" min="1" max="30" required>
                                    <div class="form-text">How often to check prices during active monitoring.</div>
                                </div>
                                <div class="mb-3">
                                <label for="sell_trigger_duration">Sell trigger duration (seconds):</label>
                                <input type="number" id="sell_trigger_duration" name="sell_trigger_duration" min="5" max="300" value="30" required>
                                <small>Enter a value between 5 and 300 seconds.</small>
                                </div>
                                <button type="submit" class="btn btn-primary" name="saveStrategy">Save Strategy Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Exchange Configuration Content -->
            <div class="col-12 mb-4 tab-content" id="exchange-content" style="display: none;">
                <div class="card">
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
                                require_once __DIR__ . '/../includes/exchange_config.php';
                                
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

                                            {$exchange_name}
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-exchange" data-exchange-id="{$exchange_id}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-exchange" data-exchange-id="{$exchange_id}">
                                            <i class="bi bi-trash"></i>
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
                                <i class="bi bi-plus-circle"></i> Add New Exchange
                            </button>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>
            
            <!-- API Configuration Content -->
            <div class="col-md-12 col-lg-10 col-xl-8 mx-auto mb-4 tab-content" id="api-content" style="display: none;">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">API Configuration</h3>
                    </div>
                    <div class="card-body bg-white">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="enableFetching" name="enable_fetching" checked>
                            <label class="form-check-label" for="enableFetching">
                                Enable Fetching of New Coins
                            </label>
                            <small class="text-muted d-block">Master switch to turn on/off all coin fetching</small>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Data Sources</h5>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addDataSourceBtn">
                                <i class="bi bi-plus-circle"></i> Add Data Source
                            </button>
                            <!-- Add Data Source Modal -->
                            <div class="modal fade" id="addDataSourceModal" tabindex="-1" aria-labelledby="addDataSourceModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addDataSourceModalLabel">Add API Data Source</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="addDataSourceForm">
                                                <div class="mb-3">
                                                    <label for="dataSourceName" class="form-label">Source Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="dataSourceName" name="dataSourceName" required placeholder="e.g. CoinGecko">
                                                    <div class="form-text">Enter a unique name for this data source.</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="dataSourceApiKey" class="form-label">API Key</label>
                                                    <input type="text" class="form-control" id="dataSourceApiKey" name="dataSourceApiKey" placeholder="Optional">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="dataSourceApiSecret" class="form-label">API Secret</label>
                                                    <input type="text" class="form-control" id="dataSourceApiSecret" name="dataSourceApiSecret" placeholder="Optional">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="dataSourceApiUrl" class="form-label">API URL</label>
                                                    <input type="text" class="form-control" id="dataSourceApiUrl" name="dataSourceApiUrl" placeholder="Optional">
                                                </div>
                                                <button type="submit" class="btn btn-primary">Add Data Source</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                        <div class="d-flex align-items-center mb-2">
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="coinGecko" name="data_sources[coingecko]" checked>
                                <label class="form-check-label" for="coinGecko">CoinGecko</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 me-1" title="Edit CoinGecko"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 delete-data-source" data-source-id="coingecko" title="Delete CoinGecko"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="coinMarketCap" name="data_sources[coinmarketcap]" <?= $tradingConfig['api_data_sources']['coinmarketcap']['enabled'] ?? false ? 'checked' : '' ?>>
                                <label class="form-check-label" for="coinMarketCap">Enable CoinMarketCap Data</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 me-1" title="Edit CoinMarketCap"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 delete-data-source" data-source-id="coinmarketcap" title="Delete CoinMarketCap"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="bitvavo" name="data_sources[bitvavo]" <?= $tradingConfig['api_data_sources']['bitvavo']['enabled'] ?? false ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bitvavo">Bitvavo</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 me-1" title="Edit Bitvavo"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 delete-data-source" data-source-id="bitvavo" title="Delete Bitvavo"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="jupiter" name="data_sources[jupiter]" <?= $tradingConfig['api_data_sources']['jupiter']['enabled'] ?? false ? 'checked' : '' ?>>
                                <label class="form-check-label" for="jupiter">Jupiter</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 me-1" title="Edit Jupiter"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 delete-data-source" data-source-id="jupiter" title="Delete Jupiter"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="alpaca" name="data_sources[alpaca]" <?= $tradingConfig['api_data_sources']['alpaca']['enabled'] ?? false ? 'checked' : '' ?>>
                                <label class="form-check-label" for="alpaca">Alpaca</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 me-1" title="Edit Alpaca"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 delete-data-source" data-source-id="alpaca" title="Delete Alpaca"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="liveCoinWatch" name="data_sources[livecoinwatch]" <?= $tradingConfig['api_data_sources']['livecoinwatch']['enabled'] ?? false ? 'checked' : '' ?>>
                                <label class="form-check-label" for="liveCoinWatch">LiveCoinWatch</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm p-1 me-1" title="Edit LiveCoinWatch"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 delete-data-source" data-source-id="livecoinwatch" title="Delete LiveCoinWatch"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Stats Confirmation Modal -->
    <div class="modal fade" id="resetStatsModal" tabindex="-1" aria-labelledby="resetStatsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetStatsModalLabel">Confirm Reset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger">Warning: This will reset all trading statistics. This action cannot be undone.</p>
                    <p>Trading logs will be preserved, but all calculated statistics will be reset to zero.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="reset_stats.php" class="btn btn-danger">Reset Statistics</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reset stats button
            const resetStatsBtn = document.getElementById('resetStatsBtn');
            if (resetStatsBtn) {
                resetStatsBtn.addEventListener('click', function() {
                    const resetStatsModal = new bootstrap.Modal(document.getElementById('resetStatsModal'));
                    resetStatsModal.show();
                });
            }
        });
    </script>
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
                    <input type="password" class="form-control" id="apiSecret" placeholder="Enter API Secret">
                </div>
                
                <div class="mb-3">
                    <label for="apiUrl" class="form-label">API URL (Optional)</label>
                    <input type="text" class="form-control" id="apiUrl" placeholder="Enter API URL (e.g., https://api.binance.com)">
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
                <button type="button" class="btn btn-primary" id="saveExchange">
                    <i class="bi bi-plus-circle me-2"></i>Add Exchange
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Strategy section switching
    function switchStrategy(strategy) {
        // Hide all strategy sections
        document.querySelectorAll('.strategy-section').forEach(section => {
            section.style.display = 'none';
        });
        // Show the selected strategy section
        if (strategy === 'general') {
            document.getElementById('general-settings').style.display = 'block';
        } else if (strategy === 'new_coin') {
            document.getElementById('new-coin-settings').style.display = 'block';
        }
    }
    
    // Tab switching functionality
    function showTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        
        // Show the selected tab content
        document.getElementById(tabName + '-content').style.display = 'block';
        
        // Update active tab styling
        document.querySelectorAll('.tab-card').forEach(tab => {
            tab.classList.remove('active-tab');
            tab.style.opacity = '0.8';
        });
        
        document.getElementById(tabName + '-tab').classList.add('active-tab');
        document.getElementById(tabName + '-tab').style.opacity = '1';
    }
    
    // Initialize with the default strategy and tab
    document.addEventListener('DOMContentLoaded', function() {
        const savedStrategy = localStorage.getItem('selectedStrategy') || 'general';
        document.getElementById('strategy-selector').value = savedStrategy;
        switchStrategy(savedStrategy);
        showTab('strategy'); // Initialize with strategy tab active
        
        // Add hover effect for tabs
        document.querySelectorAll('.tab-card').forEach(tab => {
            tab.addEventListener('mouseover', function() {
                if (!this.classList.contains('active-tab')) {
                    this.style.opacity = '0.9';
                    this.style.cursor = 'pointer';
                }
            });
            
            tab.addEventListener('mouseout', function() {
                if (!this.classList.contains('active-tab')) {
                    this.style.opacity = '0.8';
                }
            });
        });
    });
    
    // Alert function for displaying messages
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = message + 
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        
        const container = document.querySelector('.container');
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }, 5000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Reset stats button
        const resetStatsBtn = document.getElementById('resetStatsBtn');
        if (resetStatsBtn) {
            resetStatsBtn.addEventListener('click', function() {
                const resetStatsModal = new bootstrap.Modal(document.getElementById('resetStatsModal'));
                resetStatsModal.show();
            });
        }
    });

    // Make delete buttons in Data Sources remove the row from DOM and backend
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#api-content .delete-data-source').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var row = btn.closest('.d-flex.align-items-center');
                if (!row) return;
                var sourceKey = btn.getAttribute('data-source-id');
                if (!sourceKey) return;
                fetch('delete_data_source.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({source: sourceKey})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                    } else {
                        alert('Failed to delete data source: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Failed to contact backend.');
                });
            });
        });
    });
</script>
<script src="../assets/js/exchange-config.js"></script>
</body>
</html>
