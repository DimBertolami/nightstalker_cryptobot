<?php
// Include required files
require_once '/opt/lampp/htdocs/NS/includes/config.php';
require_once '/opt/lampp/htdocs/NS/includes/database.php';
require_once '/opt/lampp/htdocs/NS/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Please log in to access system tools.'
    ]));
}

// Define allowed tools
$allowedTools = [
    'coingecko' => 'CoinGecko Data Fetcher',
    'sync_trade_tables' => 'Trade Table Synchronizer',
    'trade_diagnostics' => 'Trade Diagnostics',
    'cmc_fetch_coins' => 'CoinMarketCap Data Fetcher',
    'trending_fetcher' => 'Crypto Trending Data Fetcher',
    'exchange_monitor' => 'Exchange Price Monitor',
    'cron_manager' => 'Cron Manager'
];

// Get requested tool
$tool = $_GET['tool'] ?? '';
if (!array_key_exists($tool, $allowedTools)) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid tool specified.'
    ]));
}

// Logs directory
$logsDir = '/opt/lampp/htdocs/NS/system-tools/logs';

// Check if logs directory exists
if (!is_dir($logsDir)) {
    die(json_encode([
        'success' => false,
        'message' => 'No logs directory found.'
    ]));
}

// Get log files for this tool
$logFiles = glob($logsDir . '/' . $tool . '_*.log');
if (empty($logFiles)) {
    die(json_encode([
        'success' => false,
        'message' => 'No logs found for this tool.'
    ]));
}

// Delete all log files for this tool
$deletedCount = 0;
foreach ($logFiles as $logFile) {
    if (unlink($logFile)) {
        $deletedCount++;
    }
}

// Return success message
echo json_encode([
    'success' => true,
    'message' => "Successfully cleared {$deletedCount} log file(s) for {$allowedTools[$tool]}.",
    'tool' => $allowedTools[$tool]
]);
?>
