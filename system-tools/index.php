<?php
// Set page title
$title = 'System Tools';

// Include header
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login
    echo '<div class="alert alert-danger">Please log in to access system tools.</div>';
    require_once '../includes/footer.php';
    exit;
}

// Define available tools
$tools = [
    'sync_trade_tables' => [
        'name' => 'Trade Table Synchronizer',
        'description' => 'Synchronizes trade tables with latest trade data',
        'script' => '/opt/lampp/htdocs/NS/tools/sync_trade_tables.php',
        'icon' => 'fa-sync',
        'category' => 'Data',
        'last_run' => getLastRunTime('sync_trade_tables')
    ],
    'trade_diagnostics' => [
        'name' => 'Trade Diagnostics',
        'description' => 'Diagnoses trade operations and provides insights',
        'script' => '/opt/lampp/htdocs/NS/tools/trade_diagnostics.php',
        'icon' => 'fa-bug',
        'category' => 'Data',
        'last_run' => getLastRunTime('trade_diagnostics')
    ],
    'delete_all_coins' => [
        'name' => 'delete coins',
        'description' => 'delete coins from the database',
        'script' => '/opt/lampp/htdocs/NS/delete_coins.php',
        'icon' => 'fa-coins',
        'category' => 'Data',
        'last_run' => getLastRunTime('delete_all_coins')
    ],
    'cmc_fetch_bitvavo_coins' => [
        'name' => 'CMC list of bitvavo coins',
        'description' => 'Updates the database with latest bitvavo coins from CoinMarketCap API',
        'script' => '/opt/lampp/htdocs/NS/crons/bitvavoFromCMC4NS.py',
        'icon' => 'fa-coins',
        'category' => 'Data',
        'last_run' => getLastRunTime('bitvavoFromCMC4NS')
    ],
    'cmc_fetch_binance_coins' => [
        'name' => 'CMC list of binance coins',
        'description' => 'Updates the database with latest binance coins from CoinMarketCap API',
        'script' => '/opt/lampp/htdocs/NS/crons/binanceFromCMC4NS.py',
        'icon' => 'fa-coins',
        'category' => 'Data',
        'last_run' => getLastRunTime('binanceFromCMC4NS')
    ],
    'cron_manager' => [
        'name' => 'Cron Manager',
        'description' => 'Manage scheduled tasks and automated processes',
        'script' => '/opt/lampp/htdocs/NS/tools/cron_manager_tool.php',
        'icon' => 'fa-clock',
        'category' => 'Maintenance',
        'last_run' => null
    ],
    'export_sensitive_data' => [
        'name' => 'Export Sensitive Data',
        'description' => 'Backs up all sensitive data including database, config files, and credentials',
        'script' => '/opt/lampp/htdocs/NS/export_sensitive_data.sh',
        'icon' => 'fa-shield-alt',
        'category' => 'Maintenance',
        'last_run' => null
    ],
    'log_reader' => [
        'name' => 'log reader',
        'description' => 'Shows the last 20 lines of the Nightstalkers system logs',
        'script' => '/opt/lampp/htdocs/NS/tools/log_reader.sh',
        'icon' => 'fa-shield-alt',
        'category' => 'Analytics',
        'last_run' => null
    ],
    'sync_portfolio_to_cryptocurrencies' => [
        'name' => 'portfolio to cryptocurrencies sync',
        'description' => 'Syncs the portfolio table with the cryptocurrencies table',
        'script' => '/opt/lampp/htdocs/NS/tools/sync_portfolio_to_cryptocurrencies.php',
        'icon' => 'fa-shield-alt',
        'category' => 'Data',
        'last_run' => null
    ]
    // Add more tools here as they are created
];

