<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';
header('Content-Type: text/plain');
echo "Testing API Connection...\n";

try {
    $data = fetchFromCMC();
    echo "Success! Retrieved " . count($data) . " coins\n";
    print_r(array_keys($data));
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    
    // Additional debugging
    if (file_exists('api_error.log')) {
        echo "\nAPI Error Log:\n";
        echo file_get_contents('api_error.log');
    }
}
