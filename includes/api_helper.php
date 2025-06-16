<?php
/**
 * API Helper Functions
 * This file contains helper functions for API endpoints to ensure clean JSON responses
 */

/**
 * Initialize an API endpoint with proper headers
 * Call this at the beginning of any API file
 */
function initApiEndpoint() {
    // Turn off error display for JSON API
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set JSON content type header
    header('Content-Type: application/json');
}

/**
 * Send a JSON response and end the script
 * @param array $data The data to send as JSON
 */
function sendJsonResponse($data) {
    // Set JSON content type header
    header('Content-Type: application/json');
    
    // Send JSON response
    echo json_encode($data);
    exit();
}

/**
 * Send a JSON error response and end the script
 * @param string $message Error message
 * @param int $code HTTP status code
 */
function sendJsonError($message, $code = 400) {
    // Set HTTP status code
    http_response_code($code);
    
    // Send error response
    sendJsonResponse([
        'success' => false,
        'message' => $message
    ]);
}
