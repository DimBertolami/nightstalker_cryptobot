#!/bin/bash
#
# Export Sensitive Data Script
# For Night Stalker Cryptobot
#
# This script exports sensitive data from the database for backup purposes
# It supports a --dry-run flag to preview what would be exported

# Default settings
DRY_RUN=false
EXPORT_DIR="/opt/lampp/htdocs/NS/exports"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
EXPORT_FILE="sensitive_data_${DATE}.sql"
DB_CONFIG_FILE="/opt/lampp/htdocs/NS/config/database.php"

# Parse command line arguments
for arg in "$@"; do
  case $arg in
    --dry-run)
      DRY_RUN=true
      shift
      ;;
  esac
done

# Function to extract database credentials from PHP config
get_db_credentials() {
  if [ ! -f "$DB_CONFIG_FILE" ]; then
    echo "Error: Database config file not found at $DB_CONFIG_FILE"
    exit 1
  fi
  
  # Extract database credentials using grep and sed
  DB_HOST=$(grep -o "DB_HOST.*" "$DB_CONFIG_FILE" | sed "s/.*['\"]\(.*\)['\"].*/\1/")
  DB_NAME=$(grep -o "DB_NAME.*" "$DB_CONFIG_FILE" | sed "s/.*['\"]\(.*\)['\"].*/\1/")
  DB_USER=$(grep -o "DB_USER.*" "$DB_CONFIG_FILE" | sed "s/.*['\"]\(.*\)['\"].*/\1/")
  DB_PASS=$(grep -o "DB_PASS.*" "$DB_CONFIG_FILE" | sed "s/.*['\"]\(.*\)['\"].*/\1/")
  
  # Check if all credentials were found
  if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "Error: Could not extract all database credentials from config file"
    exit 1
  fi
}

# Create export directory if it doesn't exist
create_export_dir() {
  if [ ! -d "$EXPORT_DIR" ]; then
    if [ "$DRY_RUN" = false ]; then
      mkdir -p "$EXPORT_DIR"
      echo "Created export directory: $EXPORT_DIR"
    else
      echo "[DRY RUN] Would create export directory: $EXPORT_DIR"
    fi
  fi
}

# Export sensitive tables
export_sensitive_data() {
  # Tables containing sensitive data
  SENSITIVE_TABLES=("users" "api_keys" "wallets" "portfolio" "trade_log")
  
  # Build mysqldump command
  TABLES_ARGS=""
  for table in "${SENSITIVE_TABLES[@]}"; do
    TABLES_ARGS="$TABLES_ARGS $table"
  done
  
  EXPORT_PATH="$EXPORT_DIR/$EXPORT_FILE"
  
  if [ "$DRY_RUN" = true ]; then
    echo "[DRY RUN] Would export the following tables to $EXPORT_PATH:"
    for table in "${SENSITIVE_TABLES[@]}"; do
      echo "  - $table"
    done
    echo "[DRY RUN] Command that would be executed:"
    echo "mysqldump -h $DB_HOST -u $DB_USER -p***** $DB_NAME $TABLES_ARGS > $EXPORT_PATH"
  else
    echo "Exporting sensitive data to $EXPORT_PATH..."
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" $TABLES_ARGS > "$EXPORT_PATH"
    
    if [ $? -eq 0 ]; then
      echo "Export completed successfully"
      echo "File size: $(du -h "$EXPORT_PATH" | cut -f1)"
      chmod 600 "$EXPORT_PATH"
      echo "File permissions set to 600 (owner read/write only)"
    else
      echo "Export failed"
      exit 1
    fi
  fi
}

# Main execution
echo "Night Stalker Sensitive Data Export"
echo "=================================="

# Get database credentials
get_db_credentials

# Create export directory
create_export_dir

# Export sensitive data
export_sensitive_data

echo "Done."
exit 0
