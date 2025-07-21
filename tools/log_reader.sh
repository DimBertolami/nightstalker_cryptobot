echo "__________"
echo "|cron.log|"
echo "__________"
tail -n 20 '/opt/lampp/htdocs/NS/logs/cron.log'
echo "____________"
echo "|events.log|"
echo "____________"
tail -n 50 '/opt/lampp/htdocs/NS/logs/events.log'
echo "______________"
echo "|php-error.log|"
echo "______________"
tail -n 50 '/opt/lampp/htdocs/NS/logs/php-error.log'
echo "____________________"
echo "|bitvavo_script.log|"
echo "____________________"
tail -n 50 '/opt/lampp/htdocs/NS/logs/bitvavo_script.log'
echo "___________________"
echo "|price_updates.log|"
echo "___________________"
tail -n 50 '/opt/lampp/htdocs/NS/logs/price_updates.log'
echo "__________________"
echo "|trade_monitor.log|"
echo "__________________"
tail -n 50 '/opt/lampp/logs/trade_monitor.log'
echo "__________"
echo "|error_log|"
echo "__________"
tail -n 50 '/opt/lampp/logs/error_log'
echo "_______________"
echo "|php_error_log|"
echo "_______________"
tail -n 50 '/opt/lampp/logs/php_error_log'
