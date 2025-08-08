<?php
// File: api/execute-sell.php

header('Content-Type: application/json');

// Include necessary functions and database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/pdo_functions.php'; // Contains executeSellPDO

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.'
];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    $coinId = $data['coin_id'] ?? null;
    $amount = $data['amount'] ?? null;
    $price = $data['price'] ?? null;

    if ($coinId === null || $amount === null || $price === null) {
        $response['message'] = 'Missing required parameters: coin_id, amount, or price.';
        error_log("API Error: Missing parameters for sell request. Data: " . json_encode($data));
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $response['message'] = 'Invalid amount provided.';
        error_log("API Error: Invalid amount for sell request. Amount: " . $amount);
    } elseif (!is_numeric($price) || $price <= 0) {
        $response['message'] = 'Invalid price provided.';
        error_log("API Error: Invalid price for sell request. Price: " . $price);
    } else {
        try {
            // Call the executeSellPDO function
            $sellResult = executeSellPDO($coinId, (float)$amount, (float)$price);

            if (isset($sellResult['success']) && $sellResult['success'] === true) {
                $response['success'] = true;
                $response['message'] = $sellResult['message'] ?? 'Sell order executed successfully.';
                $response['trade_id'] = $sellResult['trade_id'] ?? null;
                $response['profit_loss'] = $sellResult['profit_loss'] ?? null;
                $response['profit_percentage'] = $sellResult['profit_percentage'] ?? null;
                error_log("Sell API Success: " . json_encode($response));
            } else {
                $response['message'] = $sellResult['message'] ?? 'Failed to execute sell order.';
                error_log("Sell API Failure: " . json_encode($response));
            }
        } catch (Exception $e) {
            $response['message'] = 'Exception during sell execution: ' . $e->getMessage();
            error_log("Sell API Exception: " . $e->getMessage());
        }
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

echo json_encode($response);
