<?php
/**
 * Add Exchange API Endpoint
 * Handles adding new exchange configurations
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../includes/exchange_config.php';
require_once __DIR__ . '/../includes/ccxt_integration.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get JSON data from request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate required fields
if (!isset($data['exchange_id']) || !isset($data['api_key']) || !isset($data['api_secret'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Prepare exchange credentials
$credentials = [
    'api_key' => $data['api_key'],
    'api_secret' => $data['api_secret'],
    'test_mode' => !empty($data['test_mode']),
    'additional_params' => !empty($data['additional_params']) ? $data['additional_params'] : []
];

// Test connection before saving
$test_result = test_exchange_connection($data['exchange_id'], $credentials);

if (!$test_result['success']) {
    echo json_encode([
        'success' => false,
        'message' => 'Connection test failed: ' . $test_result['message']
    ]);
    exit;
}

// Prepare exchange config
$exchange_config = [
    'name' => ucfirst($data['exchange_id']),
    'enabled' => true,
    'is_default' => false,
    'credentials' => $credentials
];

// Save exchange configuration
$save_result = save_exchange($data['exchange_id'], $exchange_config);

if (!$save_result) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save exchange configuration'
    ]);
    exit;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Exchange added successfully',
    'exchange_id' => $data['exchange_id'],
    'exchange_name' => $exchange_config['name']
]);
