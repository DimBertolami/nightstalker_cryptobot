<?php
/**
 * Exchange Monitor Runner
 * 
 * This script provides a web interface to start and manage the exchange price monitoring system.
 *
 * When called from the System Tools dashboard, it will output JSON-formatted status information.
 * When accessed directly, it will display the full web interface.
 */

require_once '/opt/lampp/htdocs/NS/includes/config.php';
require_once '/opt/lampp/htdocs/NS/includes/functions.php';
require_once '/opt/lampp/htdocs/NS/includes/auth.php';

// Check if this script is being called from the system-tools dashboard
$fromDashboard = (strpos($_SERVER['SCRIPT_NAME'], 'run.php') !== false);

// If called from dashboard, handle differently
if ($fromDashboard) {
    // Default to starting the monitor with Bitvavo
    $exchange = 'bitvavo';
    $action = 'redirect';
    
    // Record the execution in the system logs
    logEvent("Exchange Monitor launched from System Tools dashboard");
    
    // Output a message to redirect to the full interface
    echo "Starting ... Please wait...";
    
    // Return a special response for the dashboard
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Exchange Monitor interface launched',
        'output' => "The Exchange Monitor interface will open in a new window.",
        'redirect' => '/NS/system-tools/run_exchange_monitor.php'
    ]);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

// Handle form submission
$output = '';
$status = '';
$exchange = $_POST['exchange'] ?? 'binance';
$action = $_POST['action'] ?? '';

// Validate exchange
$supportedExchanges = ['binance', 'bitvavo'];
if (!in_array(strtolower($exchange), $supportedExchanges)) {
    $exchange = 'binance'; // Default to binance if invalid
}

// Check if monitor is running
function isMonitorRunning() {
    exec("ps aux | grep 'monitor_exchange_prices.php' | grep -v grep", $output);
    return !empty($output);
}

// Get monitor process details
function getMonitorProcessDetails() {
    exec("ps aux | grep 'monitor_exchange_prices.php' | grep -v grep", $output);
    return $output;
}

// Start the monitor
if ($action === 'start') {
    if (!isMonitorRunning()) {
        $cmd = "/opt/lampp/bin/php /opt/lampp/htdocs/NS/scripts/monitor_exchange_prices.php -e {$exchange} -v > /opt/lampp/htdocs/NS/logs/exchange_monitor.log 2>&1 &";
        exec($cmd);
        $status = "Exchange monitor started for {$exchange}. Check logs for details.";
    } else {
        $status = "Exchange monitor is already running.";
    }
}

// Stop the monitor
if ($action === 'stop') {
    if (isMonitorRunning()) {
        exec("pkill -f 'monitor_exchange_prices.php'");
        $status = "Exchange monitor stopped.";
    } else {
        $status = "Exchange monitor is not running.";
    }
}

// View logs
if ($action === 'logs') {
    $logFile = '/opt/lampp/htdocs/NS/logs/exchange_monitor.log';
    if (file_exists($logFile)) {
        $output = nl2br(htmlspecialchars(file_get_contents($logFile)));
    } else {
        $output = "No log file found.";
    }
}

// Check current status
$isRunning = isMonitorRunning();
$processDetails = $isRunning ? getMonitorProcessDetails() : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange Monitor - Night Stalker</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .log-container {
            background-color: #1e1e1e;
            color: #ddd;
            padding: 15px;
            border-radius: 5px;
            max-height: 500px;
            overflow-y: auto;
            font-family: monospace;
            margin-top: 20px;
        }
        .status-running {
            color: #28a745;
        }
        .status-stopped {
            color: #dc3545;
        }
    </style>
</head>
<body class="dark-theme">
    <div class="container mt-4">
        <h1>Exchange Price Monitor</h1>
        <p class="lead">
            This tool monitors cryptocurrency prices directly from your selected exchange and executes trades based on price movements.
        </p>
        
        <div class="card bg-dark text-light mb-4">
            <div class="card-header">
                <h5>Monitor Status</h5>
            </div>
            <div class="card-body">
                <p>
                    Status: 
                    <?php if ($isRunning): ?>
                        <span class="status-running"><i class="fas fa-circle"></i> Running</span>
                    <?php else: ?>
                        <span class="status-stopped"><i class="fas fa-circle"></i> Stopped</span>
                    <?php endif; ?>
                </p>
                
                <?php if (!empty($processDetails)): ?>
                <div class="mb-3">
                    <h6>Process Details:</h6>
                    <pre class="bg-dark text-light p-2"><?php echo htmlspecialchars(implode("\n", $processDetails)); ?></pre>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($status)): ?>
                <div class="alert alert-info">
                    <?php echo $status; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" class="mb-3">
                    <div class="form-group mb-3">
                        <label for="exchange">Select Exchange:</label>
                        <select name="exchange" id="exchange" class="form-control bg-dark text-light">
                            <option value="bitvavo" <?php echo $exchange === 'bitvavo' ? 'selected' : ''; ?>>Bitvavo</option>
                        </select>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="action" value="start" class="btn btn-success">
                            <i class="fas fa-play"></i> Start Monitor
                        </button>
                        <button type="submit" name="action" value="stop" class="btn btn-danger">
                            <i class="fas fa-stop"></i> Stop Monitor
                        </button>
                        <button type="submit" name="action" value="logs" class="btn btn-info">
                            <i class="fas fa-file-alt"></i> View Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card bg-dark text-light mb-4">
            <div class="card-header">
                <h3>Strategy</h3>
            </div>
            <ul>
                <li><h4>search for new coins (age >24h)</h4></li>
                <li><h4>buy when a coin has marketcap above 1,5M, and volume (24h) above 1,5M</h4></li>
                <li><h4>price update interval: 5 seconds</h4></li>
                <li><h4>sell when 30s below highest price</h4></li>
            </ul>
        </div>
        
        <?php if (!empty($output)): ?>
        <div class="card bg-dark text-light">
            <div class="card-header">
                <h5>Log Output</h5>
            </div>
            <div class="card-body">
                <div class="log-container">
                    <?php echo $output; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
