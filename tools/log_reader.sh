#!/bin/bash

cd /opt/lampp/htdocs/NS/logs
echo showing last 50 lines of cron.log
echo ________________________________________________________
tail -n 50 cron.log
echo ________________________________________________________
echo showing last 50 lines of events.log
echo ________________________________________________________
tail -n 50 events.log
echo ________________________________________________________
echo showing last 50 lines of php-error.log
echo ________________________________________________________
tail -n 50 php-error.log
echo ________________________________________________________
echo showing last 50 lines of bitvavo_script.log
echo ________________________________________________________
tail -n 50 bitvavo_script.log
echo ________________________________________________________
echo showing last 50 lines of price_updates.log
echo ________________________________________________________
tail -n 50 price_updates.log


cd /opt/lampp/logs/
echo ________________________________________________________
echo showing last 50 lines of access_log
echo ________________________________________________________
tail -n 50 access_log
echo ________________________________________________________
echo showing last 50 lines of error_log
echo ________________________________________________________
tail -n 50 trade_monitor.log
echo ________________________________________________________
echo showing last 50 lines of php_error_log
echo ________________________________________________________
tail -n 50 php_error_logtail -n 50 error_log

