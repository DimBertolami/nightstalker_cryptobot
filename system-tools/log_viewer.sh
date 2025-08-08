cd /opt/lampp/htdocs/NS/system-tools/
tail -n 50 cron.log
tail -n 50 events.log
tail -n 50 php-error.log
tail -n 50 bitvavo_script.log
tail -n 50 price_updates.log

cd /opt/lampp/logs/
tail -n 50 access_log
tail -n 50 trade_monitor.log
tail -n 50 php_error_logtail -n 50 error_log
