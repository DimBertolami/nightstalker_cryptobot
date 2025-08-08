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
    'sync_trade_tables' => '/opt/lampp/htdocs/NS/tools/sync_trade_tables.php',
    'trade_diagnostics' => '/opt/lampp/htdocs/NS/tools/trade_diagnostics.php',
    'price_history_diagnostics' => '/opt/lampp/htdocs/NS/tools/debug_price_history.php',
    'coins_table__diagnostics' => '/opt/lampp/htdocs/NS/debug_coins_table.php',
    'delete_all_coins' => '/opt/lampp/htdocs/NS/delete_coins.php',
    'delete_all_price_history' => '/opt/lampp/htdocs/NS/system-tools/truncate_price_history_table.sh',
    'delete_coin_apex_prices' => '/opt/lampp/htdocs/NS/system-tools/empty_coin_apex_prices.php',
    'cmc_fetch_bitvavo_coins' => '/opt/lampp/htdocs/NS/crons/bitvavoFromCMC4NS.py',
    'update_prices_bitvavo_native' => '/opt/lampp/htdocs/NS/crons/update_prices_bitvavo_native.py',
    'cmc_fetch_binance_coins' => '/opt/lampp/htdocs/NS/crons/binanceFromCMC4NS.py',
    'trending_fetcher' => '/opt/lampp/htdocs/NS/crypto_sources/crypto_trending_fetcher.py',
    'cron_manager' => '/opt/lampp/htdocs/NS/tools/cron_manager_tool.php',
    'price_history' => '/opt/lampp/htdocs/NS/system-tools/vph.php',
    'export_sensitive_data' => '/opt/lampp/htdocs/NS/export_sensitive_data.sh',
    'log_reader' => '/opt/lampp/htdocs/NS/tools/log_reader.sh',
    'fix_portfolio_coin_id' => '/opt/lampp/htdocs/NS/system-tools/fix_portfolio_coin_id.php',
    'sync_portfolio_to_cryptocurrencies' => '/opt/lampp/htdocs/NS/crons/sync_portfolio_to_cryptocurrencies.php'
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

// Filter log content for entries within the last 3 hours
$filteredLogContent = [];
$yesterday = strtotime('yesterday'); // Start of yesterday
$logLines = explode("\n", $logContent);
foreach ($logLines as $line) {
    // Attempt to extract timestamp from the beginning of the line
    // This assumes log lines start with a parseable timestamp, e.g., "YYYY-MM-DD HH:MM:SS - Message"
    $timestampString = substr($line, 0, 19); // Assuming YYYY-MM-DD HH:MM:SS format
    $lineTimestamp = strtotime($timestampString);

    // If a valid timestamp is found and it's from yesterday or today, or if no timestamp is found (include by default)
    if ($lineTimestamp === false || $lineTimestamp >= $yesterday) {
        $filteredLogContent[] = $line;
    }
}
$logContent = implode("\n", $filteredLogContent);

$logLines = explode("\n", $logContent);
foreach ($logLines as $line) {
    // Attempt to extract timestamp from the beginning of the line
    // This assumes log lines start with a parseable timestamp, e.g., "YYYY-MM-DD HH:MM:SS - Message"
    $timestampString = substr($line, 0, 19); // Assuming YYYY-MM-DD HH:MM:SS format
    $lineTimestamp = strtotime($timestampString);

    // If a valid timestamp is found and it's within the last 3 hours, or if no timestamp is found (include by default)
    if ($lineTimestamp === false || $lineTimestamp >= $threeHoursAgo) {
        $filteredLogContent[] = $line;
    }
}
$logContent = implode("\n", $filteredLogContent);

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
