<?php
/**
 * Get Exchanges API Endpoint
 * Returns all configured exchanges
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../includes/exchange_config.php';

// Get all exchanges
$exchanges = get_exchanges();

// Return exchanges list
echo json_encode([
    'success' => true,
    'exchanges' => $exchanges,
    'default_exchange' => get_default_exchange()
]);
