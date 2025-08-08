# Price Monitoring System

This system monitors portfolio coin prices every 3 seconds when coin fetching is disabled, records price history, tracks the highest price per coin, detects sustained price drops below this high for 30 seconds, and triggers automatic sell orders.

## Features

- **Automatic Price Monitoring**: Tracks prices of all coins in your portfolio every 3 seconds
- **Price History Recording**: Stores price snapshots in the database for historical analysis
- **High Price Tracking**: Remembers the highest measured price for each coin
- **Price Drop Detection**: Detects when a coin's price stays below its all-time high for 30 seconds (10 checks)
- **Automatic Selling**: Triggers sell orders automatically when price drop conditions are met
- **Profit/Loss Calculation**: Calculates and logs profit or loss from auto-sell transactions

## Installation

### 1. Setup Database Tables

The system uses the following tables:
- `price_history`: Stores price snapshots
- `settings`: Stores configuration settings, including the masterFetchToggle

The tables are created automatically by the PriceMonitor class if they don't exist.

### 2. Install the Systemd Service

To run the price monitoring script as a background service:

1. Copy the service file to the systemd directory:
```bash
sudo cp /opt/lampp/htdocs/NS/price-monitor.service /etc/systemd/system/
```

2. Reload systemd to recognize the new service:
```bash
sudo systemctl daemon-reload
```

3. Enable the service to start on boot:
```bash
sudo systemctl enable price-monitor.service
```

4. Start the service:
```bash
sudo systemctl start price-monitor.service
```

5. Check the service status:
```bash
sudo systemctl status price-monitor.service
```

### 3. Verify File Permissions

Ensure the PHP script has proper permissions:
```bash
sudo chmod +x /opt/lampp/htdocs/NS/run-price-monitor.php
```

## Usage

### Enabling/Disabling Price Monitoring

The price monitoring system is controlled by the "Enable Fetching of New Coins" toggle in the Settings page:

- When the toggle is **OFF**: Price monitoring is **ACTIVE** (runs every 3 seconds)
- When the toggle is **ON**: Price monitoring is **INACTIVE** (coin fetching is active instead)

To enable price monitoring:
1. Go to the Settings page
2. Turn OFF the "Enable Fetching of New Coins" toggle
3. The system will automatically start monitoring prices

### Monitoring Logs

The price monitoring system logs its activities to the system log. You can view these logs using:

```bash
sudo journalctl -u price-monitor.service -f
```

### Understanding Auto-Sell Logic

The auto-sell feature works as follows:

1. The system tracks the highest price seen for each coin in your portfolio
2. If the price drops and stays below this high for 30 consecutive seconds (10 checks at 3-second intervals), the system triggers a sell order
3. The sell order is executed via the existing trade execution API
4. Profit or loss is calculated based on the average buy price and the sell price
5. The transaction is logged in the trade_log table

### Manual Control

If you need to manually stop the price monitoring service:

```bash
sudo systemctl stop price-monitor.service
```

To restart it:

```bash
sudo systemctl restart price-monitor.service
```

## Troubleshooting

### Service Not Starting

If the service fails to start, check the logs:

```bash
sudo journalctl -u price-monitor.service -e
```

Common issues:
- PHP path incorrect in the service file
- File permissions issues
- Database connection problems

### No Auto-Sells Happening

If the system isn't triggering auto-sells:

1. Verify the price monitoring is active (coin fetching toggle is OFF)
2. Check the logs for any errors
3. Ensure the portfolio contains coins with price data
4. Verify the price drop condition is being met (30 seconds below high)

### Database Errors

If you encounter database errors:

1. Check database connection settings in `includes/database.php`
2. Verify the required tables exist
3. Check table permissions for the database user

## Architecture

The price monitoring system consists of:

1. **PriceMonitor Class** (`includes/price_monitor.php`): Core logic for monitoring prices and triggering sells
2. **Runner Script** (`run-price-monitor.php`): Background script that runs the monitoring process
3. **Settings API** (`api/update-settings.php` and `api/get-settings.php`): API endpoints to control the monitoring
4. **Frontend Integration** (`assets/js/settings.js`): JavaScript to connect the UI toggle with the API

## Advanced Configuration

For advanced users, you can modify the following parameters in the code:

- **Check Interval**: In `run-price-monitor.php`, change the `sleep(3)` value to adjust the interval between checks
- **Price Drop Threshold**: In `includes/price_monitor.php`, modify the `$consecutiveChecksThreshold` property (default: 10)
- **Database Tables**: In `includes/price_monitor.php`, you can customize the table names and schemas
