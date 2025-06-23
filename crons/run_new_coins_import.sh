#!/bin/bash
# Script to run the new coins import process
# Designed to be run via cron job

# Change to script directory
cd "$(dirname "$0")"

# Path to PHP executable
PHP_BIN=$(which php)

# Run the importer
$PHP_BIN /opt/lampp/htdocs/NS/crons/save_new_coins.php >> /opt/lampp/htdocs/NS/logs/new_coins_cron.log 2>&1

# Exit with the script's exit code
exit $?
