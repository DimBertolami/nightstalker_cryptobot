<?php
// test.php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/functions.php';

header('Content-Type: text/plain');

try {
    $data = fetchFromCMC();
    echo "API Success!\n";
    foreach ($data as $symbol => $values) {
        printf("%s: $%.2f (%.2f%%)\n", 
               $symbol, 
               $values['price'], 
               $values['change']);
    }
} catch (Exception $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    echo "Check logs/api_response.log for details\n";
}