// Function to get last run time
function getLastRunTime($tool) {
    $db = getDBConnection();
    
    if (!$db) {
        return null;
    }
    
    try {
        if ($tool === 'coingecko') {
            $stmt = $db->query("SELECT MAX(last_updated) as last_run FROM all_coingecko_coins");
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['last_run'] ?? null;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting last run time: " . $e->getMessage());
    }
    
    return null;
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-tools me-2"></i>System Tools</h1>
            <p class="lead">Administrative tools for system maintenance and data management</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-dark text-light mb-4">
                <div class="card-header">
                    <h5>Categories</h5>
                </div>
                <div class="list-group list-group-flush bg-dark">
                    <a href="#data-tools" class="list-group-item list-group-item-action bg-dark text-light">
                        <i class="fas fa-database me-2"></i>Data Management
                    </a>
                    <a href="#maintenance-tools" class="list-group-item list-group-item-action bg-dark text-light">
                        <i class="fas fa-wrench me-2"></i>Maintenance
                    </a>
                    <a href="#analytics-tools" class="list-group-item list-group-item-action bg-dark text-light">
                        <i class="fas fa-chart-bar me-2"></i>Analytics
                    </a>
                    <a href="#trading-tools" class="list-group-item list-group-item-action bg-dark text-light">
                        <i class="fas fa-chart-line me-2"></i>Trading
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <section id="data-tools" class="mb-5">
                <h2><i class="fas fa-database me-2"></i>Data Management</h2>
                <div class="row">
                    <?php foreach ($tools as $id => $tool): ?>
                    <?php if ($tool['category'] === 'Data'): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas <?= $tool['icon'] ?> me-2"></i><?= $tool['name'] ?></h5>
                                <?php if ($tool['last_run']): ?>
                                <span class="badge bg-info">Last run: <?= date('M j, H:i', strtotime($tool['last_run'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p><?= $tool['description'] ?></p>
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-primary run-script" data-tool="<?= $id ?>">
                                        <i class="fas fa-play me-2"></i>Run Now
                                    </button>
                                    <button class="btn btn-sm btn-outline-info view-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-file-alt me-2"></i>View Log
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger clear-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-trash-alt me-2"></i>Clear Logs
                                    </button>
                                </div>
                            </div>
                            <div class="card-footer p-0">
                                <div class="output-container p-3" id="output-<?= $id ?>" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Waiting for execution...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <section id="maintenance-tools" class="mb-5">
                <h2><i class="fas fa-wrench me-2"></i>Maintenance</h2>
                <div class="row">
                    <?php foreach ($tools as $id => $tool): ?>
                    <?php if ($tool['category'] === 'Maintenance'): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas <?= $tool['icon'] ?> me-2"></i><?= $tool['name'] ?></h5>
                                <div>
                                    <?php if ($tool['last_run']): ?>
                                    <small class="text-muted">Last run: <?= $tool['last_run'] ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p><?= $tool['description'] ?></p>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-primary btn-sm run-script me-2" data-tool="<?= $id ?>">
                                        <i class="fas fa-play me-2"></i>Run Now
                                    </button>
                                    <button class="btn btn-sm btn-outline-info view-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-file-alt me-2"></i>View Log
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger clear-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-trash-alt me-2"></i>Clear Logs
                                    </button>
                                </div>
                            </div>
                            <div class="card-footer p-0">
                                <div class="output-container p-3" id="output-<?= $id ?>" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Waiting for execution...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <section id="analytics-tools" class="mb-5">
                <h2><i class="fas fa-chart-bar me-2"></i>Analytics</h2>
                <div class="row">
                    <?php foreach ($tools as $id => $tool): ?>
                    <?php if ($tool['category'] === 'Analytics'): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas <?= $tool['icon'] ?> me-2"></i><?= $tool['name'] ?></h5>
                                <div>
                                    <?php if ($tool['last_run']): ?>
                                    <small class="text-muted">Last run: <?= $tool['last_run'] ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p><?= $tool['description'] ?></p>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-primary btn-sm run-script me-2" data-tool="<?= $id ?>">
                                        <i class="fas fa-play me-2"></i>Run Now
                                    </button>
                                    <button class="btn btn-sm btn-outline-info view-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-file-alt me-2"></i>View Log
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger clear-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-trash-alt me-2"></i>Clear Logs
                                    </button>
                                </div>
                            </div>
                            <div class="card-footer p-0">
                                <div class="output-container p-3" id="output-<?= $id ?>" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <section id="trading-tools" class="mb-5">
                <h2><i class="fas fa-chart-line me-2"></i>Trading</h2>
                <div class="row">
                    <?php foreach ($tools as $id => $tool): ?>
                    <?php if ($tool['category'] === 'Trading'): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas <?= $tool['icon'] ?> me-2"></i><?= $tool['name'] ?></h5>
                                <?php if ($tool['last_run']): ?>
                                <span class="badge bg-info">Last run: <?= date('M j, H:i', strtotime($tool['last_run'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p><?= $tool['description'] ?></p>
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-primary run-script" data-tool="<?= $id ?>">
                                        <i class="fas fa-play me-2"></i>Run Now
                                    </button>
                                    <button class="btn btn-sm btn-outline-info view-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-file-alt me-2"></i>View Log
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger clear-log" data-tool="<?= $id ?>">
                                        <i class="fas fa-trash-alt me-2"></i>Clear Logs
                                    </button>
                                </div>
                            </div>
                            <div class="card-footer p-0">
                                <div class="output-container p-3" id="output-<?= $id ?>" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Script execution endpoint -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Run script buttons
    document.querySelectorAll('.run-script').forEach(button => {
        button.addEventListener('click', async function() {
            const toolId = this.dataset.tool;
            const outputContainer = document.getElementById(`output-${toolId}`);
            
            // Show output container
            outputContainer.style.display = 'block';
            outputContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin me-2"></i>Running script...
                </div>
            `;
            
            try {
                const response = await fetch(`run.php?tool=${toolId}`);
                const result = await response.json();
                
                if (result.success) {
                    // Check if we need to redirect to a tool interface
                    if (result.redirect) {
                        outputContainer.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>${result.message}
                            </div>
                            <p>${result.output}</p>
                            <div class="text-center mt-3">
                                <a href="${result.redirect}" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>Open Interface
                                </a>
                            </div>
                        `;
                        // Open the interface automatically
                        window.open(result.redirect, '_blank');
                    } else {
                        outputContainer.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>${result.message}
                            </div>
                            <pre class="bg-dark text-light p-3 rounded">${result.output}</pre>
                        `;
                    }
                } else {
                    outputContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>${result.message}
                        </div>
                        <pre class="bg-dark text-light p-3 rounded">${result.output || 'No output available'}</pre>
                    `;
                }
            } catch (error) {
                outputContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error executing script: ${error.message}
                    </div>
                `;
            }
        });
    });
    
    // View log buttons
    document.querySelectorAll('.view-log').forEach(button => {
        button.addEventListener('click', async function() {
            const toolId = this.dataset.tool;
            const outputContainer = document.getElementById(`output-${toolId}`);
            
            // Show output container
            outputContainer.style.display = 'block';
            outputContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin me-2"></i>Loading log...
                </div>
            `;
            
            try {
                const response = await fetch(`logs.php?tool=${toolId}`);
                const result = await response.json();
                
                if (result.success) {
                    outputContainer.innerHTML = `
                        <h5>Log History</h5>
                        <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">${result.log}</pre>
                    `;
                } else {
                    outputContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                outputContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error loading log: ${error.message}
                    </div>
                `;
            }
        });
    });
    
    // Clear logs buttons
    document.querySelectorAll('.clear-log').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to clear all logs for this tool?')) {
                return;
            }
            
            const toolId = this.dataset.tool;
            const outputContainer = document.getElementById(`output-${toolId}`);
            
            // Show output container
            outputContainer.style.display = 'block';
            outputContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin me-2"></i>Clearing logs...
                </div>
            `;
            
            try {
                const response = await fetch(`clear_logs.php?tool=${toolId}`);
                const result = await response.json();
                
                if (result.success) {
                    outputContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>${result.message}
                        </div>
                    `;
                } else {
                    outputContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                outputContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error clearing logs: ${error.message}
                    </div>
                `;
            }
        });
    });
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
