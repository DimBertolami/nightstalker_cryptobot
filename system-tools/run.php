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
    require_once '/opt/lampp/htdocs/NS/includes/pdo_functions.php';
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in to access system tools.');
    }
    
    // Define allowed tools
    $allowedTools = [
        'sync_trade_tables' => '/opt/lampp/htdocs/NS/tools/sync_trade_tables.php',
        'trade_diagnostics' => '/opt/lampp/htdocs/NS/tools/trade_diagnostics.php',
        'delete_all_coins' => '/opt/lampp/htdocs/NS/delete_coins.php',
        'cmc_fetch_bitvavo_coins' => '/opt/lampp/htdocs/NS/crons/bitvavoFromCMC4NS.py',
        'cmc_fetch_binance_coins' => '/opt/lampp/htdocs/NS/crons/binanceFromCMC4NS.py',
        'trending_fetcher' => '/opt/lampp/htdocs/NS/crypto_sources/crypto_trending_fetcher.py',
        'cron_manager' => '/opt/lampp/htdocs/NS/tools/cron_manager_tool.php',
        'export_sensitive_data' => '/opt/lampp/htdocs/NS/export_sensitive_data.sh',
        'log_reader' => '/opt/lampp/htdocs/NS/tools/log_reader.sh',
        'sync_portfolio_to_cryptocurrencies' => '/opt/lampp/htdocs/NS/crons/sync_portfolio_to_cryptocurrencies.php'
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
    
    // Check file extension to determine execution method
    $fileExtension = pathinfo($allowedTools[$tool], PATHINFO_EXTENSION);
    
    if ($fileExtension === 'sh') {
        // Execute shell script
        $command = 'bash ' . escapeshellarg($allowedTools[$tool]);
        if (isset($_GET['dry_run']) && $_GET['dry_run'] === 'true') {
            $command .= ' --dry-run';
        }
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        $scriptOutput = implode("\n", $output);
        
        if ($returnCode !== 0) {
            throw new Exception("Shell script execution failed with code $returnCode");
        }
    } elseif ($fileExtension === 'py') {
        // Execute Python script
        $command = 'python3 ' . escapeshellarg($allowedTools[$tool]);
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        $scriptOutput = implode("\n", $output);
        // Convert newlines to <br> for HTML display
        $scriptOutput = nl2br(htmlspecialchars($scriptOutput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        
        if ($returnCode !== 0) {
            // Include command and output in exception for debugging
            $errorMessage = "Python script execution failed with code $returnCode. Command: $command. Output: " . $scriptOutput;
            throw new Exception($errorMessage);
        }
    } else {
        // Include PHP script
        require $allowedTools[$tool];
        $scriptOutput = ob_get_clean();
    }
    
    // Save output to log file
    $bytesWritten = file_put_contents($logFile, $scriptOutput);
    if ($bytesWritten === false) {
        throw new Exception("Failed to write log file: $logFile");
    }
    error_log("Log file written: $logFile ($bytesWritten bytes)");
    
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
