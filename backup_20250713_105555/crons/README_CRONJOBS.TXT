this is how you schedule a cron jobs on linux for new coins import and price updates


# Run new coins import every minutes
*/30 * * * * /opt/lampp/htdocs/NS/crons/run_new_coins_import.sh >> /opt/lampp/htdocs/NS/logs/new_coins_import.log 2>&1


the run_new_coins_import.sh script will Run the importer in php and save the new coins to the database
PHP_BIN=$(which php)
$PHP_BIN /opt/lampp/htdocs/NS/crons/save_new_coins.php >> /opt/lampp/htdocs/NS/logs/new_coins_cron.log

 * Import new coins from the Python script into the Night Stalker database
 * This script fetches new coin listings using the newcoinstracker.py script
 * and adds them to the database with proper information

 /opt/lampp/htdocs/NS/crypto_sources/newcoinstracker.py

# Run price updates every minutes
*/1 * * * * /opt/lampp/htdocs/backend/check_portfolio_and_update.sh >> /opt/lampp/htdocs/NS/logs/price_updates.log 2>

to edit cron jobs use this command:
crontab -e

after editing cron jobs use this command to save:
crontab -l

