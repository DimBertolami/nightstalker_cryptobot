<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/cron_manager.php';

$title = "Night Stalker Installation";
require_once __DIR__ . '/../../includes/header.php';

$step = $_GET['step'] ?? 1;
$errors = [];

// Check for composer installation
$composerInstalled = file_exists(__DIR__ . '/../../vendor/autoload.php');
$composerJsonExists = file_exists(__DIR__ . '/../../composer.json');
$ccxtConfigExists = file_exists(__DIR__ . '/../../config/exchanges.json');
$directoriesReady = true;

// Check required directories
$requiredDirs = [
    __DIR__ . '/../../config',
    __DIR__ . '/../../logs',
    __DIR__ . '/../../assets/images/exchanges'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            $directoriesReady = false;
            $errors[] = "Failed to create directory: $dir";
        }
    } elseif (!is_writable($dir)) {
        if (!chmod($dir, 0777)) {
            $directoriesReady = false;
            $errors[] = "Directory not writable: $dir";
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Verify database connection
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($db->connect_error) {
            $errors[] = "Database connection failed: " . $db->connect_error;
        } else {
            // Create database if not exists
            if (!$db->select_db(DB_NAME)) {
                if (!$db->query("CREATE DATABASE " . DB_NAME)) {
                    $errors[] = "Failed to create database: " . $db->error;
                }
            }
            
            // Grant permissions to DB_USER
            $grantQuery = "GRANT ALL PRIVILEGES ON " . DB_NAME . ".* TO '" . DB_USER . "'@'localhost' IDENTIFIED BY '" . DB_PASS . "'";
            if (!$db->query($grantQuery)) {
                $errors[] = "Failed to grant privileges: " . $db->error;
            }
            
            $db->close();
            
            if (empty($errors)) {
                header("Location: ?step=2");
                exit();
            }
        }
    } elseif ($step == 2) {
        // Run database migrations
        require_once __DIR__ . '/schema.php';
        
        header("Location: ?step=3");
        exit();
    } elseif ($step == 3) {
        // Set up CCXT and exchange configuration
        if (!$composerJsonExists) {
            // Create composer.json if it doesn't exist
            $composerJson = [
                "require" => [
                    "ccxt/ccxt" => "^4.0",
                    "vlucas/phpdotenv" => "^5.6"
                ]
            ];
            
            if (!file_put_contents(__DIR__ . '/../../composer.json', json_encode($composerJson, JSON_PRETTY_PRINT))) {
                $errors[] = "Failed to create composer.json file";
            }
        }
        
        // Create default exchange configuration
        if (!$ccxtConfigExists) {
            $exchangesConfig = [
                "jupiter" => [
                    "name" => "Jupiter (Solana)",
                    "enabled" => true,
                    "is_default" => true,
                    "credentials" => [
                        "api_key" => "",
                        "api_secret" => "",
                        "test_mode" => false
                    ]
                ],
                "binance" => [
                    "name" => "Binance",
                    "enabled" => true,
                    "is_default" => false,
                    "credentials" => [
                        "api_key" => "",
                        "api_secret" => "",
                        "test_mode" => false
                    ]
                ],
                "bitvavo" => [
                    "name" => "Bitvavo",
                    "enabled" => true,
                    "is_default" => false,
                    "credentials" => [
                        "api_key" => "",
                        "api_secret" => "",
                        "test_mode" => false
                    ]
                ]
            ];
            
            if (!file_put_contents(__DIR__ . '/../../config/exchanges.json', json_encode($exchangesConfig, JSON_PRETTY_PRINT))) {
                $errors[] = "Failed to create exchanges configuration file";
            }
        }
        
        if (empty($errors)) {
            header("Location: ?step=4");
            exit();
        }
    } elseif ($step == 4) {
        // Set up cron job for fetch_coins.php
        $cronInterval = 30; // Default to 30 minutes
        $cronEnabled = true;
        
        // Schedule the cron job
        $cronResult = schedule_fetch_coins_cron($cronInterval, $cronEnabled);
        
        if (!$cronResult) {
            $errors[] = "Failed to set up cron job for fetch_coins.php";
        }
        
        if (empty($errors)) {
            header("Location: ?step=5");
            exit();
        }
    }
}

