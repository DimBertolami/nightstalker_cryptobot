<?php
/**
 * Edit Exchange API Endpoint
 * Handles updating existing exchange configurations
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
if (!isset($data['exchange_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing exchange ID'
    ]);
    exit;
}

// Check if exchange exists
$exchange = get_exchange($data['exchange_id']);
if (!$exchange) {
    echo json_encode([
        'success' => false,
        'message' => 'Exchange not found'
    ]);
    exit;
}

// Update exchange configuration
$updated_config = $exchange;

// Update name if provided
if (isset($data['name'])) {
    $updated_config['name'] = $data['name'];
}

// Update enabled status if provided
if (isset($data['enabled'])) {
    $updated_config['enabled'] = (bool)$data['enabled'];
}

// Update credentials if provided
if (isset($data['api_key']) && isset($data['api_secret'])) {
    // Prepare exchange credentials
    $credentials = [
        'api_key' => $data['api_key'],
        'api_secret' => $data['api_secret'],
        'api_url' => isset($data['api_url']) ? $data['api_url'] : $exchange['credentials']['api_url'],
        'test_mode' => isset($data['test_mode']) ? (bool)$data['test_mode'] : $exchange['credentials']['test_mode'],
        'additional_params' => isset($data['additional_params']) ? $data['additional_params'] : $exchange['credentials']['additional_params']
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
    
    $updated_config['credentials'] = $credentials;
}

// Save updated exchange configuration
$save_result = save_exchange($data['exchange_id'], $updated_config);

if (!$save_result) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save exchange configuration'
    ]);
    exit;
}

// Set as default if requested
if (isset($data['is_default']) && $data['is_default']) {
    set_default_exchange($data['exchange_id']);
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Exchange updated successfully',
    'exchange_id' => $data['exchange_id'],
    'exchange_name' => $updated_config['name']
]);
