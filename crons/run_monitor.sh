#!/bin/bash
API_CALL_COUNT=0
MAX_CALLS_PER_MIN=25

while true; do
    if [ $API_CALL_COUNT -lt $MAX_CALLS_PER_MIN ]; then
        /opt/lampp/bin/php /opt/lampp/htdocs/NS/crons/monitor_trades.php >> /opt/lampp/logs/trade_monitor.log 2>&1
        ((API_CALL_COUNT++))
    else
        sleep 60
        API_CALL_COUNT=0
    fi
    sleep 3
done