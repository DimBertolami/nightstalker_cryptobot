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

// Create logs directory if it doesn't exist
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
if (!is_dir($logsDir)) {
    die(json_encode([
        'success' => false,
        'message' => 'No logs available yet.'
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

// Sort by newest first
rsort($logFiles);

// Get the most recent log file (or specified log file)
$logFile = $logFiles[0];
if (isset($_GET['file']) && in_array($_GET['file'], $logFiles)) {
    $logFile = $_GET['file'];
}

// Read log content
$logContent = file_get_contents($logFile);

// Format log content with timestamp header
$logName = basename($logFile);
$timestamp = preg_replace('/^' . $tool . '_(\d{8})_(\d{6})\.log$/', '$1 $2', $logName);
$timestamp = date('Y-m-d H:i:s', strtotime(substr($timestamp, 0, 8) . ' ' . substr($timestamp, 9, 6)));

$formattedLog = "=== Log from {$timestamp} ===\n\n{$logContent}";

// If there are multiple logs, add navigation links
if (count($logFiles) > 1) {
    $formattedLog .= "\n\n=== Other Available Logs ===\n";
    foreach (array_slice($logFiles, 0, 5) as $file) {
        if ($file !== $logFile) {
            $name = basename($file);
            $date = preg_replace('/^' . $tool . '_(\d{8})_(\d{6})\.log$/', '$1 $2', $name);
            $date = date('Y-m-d H:i:s', strtotime(substr($date, 0, 8) . ' ' . substr($date, 9, 6)));
            $formattedLog .= "- {$date}\n";
        }
    }
    
    if (count($logFiles) > 5) {
        $formattedLog .= "- ... and " . (count($logFiles) - 5) . " more\n";
    }
}

// Return log content
echo json_encode([
    'success' => true,
    'log' => $formattedLog,
    'tool' => $allowedTools[$tool]
]);
?>
