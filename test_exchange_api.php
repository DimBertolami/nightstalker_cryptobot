<?php
/**
 * Test script to directly call the exchange API endpoints
 */

// Include required files
require_once __DIR__ . '/includes/exchange_config.php';
require_once __DIR__ . '/includes/ccxt_integration.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Test data for Kraken
$test_data = [
    'exchange_id' => 'kraken',
    'api_key' => 'dmqjTdc9A25Pd83sk9kz/M+Z/3Zu9+kSRKoGR6o7IuKzBqcWEvHIPdVl',
    'api_secret' => 'gzDfB+URG1zE0vo0kZANmOydXSwIS9BxDz6/WAAtZ6X3m3W6jc/gIugLZJNkyHWj97Uo9cGsf6TOMWXPPpMurg==',
    'api_url' => 'https://api.kraken.com',
    'test_mode' => true,
    'additional_params' => []
];

// Prepare credentials
$credentials = [
    'api_key' => $test_data['api_key'],
    'api_secret' => $test_data['api_secret'],
    'api_url' => $test_data['api_url'],
    'test_mode' => $test_data['test_mode'],
    'additional_params' => $test_data['additional_params']
];

// Test connection
echo "Testing connection to " . $test_data['exchange_id'] . "...\n";
$test_result = test_exchange_connection($test_data['exchange_id'], $credentials);
echo "Test result: " . json_encode($test_result, JSON_PRETTY_PRINT) . "\n\n";

// If connection successful, try to save
if ($test_result['success']) {
    echo "Connection successful, trying to save exchange...\n";
    
    // Prepare exchange config
    $exchange_config = [
        'name' => ucfirst($test_data['exchange_id']),
        'enabled' => true,
        'is_default' => false,
        'credentials' => $credentials
    ];
    
    // Check if config directory is writable
    $config_dir = __DIR__ . '/config';
    echo "Config directory writable: " . (is_writable($config_dir) ? 'Yes' : 'No') . "\n";
    
    // Check if exchanges.json exists and is writable
    $exchanges_file = $config_dir . '/exchanges.json';
    echo "Exchanges file exists: " . (file_exists($exchanges_file) ? 'Yes' : 'No') . "\n";
    if (file_exists($exchanges_file)) {
        echo "Exchanges file writable: " . (is_writable($exchanges_file) ? 'Yes' : 'No') . "\n";
    }
    
    // Try to save
    $save_result = save_exchange($test_data['exchange_id'], $exchange_config);
    echo "Save result: " . ($save_result ? 'Success' : 'Failed') . "\n";
    
    if (!$save_result) {
        echo "Error: " . json_encode(error_get_last()) . "\n";
    }
} else {
    echo "Connection test failed.\n";
}
?>
