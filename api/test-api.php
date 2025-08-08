<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set JSON content type header
header('Content-Type: application/json');

// Simple test response
echo json_encode([
    'success' => true,
    'message' => 'API test successful',
    'timestamp' => time()
]);
