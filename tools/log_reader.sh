#!/bin/bash

# Get today's date in YYYY-MM-DD format
TODAY_DATE_YYYYMMDD=$(date +%Y-%m-%d)
# Get yesterday's date in YYYY-MM-DD format
YESTERDAY_DATE_YYYYMMDD=$(date -d "yesterday" +%Y-%m-%d)

# Get today's date in "Mon DD YYYY" format for Apache logs
TODAY_DATE_APACHE=$(date +"%b %e %Y")
# Get yesterday's date in "Mon DD YYYY" format for Apache logs
YESTERDAY_DATE_APACHE=$(date -d "yesterday" +"%b %e %Y")

# Get today's date in "Mon Day" format for syslog/cron logs
TODAY_DATE_SYSLOG=$(date +"%b %e")
# Get yesterday's date in "Mon Day" format for syslog/cron logs
YESTERDAY_DATE_SYSLOG=$(date -d "yesterday" +"%b %e")

filter_and_display_log() {
    local log_file=$1
    local log_name=$(basename "$log_file")
    echo "________________________________________________________"
    echo "showing lines from $log_name (from yesterday onwards)"
    echo "________________________________________________________"

    # Check if the log file exists
    if [ ! -f "$log_file" ]; then
        echo "Log file not found: $log_file"
        return
    fi

    # Determine log format and apply appropriate grep filter
    # Check for YYYY-MM-DD HH:MM:SS format (common for application logs)
    if head -n 100 "$log_file" | grep -q "^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}"; then
        grep -E "^($TODAY_DATE_YYYYMMDD|$YESTERDAY_DATE_YYYYMMDD)" "$log_file"
    # Check for Apache log format [Day Mon DD HH:MM:SS YYYY]
    elif head -n 100 "$log_file" | grep -q "^\[[A-Za-z]{3} [A-Za-z]{3} [0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4}\]"; then
        grep -E "\[[^\]]*($TODAY_DATE_APACHE|$YESTERDAY_DATE_APACHE)" "$log_file"
    # Check for syslog/cron log format (e.g., "Mon Day HH:MM:SS")
    elif head -n 100 "$log_file" | grep -q "^[A-Za-z]{3} +[0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2}"; then
        grep -E "^($TODAY_DATE_SYSLOG|$YESTERDAY_DATE_SYSLOG)" "$log_file"
    else
        # Fallback: if format is unknown, just show the last 50 lines (original behavior)
        echo "Unknown log format for $log_name. Showing last 50 lines."
        tail -n 50 "$log_file"
    fi
}

cd /opt/lampp/htdocs/NS/logs/
filter_and_display_log cron.log
filter_and_display_log events.log
filter_and_display_log php-error.log
filter_and_display_log bitvavo_script.log
filter_and_display_log price_updates.log

cd /opt/lampp/logs/
filter_and_display_log access_log
filter_and_display_log error_log
filter_and_display_log php_error_log
filter_and_display_log trade_monitor.log