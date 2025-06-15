<?php
/**
 * Save Exchange Settings API Endpoint
 * Handles saving exchange settings from the settings form
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

// Get exchanges data
$exchanges = get_exchanges();

// Process enabled exchanges
if (isset($_POST['exchanges']) && is_array($_POST['exchanges'])) {
    foreach ($exchanges as $exchange_id => $exchange) {
        // Set enabled status based on form data
        $exchanges[$exchange_id]['enabled'] = isset($_POST['exchanges'][$exchange_id]);
    }
}

// Set default exchange
if (isset($_POST['defaultExchange']) && !empty($_POST['defaultExchange'])) {
    $default_exchange = $_POST['defaultExchange'];
    
    // Verify the exchange exists
    if (isset($exchanges[$default_exchange])) {
        // Update default exchange
        set_default_exchange($default_exchange);
    }
}

// Save all exchanges with updated enabled status
foreach ($exchanges as $exchange_id => $exchange) {
    save_exchange($exchange_id, $exchange);
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Exchange settings saved successfully'
]);
