<?php
// Debug script to check API response
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $data = fetchFromCMC();
    echo "<pre>";
    print_r($data);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
