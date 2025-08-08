<?php
/**
 * Test Exchange API Endpoint
 * Tests connection to an exchange without saving configuration
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
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
    'api_url' => !empty($data['api_url']) ? $data['api_url'] : '',
    'test_mode' => !empty($data['test_mode']),
    'additional_params' => !empty($data['additional_params']) ? $data['additional_params'] : []
];

// Test connection
$test_result = test_exchange_connection($data['exchange_id'], $credentials);

// Return test result
echo json_encode($test_result);
