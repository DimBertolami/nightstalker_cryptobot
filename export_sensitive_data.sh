#!/bin/bash
# Night Stalker - Export Sensitive Data Script
# This script backs up all sensitive data including database, config files, and credentials
# Created: June 2025

# Set variables
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_DIR="$(pwd)/backup_$TIMESTAMP"
DB_NAME="night_stalker"
DB_USER="root"
DB_PASS=""  # Set your database password here if needed

# Text colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Create backup directory
echo -e "${YELLOW}Creating backup directory...${NC}"
mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_DIR/config"
mkdir -p "$BACKUP_DIR/includes"
mkdir -p "$BACKUP_DIR/database"

# Export MySQL database
echo -e "${YELLOW}Exporting database...${NC}"
if [ -z "$DB_PASS" ]; then
    mysqldump -u "$DB_USER" "$DB_NAME" > "$BACKUP_DIR/database/${DB_NAME}_$TIMESTAMP.sql"
else
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/database/${DB_NAME}_$TIMESTAMP.sql"
fi

if [ $? -eq 0 ]; then
    echo -e "${GREEN}Database export successful!${NC}"
else
    echo -e "${RED}Database export failed!${NC}"
fi

# Backup config files
echo -e "${YELLOW}Backing up configuration files...${NC}"
cp -r ./config/* "$BACKUP_DIR/config/"
echo -e "${GREEN}Config files backed up!${NC}"

# Backup sensitive PHP files
echo -e "${YELLOW}Backing up sensitive PHP files...${NC}"
cp ./includes/config.php "$BACKUP_DIR/includes/"
cp ./includes/auth.php "$BACKUP_DIR/includes/"
cp ./includes/database.php "$BACKUP_DIR/includes/"
cp ./includes/exchange_config.php "$BACKUP_DIR/includes/"
cp ./wallet-auth.php "$BACKUP_DIR/" 2>/dev/null || echo -e "${YELLOW}wallet-auth.php not found, skipping...${NC}"

# Backup .env file if exists
if [ -f "./.env" ]; then
    echo -e "${YELLOW}Backing up .env file...${NC}"
    cp ./.env "$BACKUP_DIR/"
    echo -e "${GREEN}.env file backed up!${NC}"
else
    echo -e "${YELLOW}.env file not found, skipping...${NC}"
fi

# Backup SQL schema files
echo -e "${YELLOW}Backing up SQL schema files...${NC}"
cp ./install/*.sql "$BACKUP_DIR/database/" 2>/dev/null
echo -e "${GREEN}SQL schema files backed up!${NC}"

# Create a README file in the backup directory
cat > "$BACKUP_DIR/README.txt" << EOL
Night Stalker Backup - $TIMESTAMP

This backup contains sensitive data required to restore your Night Stalker installation.
When restoring, copy these files to their respective locations in a fresh installation.

Contents:
1. Database dump: database/${DB_NAME}_$TIMESTAMP.sql
2. Configuration files: config/
3. Sensitive PHP files: includes/
4. Environment variables: .env (if present)
5. SQL schema files: database/*.sql

Restoration Instructions:
1. Install a fresh copy of Night Stalker from GitHub
2. Import the database: mysql -u [username] -p [database_name] < database/${DB_NAME}_$TIMESTAMP.sql
3. Copy the config files to the config/ directory
4. Copy the sensitive PHP files to their respective locations
5. Copy the .env file to the root directory (if present)

IMPORTANT: Keep this backup secure as it contains API keys and other sensitive information!
EOL

# Create a compressed archive
echo -e "${YELLOW}Creating compressed archive...${NC}"
BACKUP_FILENAME="night_stalker_backup_$TIMESTAMP.tar.gz"
tar -czf "$BACKUP_FILENAME" -C "$(dirname "$BACKUP_DIR")" "$(basename "$BACKUP_DIR")"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}Backup archive created: $BACKUP_FILENAME${NC}"
    echo -e "${YELLOW}Cleaning up temporary files...${NC}"
    rm -rf "$BACKUP_DIR"
    echo -e "${GREEN}Backup completed successfully!${NC}"
else
    echo -e "${RED}Failed to create backup archive!${NC}"
    echo -e "${YELLOW}Backup files remain in: $BACKUP_DIR${NC}"
fi

echo -e "\n${GREEN}=== Night Stalker Backup Summary ===${NC}"
echo -e "Timestamp: $TIMESTAMP"
echo -e "Archive: $BACKUP_FILENAME"
echo -e "\nTo restore this backup, extract the archive and follow the instructions in README.txt"
echo -e "${YELLOW}IMPORTANT: Keep this backup secure as it contains sensitive information!${NC}"
