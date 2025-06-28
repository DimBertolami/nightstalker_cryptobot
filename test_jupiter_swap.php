<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/SwapDataSourceInterface.php';
require_once __DIR__ . '/includes/JupiterDataSource.php';

// Initialize with API key from config
$jupiter = new NS\DataSources\JupiterDataSource();

// Test connection
echo "Testing connection to Jupiter API:\n";
var_dump($jupiter->testConnection());

// Test swap quote
echo "\nGetting SOL to USDC quote:\n";
$quote = $jupiter->getSwapQuote(
    'So11111111111111111111111111111111111111112', // SOL
    'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', // USDC
    1000000 // 1 SOL
);
print_r($quote);

// Verify quote structure
echo "\nQuote validation: ";
if (isset($quote['outAmount']) && isset($quote['routePlan'])) {
    echo "VALID\n";
} else {
    echo "INVALID\n";
}
