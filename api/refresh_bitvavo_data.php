<?php
/**
 * Refresh Bitvavo Data API Endpoint
 * 
 * This script purges the coins table and then executes the Bitvavo data fetch script.
 * It's designed to be called via AJAX when the auto-refresh switch is toggled.
 */

// Set headers for AJAX response
header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Function to run a command and capture output
function runCommand($command) {
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    return [
        'output' => $output,
        'returnCode' => $returnCode
    ];
}

// Function to log refresh operations
function logRefresh($message, $success = true) {
    // Ensure logs directory exists
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/bitvavo_refresh.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'ERROR';
    $logMessage = "[$timestamp] [$status] $message\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    return $timestamp;
}

try {
    $response = [
        'success' => true,
        'messages' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Log the start of the refresh operation
    logRefresh("Starting Bitvavo data refresh operation");
    
    // Step 1: Purge the coins table
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    $db->beginTransaction();
    $db->exec("TRUNCATE TABLE coins");
    $db->commit();
    $response['messages'][] = "Coins table purged successfully";
    logRefresh("Coins table purged successfully");
    
    // Step 2: Execute the Bitvavo data fetch script
    $scriptPath = __DIR__ . '/../tools/cmc/bitvavoFromCMC4NS.py';
    
    // Check if the script exists
    if (!file_exists($scriptPath)) {
        throw new Exception("Bitvavo data fetch script not found at: $scriptPath");
    }
    
    // Execute the Python script
    $result = runCommand("python3 $scriptPath");
    
    if ($result['returnCode'] !== 0) {
        $errorMsg = "Error executing Bitvavo data fetch script. Return code: " . $result['returnCode'];
        logRefresh($errorMsg, false);
        throw new Exception($errorMsg);
    }
    
    // Log success
    $timestamp = logRefresh("Bitvavo data fetch completed successfully. " . count($result['output']) . " lines of output.");
    
    $response['messages'][] = "Bitvavo data fetch completed successfully";
    $response['scriptOutput'] = $result['output'];
    $response['refreshTimestamp'] = $timestamp;
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    logRefresh("Error: " . $e->getMessage(), false);
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    exit(1);
}
?>
