<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['source']) || !$data['source']) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid data source key']);
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
    echo json_encode(['success' => false, 'error' => 'Data source already exists']);
    exit;
}

$config['api_data_sources'][$source] = [
    'enabled' => true,
    'display_name' => $data['display_name'] ?? $source,
    'api_key' => $data['api_key'] ?? '',
    'api_secret' => $data['api_secret'] ?? '',
    'api_url' => $data['api_url'] ?? ''
];

if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write config file']);
}
