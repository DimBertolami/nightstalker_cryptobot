<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create a log file
$logFile = __DIR__ . '/../logs/debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Debug script started\n", FILE_APPEND);

try {
    // Include API helper functions
    require_once __DIR__ . '/../includes/api_helper.php';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - API helper included\n", FILE_APPEND);
    
    require_once __DIR__ . '/../includes/config.php';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Config included\n", FILE_APPEND);
    
    require_once __DIR__ . '/../includes/functions.php';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Functions included\n", FILE_APPEND);
    
    require_once __DIR__ . '/../includes/database.php';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database included\n", FILE_APPEND);
    
    // Test database connection
    $db = getDBConnection();
    if ($db) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection successful\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection failed\n", FILE_APPEND);
    }
    
    // Test API helper functions
    initApiEndpoint();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - API endpoint initialized\n", FILE_APPEND);
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Debug successful',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'Debug failed: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
}
