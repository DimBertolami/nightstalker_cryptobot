<?php
/**
 * Get Exchanges API Endpoint
 * Returns all configured exchanges
 */

// Set JSON content type header
header('Content-Type: application/json');

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

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
exit;