?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0 text-center">Night Stalker Installation</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Errors occurred:</h5>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step == 1): ?>
                        <div class="mb-4">
                            <h4>Step 1: Database Setup</h4>
                            <p>We'll now verify database connection and create the necessary database.</p>
                            
                            <div class="mb-3">
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Database Host</th>
                                        <td><?php echo DB_HOST; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Database Name</th>
                                        <td><?php echo DB_NAME; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Database User</th>
                                        <td><?php echo DB_USER; ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <form method="POST">
                                <button type="submit" class="btn btn-primary">Continue to Step 2</button>
                            </form>
                        </div>
                    
                    <?php elseif ($step == 2): ?>
                        <div class="mb-4">
                            <h4>Step 2: Database Migration</h4>
                            <p>Creating database tables and initial data...</p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Please wait while we set up the database structure.
                            </div>
                            
                            <form method="POST">
                                <button type="submit" class="btn btn-primary">Run Migrations</button>
                            </form>
                        </div>
                    
                    <?php elseif ($step == 3): ?>
                        <div class="mb-4">
                            <h4>Step 3: Exchange Configuration</h4>
                            <p>Setting up CCXT integration and default exchange configurations.</p>
                            
                            <div class="mb-3">
                                <h5>Prerequisites:</h5>
                                <ul class="list-group mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Composer Configuration
                                        <?php if ($composerJsonExists): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fas fa-check"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning rounded-pill"><i class="fas fa-times"></i></span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Exchange Configuration
                                        <?php if ($ccxtConfigExists): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fas fa-check"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning rounded-pill"><i class="fas fa-times"></i></span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Required Directories
                                        <?php if ($directoriesReady): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fas fa-check"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning rounded-pill"><i class="fas fa-times"></i></span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                We'll set up the default exchange configurations (Jupiter, Binance, Bitvavo) and prepare the system for CCXT integration.
                            </div>
                            
                            <form method="POST">
                                <button type="submit" class="btn btn-primary">Configure Exchanges</button>
                            </form>
                        </div>
                        
                    <?php elseif ($step == 4): ?>
                        <div class="mb-4">
                            <h4>Step 4: Scheduling Data Updates</h4>
                            <p>Setting up automatic data fetching via cron job.</p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-clock me-2"></i>
                                We'll configure a cron job to fetch cryptocurrency data every 30 minutes.
                            </div>
                            
                            <div class="mb-3">
                                <p>This will ensure your cryptocurrency data stays up-to-date automatically.</p>
                                <p>You can adjust the frequency later in the settings panel.</p>
                            </div>
                            
                            <form method="POST">
                                <button type="submit" class="btn btn-primary">Set Up Cron Job</button>
                            </form>
                        </div>
                        
                    <?php elseif ($step == 5): ?>
                        <div class="mb-4">
                            <h4>Step 5: Installation Complete</h4>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Night Stalker has been successfully installed!
                            </div>
                            
                            <div class="mb-3">
                                <h5>Next Steps:</h5>
                                <ol>
                                    <li>Configure your CoinGecko API key in settings</li>
                                    <li>Set up exchange API keys in the Exchange Configuration panel</li>
                                    <li>Install Composer dependencies with: <code>php composer.phar install --ignore-platform-req=ext-gmp</code></li>
                                    <li>Adjust data fetching frequency in the Settings panel if needed</li>
                                    <li>Review the default trading parameters</li>
                                </ol>
                            </div>
                            
                            <div class="text-center">
                                <a href="/NS/index.php" class="btn btn-success">
                                    <i class="fas fa-rocket me-2"></i> Launch Night Stalker
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <small class="text-muted">Installation Step <?php echo $step; ?> of 5</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Add important notes about exchange configuration
if ($step == 5): // Only show on final step
?>
<div class="container mt-4">
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Important Notes for Exchange Configuration</h5>
        <p>When adding cryptocurrency exchanges to Night Stalker, please ensure:</p>
        <ul>
            <li>The <code>config</code> directory has proper write permissions (777)</li>
            <li>API keys have proper permissions on the exchange platform</li>
            <li>API URL is correctly specified for each exchange</li>
            <li>Check browser console for any JavaScript errors if exchanges cannot be added</li>
        </ul>
        <p>For detailed troubleshooting, refer to the <a href="/NS/README.md">README documentation</a>.</p>
    </div>
</div>
<?php
endif;

require_once __DIR__ . '/../../includes/footer.php';
