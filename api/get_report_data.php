<?php
require_once '/opt/lampp/htdocs/NS/includes/pdo_functions.php';

header('Content-Type: application/json');

$response = [
    'learning_metrics' => [],
    'trading_performance' => []
];

try {
    // Attempt to get DB connection first
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Failed to establish database connection.");
    }

    $response['learning_metrics'] = getLearningMetricsPDO();
    $response['trading_performance'] = getTradingPerformancePDO();
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    error_log("PHP API Error: " . $e->getMessage()); // Log the error
    echo json_encode(['error' => $e->getMessage()]);
}
