<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/SwapDataSourceInterface.php';
require_once __DIR__ . '/includes/JupiterDataSource.php';

$jupiter = new NS\DataSources\JupiterDataSource();

try {
    // Test connection
    echo "Testing connection...\n";
    if ($jupiter->testConnection()) {
        echo "✓ Connection successful\n\n";
        
        // Get quote
        echo "Getting SOL to USDC quote...\n";
        $quote = $jupiter->getSwapQuote(
            'So11111111111111111111111111111111111111112',
            'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            1000000
        );
        
        echo "✓ Quote received\n";
        echo "- Input: 1 SOL\n";
        echo "- Output: ".($quote['outAmount']/1000000)." USDC\n";
        echo "- Route: ".count($quote['routePlan'])." steps\n";
    } else {
        echo "✗ Connection failed\n";
    }
} catch (\Exception $e) {
    echo "\nERROR: ".$e->getMessage()."\n";
}
