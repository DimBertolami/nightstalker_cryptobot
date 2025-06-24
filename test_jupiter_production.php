<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/JupiterDataSource.php';

// Initialize Jupiter directly
$jupiter = new NS\DataSources\JupiterDataSource();

try {
    echo "Testing Jupiter Swap API Directly:\n\n";
    
    // Test connection
    echo "- Testing connection: ";
    echo $jupiter->testConnection() ? "OK\n" : "FAILED\n";
    
    // Get quote
    echo "- Getting SOL/USDC quote: ";
    $quote = $jupiter->getSwapQuote(
        'So11111111111111111111111111111111111111112',
        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        1000000
    );
    
    echo "SUCCESS\n";
    echo "  Out amount: ".($quote['outAmount']/1000000)." USDC\n";
    echo "  Route steps: ".count($quote['routePlan'])."\n";
    
} catch (\Exception $e) {
    echo "\nERROR: ".$e->getMessage()."\n";
}
