#!/bin/bash
# /opt/lampp/htdocs/NS/crons/run_monitor.sh
while true; do
    php /opt/lampp/htdocs/NS/crons/monitor_trades.php
    sleep 3
done