some ***new*** screenshots first: 
<img width="1342" height="913" alt="image" src="https://github.com/user-attachments/assets/cf7dbc2c-d3fd-4162-b63c-e3a68fc8a093" />
<img width="1313" height="897" alt="image" src="https://github.com/user-attachments/assets/2c650ae3-24c9-4129-bbe0-1869122db74d" />
<img width="1336" height="922" alt="image" src="https://github.com/user-attachments/assets/319ef98c-e945-4896-9ff8-540859412360" />
<img width="1363" height="832" alt="image" src="https://github.com/user-attachments/assets/ddc838de-16e1-416b-a084-08dbb87ae333" />
<img width="1359" height="908" alt="image" src="https://github.com/user-attachments/assets/36e98fde-f9d8-4779-ab03-3d5fae2e5bdc" />
<img width="1337" height="922" alt="image" src="https://github.com/user-attachments/assets/bf155462-b254-4614-a7fe-424b03e65bc1" />
<img width="1329" height="877" alt="image" src="https://github.com/user-attachments/assets/8950a23a-efd6-4e0f-b616-39c3619f959a" />
<img width="1323" height="435" alt="image" src="https://github.com/user-attachments/assets/c3563cdd-931a-42e6-9dc6-a825d7c8378d" />
<img width="1312" height="902" alt="image" src="https://github.com/user-attachments/assets/281139b3-9e25-42ab-a1e6-4042254d7d3b" />
<img width="1275" height="922" alt="image" src="https://github.com/user-attachments/assets/fdf8fd28-e656-427a-8675-76849fa11bec" />
<img width="1235" height="924" alt="image" src="https://github.com/user-attachments/assets/9f4616fa-0df3-4674-9d63-2500eb750059" />
<img width="1280" height="741" alt="image" src="https://github.com/user-attachments/assets/2b411e8c-785c-4d4c-8dd3-5df22e76dbf4" />
<img width="1906" height="661" alt="image" src="https://github.com/user-attachments/assets/b22f38ce-7478-49e0-8e2f-fff9960923f3" />
<img width="1900" height="772" alt="image" src="https://github.com/user-attachments/assets/c7e792e5-dc39-423d-a679-1250c560e79d" />
<img width="924" height="432" alt="image" src="https://github.com/user-attachments/assets/6730d788-96ae-4e33-87fc-638deaf88fdf" />
<img width="1847" height="267" alt="image" src="https://github.com/user-attachments/assets/4c266747-ac1e-45e4-8deb-2b0c886f0fef" />






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
                             --------------------------------------------
                             |keeping track of price updates and history|
                             --------------------------------------------
  2 little scripts.. 
  Price update from bitvavo API: /opt/lampp/htdocs/NS/includes/bitvavo.py
  from database: /opt/lampp/htdocs/NS/assets/js/portfolio-price-updater.js
  together they act like my buddy Danny, sometimes he was awake for three straight days
  refreshing his phone on a particular page (and no it's not Pornhub, that would be me)
  but after those three days, he would appear to transition back into his human form and
  fall asleep.. Sometimes this was no problem, but there were times that he lost all of 
  his invested money. This is my solution to his problem. he can sleep on both ears now. 
  I also need my beauty sleep, although i doubt that my beauty will benefit from it..  
  
  1. Backend Script: bitvavo.py

  updated the script to poll for prices every 3 seconds. It will run indefinitely until it detects that there are
  no more coins in the portfolio, at which point it will log a message and exit gracefully.

  Here is the code used to update the file:
```
 ╭─────────────────────────────────────────────────────────────────────────────────────────────────────────────╮
 │ ✔                       includes/bitvavo.py                                                                 │
 │                                                                                                             │
 │    66         def log_and_save_portfolio_prices(self):                                                      │
 │    67             symbols_to_track = get_portfolio_symbols()                                                │
 │    68             if not symbols_to_track:                                                                  │
 │    69                 script_logger.warning("No symbols to track from portfolio. Exiting.")                 │
 │    70                 return                                                                                │
 │    69                 script_logger.warning("No symbols to track from portfolio.")                          │
 │    70                 return False                                                                          │
 │    71                                                                                                       │
 │    72             response = self.bitvavo_engine.ticker24h({})                                              │
 │    73             prices = {item['market']: item['bid'] for item in response if 'bid' in item}              │
 │    ═══════════════════════════════════════════════════════════════════════════════════════════════════════  │
 │    103            except Exception as e:                                                                    │
 │    104                script_logger.error(f"An error occurred during database operation: {e}")              │
 │    105                                                                                                      │
 │    106            return True                                                                               │
 │    107                                                                                                      │
 │    108    if __name__ == '__main__':                                                                        │
 │    109        script_logger.info("--- Script starting ---")                                                 │
 │    110        bvavo = BitvavoImplementation()                                                               │
 │    109        bvavo.log_and_save_portfolio_prices()                                                         │
 │    111        while True:                                                                                   │
 │    112            if not bvavo.log_and_save_portfolio_prices():                                             │
 │    113                script_logger.info("No portfolio coins found. Shutting down.")                        │
 │    114                break                                                                                 │
 │    115            time.sleep(3)                                                                             │
 │    116        script_logger.info("--- Script finished ---")                                                 │
 ╰─────────────────────────────────────────────────────────────────────────────────────────────────────────────╯
```
✦ 2. Triggering the Scripts

  To ensure the scripts are triggered correctly, I'll need to modify the part of the application that will handle handle coin
  purchases. After a purchase is successfully completed, you should execute a shell command to launch both the backend
   and frontend scripts.

  Here's how:

   1. Backend: The bitvavo.py script should be run in the background (python3 /opt/lampp/htdocs/NS/includes/bitvavo.py)
      This will start the script and allow it to run independently from the main application.
   2. Frontend: The portfolio-price-updater.js script is already set up to start polling when the document is ready, 
      As long as it's included in your portfolio page, it will start automatically.
  
   I modified the executeBuyPDO function in includes/pdo_functions.php to trigger the bitvavo.py script on the first purchase. A check was implemented to determine if the portfolio is empty before the trade is executed, and if it
   is, the script is run in the background after the purchase is complete.
*********************************done***************************************************************************
**  To ensure the scripts only run when there are coins in the portfolio, i should add a check to my purchase logic.
**  Before launching the scripts, i could query the database to see if there are any coins in the portfolio. If there
**  are, launch the scripts. If not, skip.
**  This approach ensures the scripts are only running when needed, and they'll automatically stop
**  when the portfolio is empty.
*********************************done***************************************************************************

### Real-time Price Monitoring and Portfolio Management Architecture

The application's real-time price monitoring and portfolio management system is a sophisticated interplay between frontend JavaScript, backend PHP, and a persistent Python script.

*   **Frontend Price Display (`assets/js/portfolio-price-updater.js` and `assets/js/coins.js`):**
    *   The `portfolio-price-updater.js` script is responsible for client-side polling to update displayed prices and potential profits for portfolio items. It operates by periodically fetching data from a PHP API endpoint.
    *   Crucially, this script's execution is orchestrated by `assets/js/coins.js`. The `coins.js` script dynamically renders the portfolio items on the page. To prevent race conditions and ensure accurate data, `coins.js` now explicitly calls `window.startPortfolioPriceUpdaterPolling()` (from `portfolio-price-updater.js`) *after* the portfolio HTML elements have been fully loaded and rendered into the DOM.
    *   The `portfolio-price-updater.js` script is designed to gracefully stop its polling interval and log a message to the browser console if it detects that the portfolio has become empty, preventing unnecessary network requests.

*   **Backend Price Update Mechanism (`includes/pdo_functions.php` and `includes/bitvavo_price_udater.py`):**
    *   The core logic for triggering backend price updates resides within the `executeBuyPDO` function in `includes/pdo_functions.php`. This PHP function is invoked whenever a coin purchase is successfully processed and added to the user's portfolio.
    *   Upon a successful purchase, `executeBuyPDO` launches a dedicated Python script, `/opt/lampp/htdocs/NS/includes/bitvavo_price_udater.py`, as a background process. This is achieved using PHP's `exec()` function, with the Python interpreter explicitly specified (`/usr/bin/python3`) to ensure it's found within the web server's environment. The script's standard output and error are redirected to `/dev/null` as its primary logging is handled internally.
    *   To prevent multiple instances of the price updater from running concurrently (which would lead to redundant API calls and database writes), `executeBuyPDO` incorporates a `pgrep` check. Before launching a new instance, it uses `/usr/bin/pgrep -f bitvavo_price_udater.py` to determine if the script is already active. If an existing process is found, a new instance is not launched, and a message is logged to the Apache error log.
    *   The `bitvavo_price_udater.py` script itself is a persistent Python process that continuously fetches real-time price data from the Bitvavo API (or other configured sources) for coins present in the user's portfolio. It then updates the `price_history` table in the MySQL database.
    *   This Python script is designed to run indefinitely as long as there are coins in the portfolio. It includes internal logic to detect an empty portfolio and will gracefully terminate itself, logging a "stopped" message to its dedicated log file.

*   **Logging and Error Handling:**
    *   Frontend JavaScript logs (from `portfolio-price-updater.js` and `coins.js`) are directed to the browser's developer console, providing immediate feedback on script status and potential issues.
    *   Backend Python script logs (from `bitvavo_price_udater.py`) are managed by Python's `logging` module and written to specific files within the `/opt/lampp/htdocs/NS/logs/` directory (e.g., `bitvavo_script.log` for general script status, `price_updates.log` for detailed price data). This ensures persistent records of backend operations.
    *   PHP's `error_log` is utilized to capture diagnostic messages related to the launching of the Python script, including `exec` command details, return codes, and any output from `stderr` if not redirected.

This architecture ensures a robust, self-managing system for real-time cryptocurrency price monitoring, where frontend display is synchronized with backend data updates, and resource usage is optimized by preventing redundant background processes.
### Directory Structure

```
drwxr-xr-x dim     ]  .
├── [drwxr-xr-x dim     ]  api
│   ├── [drwxr-xr-x dim     ]  integrations
│   ├── [drwxr-xr-x dim     ]  security
│   ├── [drwxr-xr-x dim     ]  system
│   └── [drwxr-xr-x dim     ]  trading
├── [drwxr-xr-x dim     ]  assets
│   ├── [drwxr-xr-x dim     ]  css
│   ├── [drwxr-xr-x dim     ]  fonts
│   ├── [drwxr-xr-x dim     ]  images
│   │   ├── [drwxr-xr-x dim     ]  exchanges
│   │   ├── [drwxr-xr-x dim     ]  tutorial
│   │   └── [drwxrwxrwx dim     ]  wallets
│   ├── [drwxr-xr-x dim     ]  img
│   │   └── [drwxr-xr-x dim     ]  crypto-icons
│   └── [drwxr-xr-x dim     ]  js
│       └── [drwxr-xr-x dim     ]  components
├── [drwxr-xr-x dim     ]  backups
│   └── [drwxr-xr-x dim     ]  backup_20250713_105555
│       ├── [drwxr-xr-x dim     ]  crons
│       │   └── [drwxr-xr-x dim     ]  screen -dmS price_monitor 
│       │       └── [drwxr-xr-x dim     ]  opt
│       │           └── [drwxr-xr-x dim     ]  lampp
│       │               └── [drwxr-xr-x dim     ]  htdocs
│       │                   └── [drwxr-xr-x dim     ]  NS
│       │                       └── [drwxr-xr-x dim     ]  crons
│       ├── [drwxr-xr-x dim     ]  dashboard
│       └── [drwxr-xr-x dim     ]  includes
├── [drwxr-xr-x daemon  ]  cache
├── [drwxrwxrwx www-data]  config
├── [drwxrwxr-x www-data]  crons
│   └── [drwxrwxr-x www-data]  screen -dmS price_monitor 
│       └── [drwxrwxr-x www-data]  opt
│           └── [drwxrwxr-x www-data]  lampp
│               └── [drwxrwxr-x www-data]  htdocs
│                   └── [drwxrwxr-x www-data]  NS
│                       └── [drwxrwxr-x www-data]  crons
├── [drwxr-xr-x dim     ]  crypto_sources
│   └── [drwxr-xr-x dim     ]  tst
│       └── [drwxr-xr-x dim     ]  __pycache__
├── [drwxr-xr-x dim     ]  dashboard
│   └── [drwxr-xr-x www-data]  cache
├── [drwxr-xr-x dim     ]  data
│   ├── [drwxr-xr-x dim     ]  csv
│   ├── [drwxr-xr-x dim     ]  mysql
│   ├── [drwxr-xr-x dim     ]  performance_schema
│   └── [drwxr-xr-x dim     ]  test
├── [drwxr-xr-x dim     ]  database
│   └── [drwxr-xr-x dim     ]  migrations
├── [drwxr-xr-x dim     ]  docs
├── [drwxr-xr-x dim     ]  includes
│   ├── [drwxr-xr-x dim     ]  cachedir
│   │   └── [drwxr-xr-x dim     ]  joblib
│   │       └── [drwxr-xr-x dim     ]  xgboost
│   │           └── [drwxr-xr-x dim     ]  testing
│   │               └── [drwxr-xr-x dim     ]  data
│   │                   ├── [drwxr-xr-x dim     ]  get_ames_housing
│   │                   ├── [drwxr-xr-x dim     ]  get_california_housing
│   │                   ├── [drwxr-xr-x dim     ]  get_cancer
│   │                   ├── [drwxr-xr-x dim     ]  get_digits
│   │                   ├── [drwxr-xr-x dim     ]  get_mq2008
│   │                   ├── [drwxr-xr-x dim     ]  get_sparse
│   │                   └── [drwxr-xr-x dim     ]  make_sparse_regression
│   ├── [drwxr-xr-x dim     ]  DataSources
│   ├── [drwxr-xr-x dim     ]  exchanges
│   ├── [drwxr-xr-x dim     ]  __pycache__
│   └── [drwxr-xr-x dim     ]  strategies
├── [drwxr-xr-x dim     ]  install
├── [drwxr-xr-x dim     ]  js
├── [drwxrwxrwx daemon  ]  logs
├── [drwxr-xr-x dim     ]  models
├── [drwxr-xr-x dim     ]  scripts
├── [drwxr-xr-x dim     ]  sql
├── [drwxr-xr-x daemon  ]  system-tools
│   └── [drwxrwxr-x daemon  ]  logs
├── [drwxr-xr-x dim     ]  tools
│   ├── [drwxr-xr-x dim     ]  backup_csv
│   └── [drwxr-xr-x dim     ]  cmc
│       ├── [drwxr-xr-x dim     ]  __pycache__
│       └── [drwxr-xr-x dim     ]  venv
│           ├── [drwxr-xr-x dim     ]  bin
│           ├── [drwxr-xr-x dim     ]  include
│           │   └── [drwxr-xr-x dim     ]  python3.11
│           └── [drwxr-xr-x dim     ]  lib
│               └── [drwxr-xr-x dim     ]  python3.11
│                   └── [drwxr-xr-x dim     ]  site-packages
│                       ├── [drwxr-xr-x dim     ]  git_filter_repo-2.47.0.dist-info
│                       └── [drwxr-xr-x dim     ]  __pycache__
└── [drwxr-xr-x dim     ]  vendor
    ├── [drwxr-xr-x dim     ]  bin
    ├── [drwxr-xr-x dim     ]  ccxt
    │   └── [drwxr-xr-x dim     ]  ccxt
    │       ├── [drwxr-xr-x dim     ]  build
    │       ├── [drwxr-xr-x dim     ]  dist
    │       ├── [drwxr-xr-x dim     ]  doc
    │       │   └── [drwxr-xr-x dim     ]  _static
    │       │       ├── [drwxr-xr-x dim     ]  css
    │       │       └── [drwxr-xr-x dim     ]  javascript
    │       ├── [drwxr-xr-x dim     ]  examples
    │       │   ├── [drwxr-xr-x dim     ]  async-php
    │       │   ├── [drwxr-xr-x dim     ]  ccxt.pro
    │       │   │   ├── [drwxr-xr-x dim     ]  js
    │       │   │   ├── [drwxr-xr-x dim     ]  php
    │       │   │   └── [drwxr-xr-x dim     ]  py
    │       │   ├── [drwxr-xr-x dim     ]  html
    │       │   ├── [drwxr-xr-x dim     ]  js
    │       │   ├── [drwxr-xr-x dim     ]  php
    │       │   ├── [drwxr-xr-x dim     ]  py
    │       │   └── [drwxr-xr-x dim     ]  ts
    │       │       ├── [drwxr-xr-x dim     ]  fetch-futures
    │       │       │   └── [drwxr-xr-x dim     ]  src
    │       │       └── [drwxr-xr-x dim     ]  fetch-tickers
    │       │           └── [drwxr-xr-x dim     ]  src
    │       ├── [drwxr-xr-x dim     ]  js
    │       │   ├── [drwxr-xr-x dim     ]  base
    │       │   │   └── [drwxr-xr-x dim     ]  functions
    │       │   ├── [drwxr-xr-x dim     ]  static_dependencies
    │       │   │   ├── [drwxr-xr-x dim     ]  BN
    │       │   │   ├── [drwxr-xr-x dim     ]  crypto-js
    │       │   │   ├── [drwxr-xr-x dim     ]  elliptic
    │       │   │   │   └── [drwxr-xr-x dim     ]  lib
    │       │   │   │       ├── [drwxr-xr-x dim     ]  elliptic
    │       │   │   │       │   ├── [drwxr-xr-x dim     ]  curve
    │       │   │   │       │   ├── [drwxr-xr-x dim     ]  ec
    │       │   │   │       │   ├── [drwxr-xr-x dim     ]  eddsa
    │       │   │   │       │   └── [drwxr-xr-x dim     ]  precomputed
    │       │   │   │       └── [drwxr-xr-x dim     ]  hmac-drbg
    │       │   │   ├── [drwxr-xr-x dim     ]  fetch-ponyfill
    │       │   │   ├── [drwxr-xr-x dim     ]  node-fetch
    │       │   │   ├── [drwxr-xr-x dim     ]  node-rsa
    │       │   │   │   ├── [drwxr-xr-x dim     ]  asn1
    │       │   │   │   │   └── [drwxr-xr-x dim     ]  ber
    │       │   │   │   ├── [drwxr-xr-x dim     ]  encryptEngines
    │       │   │   │   ├── [drwxr-xr-x dim     ]  formats
    │       │   │   │   ├── [drwxr-xr-x dim     ]  libs
    │       │   │   │   └── [drwxr-xr-x dim     ]  schemes
    │       │   │   └── [drwxr-xr-x dim     ]  qs
    │       │   └── [drwxr-xr-x dim     ]  test
    │       │       ├── [drwxr-xr-x dim     ]  base
    │       │       │   └── [drwxr-xr-x dim     ]  functions
    │       │       ├── [drwxr-xr-x dim     ]  errors
    │       │       └── [drwxr-xr-x dim     ]  Exchange
    │       ├── [drwxr-xr-x dim     ]  php
    │       │   ├── [drwxr-xr-x dim     ]  async
    │       │   ├── [drwxr-xr-x dim     ]  static_dependencies
    │       │   │   ├── [drwxr-xr-x dim     ]  BI
    │       │   │   ├── [drwxr-xr-x dim     ]  BN
    │       │   │   ├── [drwxr-xr-x dim     ]  elliptic-php
    │       │   │   │   └── [drwxr-xr-x dim     ]  lib
    │       │   │   │       ├── [drwxr-xr-x dim     ]  Curve
    │       │   │   │       │   ├── [drwxr-xr-x dim     ]  BaseCurve
    │       │   │   │       │   ├── [drwxr-xr-x dim     ]  EdwardsCurve
    │       │   │   │       │   ├── [drwxr-xr-x dim     ]  MontCurve
    │       │   │   │       │   └── [drwxr-xr-x dim     ]  ShortCurve
    │       │   │   │       ├── [drwxr-xr-x dim     ]  EC
    │       │   │   │       └── [drwxr-xr-x dim     ]  EdDSA
    │       │   │   └── [drwxr-xr-x dim     ]  kornrunner
    │       │   │       └── [drwxr-xr-x dim     ]  keccak
    │       │   │           └── [drwxr-xr-x dim     ]  src
    │       │   └── [drwxr-xr-x dim     ]  test
    │       ├── [drwxr-xr-x dim     ]  python
    │       │   └── [drwxr-xr-x dim     ]  ccxt
    │       │       ├── [drwxr-xr-x dim     ]  async_support
    │       │       │   └── [drwxr-xr-x dim     ]  base
    │       │       ├── [drwxr-xr-x dim     ]  base
    │       │       ├── [drwxr-xr-x dim     ]  pro
    │       │       ├── [drwxr-xr-x dim     ]  static_dependencies
    │       │       │   ├── [drwxr-xr-x dim     ]  ecdsa
    │       │       │   └── [drwxr-xr-x dim     ]  keccak
    │       │       └── [drwxr-xr-x dim     ]  test
    │       └── [drwxr-xr-x dim     ]  wiki
    ├── [drwxr-xr-x dim     ]  composer
    ├── [drwxr-xr-x dim     ]  pear
    │   └── [drwxr-xr-x dim     ]  console_table
    │       └── [drwxr-xr-x dim     ]  tests
    └── [drwxr-xr-x dim     ]  symfony
        └── [drwxr-xr-x dim     ]  polyfill-mbstring
            └── [drwxr-xr-x dim     ]  Resources
                └── [drwxr-xr-x dim     ]  unidata

179 directories

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

## System Tools

Night Stalker includes an administrative System Tools Dashboard for managing backend scripts and maintenance tasks.

### Accessing System Tools

1. **Via Navigation Bar**: Click the "System Tools" link in the main navigation
2. **Via User Menu**: Click your username, then select "System Tools" from the dropdown

### Available Tools

#### Data Management Tools

1. **CoinGecko Data Fetcher**
   - **Purpose**: Updates the database with latest coins from CoinGecko API
   - **Location**: `/scripts/fetch_coingecko_coins.php`
   - **Features**:
     - Updates `all_coingecko_coins` table with latest coin data
     - Creates CSV backups in `/data/` directory
     - Prevents duplicate daily runs
     - Provides detailed execution logs

#### Using System Tools

1. **Running Scripts**:
   - Click the "Run Now" button next to any tool
   - View real-time execution output in the browser
   - All executions are logged for future reference

2. **Viewing Logs**:
   - Click "View Log" to see previous execution results
   - Logs are timestamped and stored in `/system-tools/logs/`

### Security

- All system tools require user login (no guest access)
- Execution is logged and monitored
- Scripts run with web server permissions

### Adding New Tools

To add a new tool to the dashboard:

1. Add the script to an appropriate directory (e.g., `/scripts/`, `/cron/`)
2. Register the tool in `/system-tools/index.php` in the `$tools` array
3. Add execution permission in `/system-tools/run.php` in the `$allowedTools` array

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

- **Using the debug_portfolio.php Script to Investigate Coin Registration Issues**

  If you encounter issues where a coin in your portfolio (e.g., SPX) does not update its price chart or latest recorded time remains unchanged, it may be due to the coin not being properly registered in the portfolio or coins tables.

  A helpful debugging tool is provided in the `debug_portfolio.php` script located in the project root. This script allows you to test if a specific coin is found in your portfolio and how it is registered.

  ### How to Use

  1. Open your web browser and navigate to the script URL, for example:
     ```
     http://localhost/NS/debug_portfolio.php
     ```

  2. In the input field labeled "Coin ID", enter the coin symbol or ID you want to test, e.g., `SPX`.

  3. Click the "Test" button.

  4. The script will perform several checks:
     - Direct database query to find the coin in the portfolio.
     - Using the `getUserCoinBalancePDO()` function to fetch portfolio data.
     - Simulated sell action checks (without executing actual sell).
     - Displays the portfolio table schema.
     - Lists all coins currently in the portfolio, highlighting the tested coin if found.

  ### Interpreting the Output

  - **Success messages** indicate the coin was found in the portfolio and relevant data such as amount and average buy price.
  - **Failure messages** indicate the coin was not found, which may explain why price updates or chart data are not working.
  - The portfolio table listing helps you verify how the coin is stored (coin_id, amount, last updated, etc.).

  ### Why This Helps

  If the coin is missing or mismatched in the portfolio or coins tables, the price update loop will skip it, resulting in no new price data and no chart updates. Using this script helps you identify registration issues so you can correct them.

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


## Recent Fixes
###   Phase 1: Backend - Price History API Endpoint (Already Done)
   * We have crons/portfolio_price_monitor.php running in the background, populating price_history.
   * We have api/get-price-history.php ready to serve price history data.

###  Phase 2: Frontend - Portfolio Card UI with Mini-Graphs

***Step 1:*** Add Chart.js and new CSS to `coins.php` (Carefully)
       * I have already added the Chart.js library link to coins.php.
       * add the new CSS for the crypto widgets to coins.php within a new <style> block, ensuring it doesn't interfere with existing styles.
   ***Step 2:*** Modify `coins.php` HTML for Portfolio Display
       * change the div with id="portfolio" to be the container for the new card-style widgets. The main coin table will remain separate.
   ***Step 3:*** Update `assets/js/coins.js` for Portfolio Cards
       * I should modify the updatePortfolioDisplay function to:
           * Fetch portfolio data.
           * For each coin in the portfolio, dynamically create the HTML for the "coin-card" including a <canvas> element for the mini-graph.
           * Call api/get-price-history.php for each coin to get its price history.
           * Use Chart.js to draw the mini-graph on the canvas.
           * Remove the "Buy" button from these portfolio cards.
           * Ensure the "Sell" button on the cards sells the entire holding of that coin with a single confirmation click.Step 3: Update `assets/js/coins.js` for Portfolio Cards
       * I should modify the updatePortfolioDisplay function to:
           * Fetch portfolio data.
           * For each coin in the portfolio, dynamically create the HTML for the "coin-card" including a <canvas> element for the mini-graph.
           * Call api/get-price-history.php for each coin to get its price history.
           * Use Chart.js to draw the mini-graph on the canvas.
           * Remove the "Buy" button from these portfolio cards.
           * Ensure the "Sell" button on the cards sells the entire holding of that coin with a single confirmation click.

### New Coin Detection & Display Enhancement (June 2025)
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

### Coin Data Fetching and Display Fixes (July 2025)

Several critical issues with the coin data fetching, storage, and display were identified and fixed:

1. **Binance Price Fetching Script (`binanceFromCMC4NS.py`)**:
   - Fixed SQL query to include both `price` and `current_price` columns
   - Updated SQL UPDATE statement to update both columns with the same value
   - Resolved issue where coins were being saved with zero prices
   - Added proper error handling for database operations

2. **API Endpoint Enhancement (`api/get-coins.php`)**:
   - Added debug information to help diagnose empty coin list issues
   - Improved filtering logic for coin retrieval
   - Added counts for total coins and coins with prices > 0
   - Enhanced error reporting in API responses

3. **Frontend JavaScript Fixes (`coins.php`)**:
   - Fixed "filters is not defined" error after commenting out filter-coins.js
   - Added minimal filters object definition directly in coins.php
   - Implemented basic applyCustomFilters() function
   - Improved error handling in coin display logic

4. **Troubleshooting Tools**:
   - Created comprehensive debug script (`debug_coins_table.php`) to inspect database state
   - Added detailed troubleshooting guide (`TROUBLESHOOTING-EMPTY-COINS.md`)
   - Documented common issues and their solutions
   - Provided quick 5-minute fix checklist for empty coin list problems

These fixes ensure that coin prices are correctly fetched from Binance, properly stored in the database, and accurately displayed in the UI with working filters.

## Recent Updates (July 2025)

Significant enhancements and fixes have been implemented to improve the system's real-time price monitoring, automated trading, and user interface:

1.  **Unified Price Updater (`unified_price_updater.py`)**:
    *   Replaced separate `binance_price_updater_for_terminal.py` and `bitvavo_price_udater_for_terminal.py` scripts with a single, unified Python service.
    *   This script now fetches real-time prices from both Binance (using USDT trading pairs) and Bitvavo (using EUR trading pairs) based on the associated exchange in the `coins` table.
    *   Continuously updates the `price_history` table for all active portfolio coins.
    *   Includes robust logging and gracefully shuts down when the portfolio is empty, logging the timestamp of cessation.

2.  **Integrated Apex Tracking and Automated Trading**:
    *   The `unified_price_updater.py` now incorporates the advanced apex tracking logic.
    *   Monitors the highest recorded price (apex) for each coin in the portfolio.
    *   Detects significant price drops from the apex and, if sustained, automatically triggers sell orders via PHP API endpoints (`execute-sell.php`).
    *   Manages coin statuses (`monitoring`, `dropping`, `sold`) to guide trading decisions.

3.  **Enhanced Log Reader Tool (`log_reader.sh`)**:
    *   Improved the `log_reader.sh` script to accurately filter log entries.
    *   Now correctly identifies and displays log lines from yesterday onwards, filtering out older, irrelevant data.
    *   Includes better handling for various log formats (application, Apache, syslog/cron) and non-existent log files.

4.  **Coin Display Enhancements (`coins.php`)**:
    *   The portfolio view in `coins.php` now visually indicates the apex status of each coin.
    *   Coins are color-coded: Green for `monitoring`, Orange for `dropping`, and Red for `sold`, providing immediate visual feedback on trading status.
    *   A new "Apex Status" column has been added to the table.

5.  **Tutorial Integration**:
    *   A simple version of the tutorial has been started, guiding users through initial setup and core functionalities. (Further details on accessing the tutorial will be provided.)



## Recent Updates (July 2025)



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
