<?php
// Prevent any output before our response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer all output to prevent headers already sent errors
ob_start();

try {
    // Include required files
    require_once '/opt/lampp/htdocs/NS/includes/config.php';
    require_once '/opt/lampp/htdocs/NS/includes/database.php';
    require_once '/opt/lampp/htdocs/NS/includes/functions.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in to access system tools.');
    }
    
    // Define allowed tools
    $allowedTools = [
        'coingecko' => '/opt/lampp/htdocs/NS/tools/fetch_coingecko_coins.php',
        'sync_trade_tables' => '/opt/lampp/htdocs/NS/tools/sync_trade_tables.php',
        'trade_diagnostics' => '/opt/lampp/htdocs/NS/tools/trade_diagnostics.php',
        'cmc_fetch_coins' => '/opt/lampp/htdocs/NS/tools/fetch_cmc_coins.php',
        'trending_fetcher' => '/opt/lampp/htdocs/NS/crypto_sources/crypto_trending_fetcher.py',
        'cron_manager' => '/opt/lampp/htdocs/NS/tools/cron_manager_tool.php',
        'exchange_monitor' => '/opt/lampp/htdocs/NS/system-tools/run_exchange_monitor.php'
    ];
    
    // Get requested tool
    $tool = $_GET['tool'] ?? '';
    if (!array_key_exists($tool, $allowedTools)) {
        throw new Exception('Invalid tool specified. (' . $tool . ')');
    }
    
    // Create logs directory if it doesn't exist
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    // Log file for this execution
    $logFile = $logsDir . '/' . $tool . '_' . date('Ymd_His') . '.log';
    
    // Execute the script and capture output
    ob_start();
    
    // Include the script
    require $allowedTools[$tool];
    
    // Get output
    $scriptOutput = ob_get_clean();
    
    // Save output to log file
    file_put_contents($logFile, $scriptOutput);
    
    // Clear any previous output
    ob_clean();
    
    // Set proper content type
    header('Content-Type: application/json');
    
    // Return success result
    echo json_encode([
        'success' => true,
        'message' => 'Script executed successfully (' . $tool . ')',
        'output' => $scriptOutput,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Throwable $e) {
    // Clear any previous output
    ob_clean();
    
    // Log the error
    error_log("System Tools Error: " . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' (Tool: ' . $tool . ')');
    
    // Set proper content type
    header('Content-Type: application/json');
    
    // Return error result
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'output' => 'Error details: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// End output buffering and flush
ob_end_flush();

