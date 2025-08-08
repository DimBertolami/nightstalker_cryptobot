<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cmc_utils.php';

$symbol = $_GET['symbol'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$symbol || !$start || !$end) {
    echo json_encode(['error' => 'Symbol, start timestamp, and end timestamp are required.']);
    exit();
}

// Ensure timestamps are integers
$start = (int)$start;
$end = (int)$end;

$historicalData = getCMCHistoricalData($symbol, $start, $end);

if (empty($historicalData)) {
    error_log("API: No historical data found for symbol {$symbol} or API error.");
    echo json_encode(['error' => 'No historical data found or API error.']);
} else {
    echo json_encode(['success' => true, 'data' => $historicalData]);
}
?>