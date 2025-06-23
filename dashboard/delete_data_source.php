<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['source'])) {
    echo json_encode(['success' => false, 'error' => 'Missing data source key']);
    exit;
}

$source = strtolower($data['source']);
$configFile = __DIR__ . '/../config/trading_config.json';

if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!isset($config['api_data_sources'])) {
    $config['api_data_sources'] = [];
}

if (isset($config['api_data_sources'][$source])) {
    unset($config['api_data_sources'][$source]);
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'Data source not found']);
    exit;
}
