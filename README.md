some screenshots first: 
![image](https://github.com/user-attachments/assets/be514f07-99ba-4b05-8cfc-21067b65f53d)
![image](https://github.com/user-attachments/assets/a1dd67b0-9c64-4869-8a28-926db70a583c)
![image](https://github.com/user-attachments/assets/48d13133-ee2b-4bea-bd43-cb72953c66c3)
![image](https://github.com/user-attachments/assets/1286c3e1-01d1-4c4e-b3d0-7db383472a5e)

**SETTINGS:**
<BR>
![image](https://github.com/user-attachments/assets/fafd54e8-e5c7-4c75-a385-0cc2973fbe40)
![image](https://github.com/user-attachments/assets/06b439f9-86de-4eb9-b4b5-fd1fda9d83d5)
![image](https://github.com/user-attachments/assets/b11a7437-acfd-472c-8247-0a5accd4d5ce)
![image](https://github.com/user-attachments/assets/7040e350-f120-438d-ada9-ad4fb8137aaa)



# Night Stalker - Cryptocurrency Trading Bot

A comprehensive cryptocurrency trading platform with volume spike detection, multi-exchange support, and automated trading capabilities.

## Table of Contents

- [System Overview](#system-overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [General Settings](#general-settings)
  - [Exchange Configuration](#exchange-configuration)
  - [Wallet Integration](#wallet-integration)
- [Architecture](#architecture)
- [API Documentation](#api-documentation)
- [Usage Guide](#usage-guide)
- [Scheduled Tasks](#scheduled-tasks)
- [Troubleshooting](#troubleshooting)
- [Security Considerations](#security-considerations)
- [License](#license)

## System Overview

Night Stalker is an advanced cryptocurrency trading platform designed to detect volume spikes, monitor market trends, and execute trades across multiple exchanges. The system integrates with various cryptocurrency exchanges through the CCXT library and supports Phantom Wallet for Solana-based transactions.

## Features

- **Multi-Exchange Support**: Integration with multiple cryptocurrency exchanges via CCXT
- **Volume Spike Detection**: Automated detection of significant volume increases
- **Phantom Wallet Integration**: Direct interaction with Solana blockchain
- **Automated Trading**: Configurable buy/sell strategies based on market conditions
- **Real-time Monitoring**: Dashboard with live updates of coin prices and volumes
- **Customizable Parameters**: Adjustable trading parameters and thresholds
- **Exchange Management**: Add, edit, test, and delete exchange configurations
- **Responsive UI**: Mobile-friendly interface built with Bootstrap

## System Requirements

- XAMPP installed at `/opt/lampp` or equivalent web server setup
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer for dependency management
- Internet connection for API calls
- Modern web browser with JavaScript enabled

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone https://github.com/yourusername/night-stalker.git /opt/lampp/htdocs/NS
   ```

2. Install dependencies using Composer:
   ```bash
   cd /opt/lampp/htdocs/NS
   composer install
   ```

3. Set up the database:
   ```bash
   mysql -u root -p < database/setup.sql
   ```

4. Configure environment variables:
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

5. Set proper permissions:
   ```bash
   chmod -R 755 /opt/lampp/htdocs/NS
   chmod -R 777 /opt/lampp/htdocs/NS/logs
   chmod -R 777 /opt/lampp/htdocs/NS/config
   ```

6. Access the system at:
   ```
   http://localhost/NS/index.php
   ```

## Configuration

### General Settings

1. Navigate to the Settings page through the web interface
2. Configure general trading parameters:
   - Price change thresholds
   - Volume spike detection sensitivity
   - Auto-refresh intervals
   - Master toggle for coin fetching

### Exchange Configuration

1. In the Settings page, locate the Exchange Configuration panel
2. Default exchanges (Jupiter, Binance, Bitvavo) are pre-configured
3. Add new exchanges by clicking "Add Exchange"
4. For each exchange, provide:
   - API Key
   - API Secret
   - Additional parameters (if required)
   - Test mode toggle (for sandbox testing)
5. Test the connection before saving
6. Set your preferred default exchange

### Supported Exchanges
Currently, the system supports the following CCXT-based exchanges:
- Binance
- Kraken
- OKX
- Alpaca
- Coinbase
- MEXC
- Ascendex
- BigOne
- BingX
- Bitbns
- Bitfinex
- Bitflyer
- Bitget
- Bithumb
- Bitmart
- Bitmex
- Bitopro
- Bitrue
- Bitso
- Bitstamp
- Bitteam
- Bittrade
- Blockchaincom

### Wallet Integration

1. Configure Phantom Wallet in the Wallet Configuration panel
2. Set up connection parameters for Solana blockchain
3. Specify wallet addresses and transaction settings

## Architecture

### Directory Structure

```
/opt/lampp/htdocs/NS/
├── api/                  # API endpoints
│   ├── add-exchange.php
│   ├── delete-exchange.php
│   ├── edit-exchange.php
│   ├── get-exchanges.php
│   ├── get-supported-exchanges.php
│   ├── save-exchange-settings.php
│   └── test-exchange.php
├── assets/              # Frontend assets
│   ├── css/
│   ├── js/
│   └── images/
├── config/              # Configuration files
│   └── exchanges.json
├── includes/            # Core PHP files
│   ├── auth.php
│   ├── ccxt_integration.php
│   ├── config.php
│   ├── exchange_config.php
│   └── functions.php
├── logs/                # Log files
├── vendor/              # Composer dependencies
├── .env                 # Environment variables
├── composer.json        # Composer configuration
├── index.php            # Main entry point
├── coins.php            # Coins listing page
├── settings.php         # Settings page
├── trade.php            # Trading interface
└── README.md            # This documentation
```

### Core Components

1. **Frontend**:
   - Bootstrap-based responsive UI
   - JavaScript for dynamic interactions
   - AJAX for asynchronous data loading

2. **Backend**:
   - PHP core application logic
   - MySQL database for data storage
   - CCXT library for exchange interactions

3. **API Layer**:
   - RESTful endpoints for exchange operations
   - JSON response format
   - Error handling and validation

## API Documentation

### Exchange Management

#### Add Exchange
- **Endpoint**: `/api/add-exchange.php`
- **Method**: POST
- **Parameters**:
  ```json
  {
    "exchange_id": "binance",
    "api_key": "your_api_key",
    "api_secret": "your_api_secret",
    "test_mode": true,
    "additional_params": {}
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Exchange added successfully",
    "exchange_id": "binance",
    "exchange_name": "Binance"
  }
  ```

#### Test Exchange Connection
- **Endpoint**: `/api/test-exchange.php`
- **Method**: POST
- **Parameters**: Same as Add Exchange
- **Response**:
  ```json
  {
    "success": true,
    "message": "Connection successful",
    "markets_count": 1324
  }
  ```

#### Get Exchanges
- **Endpoint**: `/api/get-exchanges.php`
- **Method**: GET
- **Response**:
  ```json
  {
    "success": true,
    "exchanges": {
      "binance": {
        "name": "Binance",
        "enabled": true,
        "is_default": true,
        "credentials": {...}
      },
      ...
    },
    "default_exchange": "binance"
  }
  ```

## Usage Guide

### Coins Page

1. View all monitored coins with real-time price and volume data
2. Use the search function to find specific coins
3. Toggle auto-refresh to keep data updated
4. Enter trade amounts and click Buy/Sell buttons to execute trades
5. Sort columns by clicking on column headers

### Trading

1. From the coins page, enter an amount and click Buy or Sell
2. Review the trade details on the trade confirmation page
3. Confirm the transaction to execute via the selected exchange
4. View transaction history in the trades section

### Settings Management

1. Access all configuration options from the Settings page
2. Adjust trading parameters to fit your strategy
3. Configure exchange connections and API keys
4. Set up wallet integration for Solana transactions
5. Save all changes with the global Save button

## New Coin Detection System

The Night Stalker platform includes a sophisticated new coin detection system that identifies and tracks newly listed cryptocurrencies:

### Architecture

1. **Discovery**: Python script (`newcoinstracker.py`) queries CoinGecko API for newly listed coins
2. **Processing**: PHP import script (`save_new_coins.php`) processes discovery results
3. **Storage**: New coins are stored in the `coins` table with timestamps and metadata
4. **Display**: Frontend components show new coins on dashboard and coins pages

### How It Works

#### Coin Discovery Process

1. **Initial Detection**: 
   - Python script fetches the newest coins from CoinGecko
   - Uses persistent tracking to identify genuinely new coins
   - Filters by age (< 24 hours old) and volume thresholds
   - Outputs results to a temporary JSON file

2. **Data Import**:
   - PHP script processes JSON data
   - Updates `coins` table with new entries
   - Sets `date_added` timestamp and `is_trending` flags

3. **Frontend Display**:
   - `getNewCryptocurrencies()` function retrieves coins < 24h old
   - `getTrendingCoins()` function gets trending coins by volume
   - `coins.php` displays only high-value new coins (market cap and volume ≥ $1.5M)

#### Configuration Parameters

- `MAX_COIN_AGE`: Maximum age in hours for a coin to be considered "new" (default: 24)
- `MIN_VOLUME_THRESHOLD`: Minimum volume for trending status
- Filter thresholds in `coins.php` for high-value coins

## Scheduled Tasks

The system uses cron jobs for automated operations:

1. **New Coin Discovery**: Looks for newly listed coins every 30 minutes
   ```
   */30 * * * * /usr/bin/python3 /home/dim/Documenten/newcoinstracker.py --json | php /opt/lampp/htdocs/NS/crons/save_new_coins.php
   ```

2. **Coin Fetching**: Retrieves general coin data every 3 minutes
   ```
   */3 * * * * php /opt/lampp/htdocs/NS/cron/fetch_coins.php
   ```

3. **Trade Monitoring**: Checks for trading conditions every 5 minutes
   ```
   */5 * * * * php /opt/lampp/htdocs/NS/cron/monitor_trades.php
   ```

4. **Price Updates**: Updates coin prices every minute
   ```
   * * * * * php /opt/lampp/htdocs/NS/cron/update_prices.php
   ```

## Recent Fixes

### New Coin Discovery & Display Enhancement (June 2025)

Significant improvements were made to the new coin discovery, import, and frontend display functionality:

#### 1. Python New Coin Discovery Script (`newcoinstracker.py`)

- Fixed API request parameters to comply with CoinGecko's free tier requirements
- Implemented robust error handling and retry logic for API requests
- Added support for optional CoinGecko API key usage
- Introduced persistent tracking of known coins to reliably identify genuinely new coins (< 24h old)
- Improved JSON output handling by writing to a temporary file, cleanly separating logs from data
- Ensured consistent numeric types and ISO-formatted timestamps
- Added caching of coin detail queries to reduce API calls and respect rate limits

#### 2. PHP Backend Import Script (`save_new_coins.php`)

- Updated to handle the new Python script output format
- Fixed string formatting errors in logging
- Improved API key handling to only pass non-empty keys
- Enhanced database update process for coin data including proper timestamp handling
- Added detailed logging of coin age ranges and counts for debugging purposes

#### 3. Frontend Functions Fix

- Fixed `getNewCryptocurrencies()` function in `functions.php` to query the correct `coins` table instead of `cryptocurrencies`
- Updated `getTrendingCoins()` function to use the correct database table and column names
- Added runtime calculation of `age_hours` using MySQL's `TIMESTAMPDIFF()` function
- Ensured proper sorting of coins by most recently added first

#### 4. Coins Display Page Enhancement (`coins.php`)

- Updated to show only coins less than 24 hours old
- Added filtering to show only high-value coins with market cap AND volume ≥ $1.5 million
- Fixed column name references throughout the page (price → current_price, volume → volume_24h)
- Enhanced coin age display to show hours since addition
- Updated page title and header to reflect the specialized nature of the display
- Added visual indicators for new coins with highlighting

### Exchange Configuration Issues (June 2025)

The following issues were identified and fixed in the exchange configuration system:

1. **JavaScript Fixes in `assets/js/exchange-config.js`**:
   - Fixed incomplete code with missing closing brackets
   - Changed `BASE_URL` declaration from `const` to `var` to avoid duplicate declaration errors
   - Added console debug logging for AJAX calls
   - Enabled page reload after successful exchange addition

2. **PHP API Endpoint Fixes**:
   - Added missing `api_url` parameter in `api/test-exchange.php` credentials array
   - Fixed permission issues with the config directory
   - Improved error handling in exchange connection testing

3. **Frontend Fixes in `dashboard/settings.php`**:
   - Removed duplicate inclusion of exchange-config.js script
   - Added `showAlert` JavaScript function for better user feedback

These fixes resolved issues preventing successful addition of cryptocurrency exchanges like Kraken to the platform.

## Troubleshooting

- **API Connection Issues**:
  - Check `logs/api_errors.log` for detailed error messages
  - Verify API keys are correctly entered and have proper permissions
  - Ensure your IP is not blocked by the exchange

- **Database Problems**:
  - Verify database connection in `includes/config.php`
  - Check MySQL service is running: `sudo service mysql status`
  - Run database diagnostics: `php tools/db_check.php`
  - delete from coins table: `DELETE FROM coins WHERE id IN (886, 887, 888);`
  - delete from cryptocurrencies table: `DELETE FROM cryptocurrencies WHERE symbol = 'SC1';`
  - other handy commands:   `"SHOW DATABASES"` 
                            `"SHOW TABLES FROM night_stalker"` 
                            `"SELECT * FROM coins;"` 
                            `"SELECT * FROM cryptocurrencies;"`
                            `"SELECT count(*) FROM coins;"`
                            `"DESCRIBE night_stalker.cryptocurrencies"`
                            `"DESCRIBE night_stalker.coins"`

- **Scheduled Tasks**:
  - Make sure cron jobs are running: `crontab -l`
  - Check cron logs: `grep CRON /var/log/syslog`
  - Verify PHP CLI is working: `php -v`

- **Exchange Integration**:
  - Test exchange connection through the Settings page
  - Check CCXT library is installed: `composer show ccxt/ccxt`
  - Verify exchange API status on their official status page
  - Ensure config directory has proper write permissions: `chmod -R 777 /opt/lampp/htdocs/NS/config`
  - Check browser console for JavaScript errors when adding exchanges
  - Verify `api_url` parameter is included in exchange credentials

## Security Considerations

- Change the default admin password (set in `includes/auth.php`)
- Store API keys securely using environment variables
- Implement IP restrictions for admin access
- Use HTTPS for all connections
- Regularly update dependencies with `composer update`
- Monitor logs for suspicious activity
- Implement rate limiting for API endpoints
- Use the test mode when configuring new exchanges

## License

This is a custom system. All rights reserved. Unauthorized copying, modification, distribution, or use is strictly prohibited.
