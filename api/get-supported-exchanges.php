<?php
/**
 * Get Supported Exchanges API Endpoint
 * Returns all exchanges supported by CCXT
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../includes/ccxt_integration.php';

// Get supported exchanges
$result = get_supported_exchanges();

// Return exchanges list
echo json_encode($result);
