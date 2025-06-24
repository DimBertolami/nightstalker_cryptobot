# Jupiter Terminal API Integration

## Overview
This document describes the Jupiter swap integration in the Night Stalker cryptobot system.

## Key Features
- Real-time swap quotes
- Swap execution
- Automatic failover handling
- Comprehensive logging

## Implementation Details

### Class: `JupiterDataSource`
Implements `SwapDataSourceInterface` with:
- `getSwapQuote()` - Get swap quotes
- `executeSwap()` - Execute swaps
- `testConnection()` - Verify API connectivity

### Configuration
Set these in `config.php`:
```php
define('JUPITER_API_KEY', 'your_api_key');
define('JUPITER_API_SECRET', 'your_api_secret');
```

### Usage Examples

**Get a swap quote:**
```php
$jupiter = new JupiterDataSource();
$quote = $jupiter->getSwapQuote(
    'So111...11112', // SOL
    'EPjFW...Dt1v',  // USDC
    1000000          // 1 SOL
);
```

**Execute a swap:**
```php
$result = $jupiter->executeSwap($quote);
```

**Through DataSourceManager:**
```php
$manager = new CryptoDataSourceManager();
$quote = $manager->getJupiterSwapQuote('SOL', 'USDC', 1.0);
```

## Error Handling
Errors are logged to:
- `logs/jupiter_errors.log`
- `logs/jupiter_debug.log`

## API Reference
- Base URL: `https://quote-api.jup.ag`
- Endpoints:
  - `GET /v6/quote` - Get swap quotes
  - `POST /v6/swap` - Execute swaps
