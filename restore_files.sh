#!/bin/bash
# Script to restore modified files from GitHub repository

# Base URL for raw GitHub content
BASE_URL="https://raw.githubusercontent.com/DimBertolami/nightstalker_cryptobot/main"

# Base directory
BASE_DIR="/opt/lampp/htdocs/NS"

# List of files to restore
FILES=(
  "coins.php"
  "crons/README_CRONJOBS.TXT"
  "crons/execute_trades.php"
  "crons/fetch_coins.OLD"
  "crons/fetch_coins.php"
  "crons/fetch_coins.php.bak"
  "crons/import_all_coins.php"
  "crons/monitor_new_coins.php"
  "crons/monitor_trades.php"
  "crons/monitor_trades.php.bak"
  "crons/real_time_CMC_price_monitor.py"
  "crons/run_autonomous_trader.php"
  "crons/save_new_coins.php"
  "crons/screen -dmS price_monitor /opt/lampp/htdocs/NS/crons/run_monitor.sh"
  "dashboard/settings.php"
  "includes/config.php"
)

echo "Starting file restoration from GitHub..."

# Create backup directory
BACKUP_DIR="$BASE_DIR/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
echo "Created backup directory: $BACKUP_DIR"

# Download each file
for FILE in "${FILES[@]}"; do
  # Create backup of current file if it exists
  if [ -f "$BASE_DIR/$FILE" ]; then
    BACKUP_PATH="$BACKUP_DIR/$FILE"
    mkdir -p "$(dirname "$BACKUP_PATH")"
    cp "$BASE_DIR/$FILE" "$BACKUP_PATH"
    echo "Backed up: $FILE"
  fi
  
  # Create directory if it doesn't exist
  mkdir -p "$(dirname "$BASE_DIR/$FILE")"
  
  # Special handling for file with spaces
  if [[ "$FILE" == *"screen -dmS"* ]]; then
    TARGET_FILE="crons/run_monitor.sh"
    wget -q "$BASE_URL/$TARGET_FILE" -O "$BASE_DIR/$TARGET_FILE"
    echo "Downloaded: $TARGET_FILE"
  else
    # Download file from GitHub
    wget -q "$BASE_URL/$FILE" -O "$BASE_DIR/$FILE"
    echo "Downloaded: $FILE"
  fi
done

echo "File restoration complete!"
echo "Original files backed up to: $BACKUP_DIR"
echo "Please check the files and make sure they are working correctly."
