<?php
/**
 * Delete Exchange API Endpoint
 * Handles deleting exchange configurations
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../includes/exchange_config.php';

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

// Delete exchange configuration
$delete_result = delete_exchange($data['exchange_id']);

if (!$delete_result) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete exchange configuration'
    ]);
    exit;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Exchange deleted successfully',
    'exchange_id' => $data['exchange_id']
]);
