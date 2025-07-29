#!/bin/bash
# Night Stalker - Export Sensitive Data Script
# This script backs up all sensitive data including database, config files, and credentials
# Created: June 2025

# Display usage information
show_usage() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -h, --help     Show this help message"
    echo "  -d, --dry-run  Show what would be backed up without actually creating backups"
    echo "  -p, --password Set the database password"
    echo "  -u, --user     Set the database username (default: root)"
    echo "  -n, --name     Set the database name (default: night_stalker)"
    echo ""
    echo "Example:"
    echo "  $0 --dry-run              # Show what would be backed up"
    echo "  $0 --password=mypass      # Set database password"
    echo "  $0 -u admin -n my_db      # Set database user and name"
    exit 0
}

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Set default variables
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_DIR="$(pwd)/backup_$TIMESTAMP"
#DB_NAME="night_stalker"
DB_NAME="NS"
DB_USER="root"
DB_PASS="1304"  # Default empty password
DRY_RUN=false

# Parse command line arguments
for arg in "$@"; do
    case $arg in
        -h|--help)
            show_usage
            ;;
        -d|--dry-run)
            DRY_RUN=true
            ;;
        -p=*|--password=*)
            DB_PASS="${arg#*=}"
            ;;
        -u=*|--user=*)
            DB_USER="${arg#*=}"
            ;;
        -n=*|--name=*)
            DB_NAME="${arg#*=}"
            ;;
        -p|--password)
            read -sp "Enter MySQL password: " DB_PASS
            echo ""
            ;;
        -u|--user)
            if [[ "$2" != -* ]]; then
                DB_USER="$2"
                shift
            fi
            ;;
        -n|--name)
            if [[ "$2" != -* ]]; then
                DB_NAME="$2"
                shift
            fi
            ;;
    esac
    shift
done

# Text colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Create backup directory
echo -e "${YELLOW}Creating backup directory...${NC}"
if [ "$DRY_RUN" = false ]; then
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$BACKUP_DIR/config"
    mkdir -p "$BACKUP_DIR/includes"
    mkdir -p "$BACKUP_DIR/database"
else
    echo -e "${GREEN}[DRY RUN]${NC} Would create directory: $BACKUP_DIR"
    echo -e "${GREEN}[DRY RUN]${NC} Would create subdirectories: config, includes, database"
fi

# Export MySQL database
echo -e "${YELLOW}Exporting database...${NC}"

# Check if mysqldump is available
if ! command -v mysqldump &> /dev/null; then
    echo -e "${RED}mysqldump command not found. Database export skipped.${NC}"
    echo -e "${YELLOW}Please install MySQL client tools or check your PATH.${NC}"
else
    # Ask for password if not provided and not running in non-interactive mode
    if [ -z "$DB_PASS" ] && [ -t 0 ] && [ "$DRY_RUN" = false ]; then
        read -sp "Enter MySQL password for user $DB_USER (leave empty for no password): " DB_PASS
        echo ""
    fi
    
    # Set export file path
    DB_EXPORT_FILE="$BACKUP_DIR/database/${DB_NAME}_$TIMESTAMP.sql"
    
    if [ "$DRY_RUN" = false ]; then
        # Attempt database export with error handling
        if [ -z "$DB_PASS" ]; then
            mysqldump -u "$DB_USER" "$DB_NAME" > "$DB_EXPORT_FILE" 2>/tmp/mysqldump_error
        else
            mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_EXPORT_FILE" 2>/tmp/mysqldump_error
        fi
        
        if [ $? -eq 0 ] && [ -s "$DB_EXPORT_FILE" ]; then
            echo -e "${GREEN}Database export successful!${NC}"
            echo -e "${GREEN}Saved to: $DB_EXPORT_FILE${NC}"
        else
            echo -e "${RED}Database export failed!${NC}"
            if [ -f /tmp/mysqldump_error ]; then
                echo -e "${RED}Error message: $(cat /tmp/mysqldump_error)${NC}"
                rm /tmp/mysqldump_error
            fi
            echo -e "${YELLOW}Continuing with other backups...${NC}"
        fi
    else
        echo -e "${GREEN}[DRY RUN]${NC} Would export database '$DB_NAME' as user '$DB_USER'"
        echo -e "${GREEN}[DRY RUN]${NC} Would save to: $DB_EXPORT_FILE"
    fi
fi

# Backup config files
echo -e "${YELLOW}Backing up configuration files...${NC}"
if [ -d "$SCRIPT_DIR/config" ] && [ "$(ls -A "$SCRIPT_DIR/config" 2>/dev/null)" ]; then
    if [ "$DRY_RUN" = false ]; then
        cp -r "$SCRIPT_DIR/config"/* "$BACKUP_DIR/config/"
        echo -e "${GREEN}Config files backed up!${NC}"
    else
        echo -e "${GREEN}[DRY RUN]${NC} Would backup config files from: $SCRIPT_DIR/config"
        echo -e "${GREEN}[DRY RUN]${NC} Found files: $(ls -A "$SCRIPT_DIR/config" | tr '\n' ' ')"
    fi
else
    echo -e "${YELLOW}No config files found or directory is empty.${NC}"
fi

# Backup sensitive PHP files
echo -e "${YELLOW}Backing up sensitive PHP files...${NC}"

# Define sensitive files to backup
SENSITIVE_FILES=(
    "includes/config.php"
    "includes/auth.php"
    "includes/database.php"
    "includes/exchange_config.php"
    "wallet-auth.php"
    ".htaccess"
)

# Process each file
for file in "${SENSITIVE_FILES[@]}"; do
    src_file="$SCRIPT_DIR/$file"
    if [ -f "$src_file" ]; then
        if [ "$DRY_RUN" = false ]; then
            # Create target directory if needed
            target_dir="$BACKUP_DIR/$(dirname "$file")"
            mkdir -p "$target_dir"
            
            # Copy the file
            cp "$src_file" "$BACKUP_DIR/$file"
            echo -e "${GREEN}$(basename "$file") backed up!${NC}"
        else
            echo -e "${GREEN}[DRY RUN]${NC} Would backup: $file"
        fi
    else
        echo -e "${YELLOW}$(basename "$file") not found, skipping...${NC}"
    fi
done

# Backup .env file if exists
if [ -f "$SCRIPT_DIR/.env" ]; then
    echo -e "${YELLOW}Backing up .env file...${NC}"
    if [ "$DRY_RUN" = false ]; then
        cp "$SCRIPT_DIR/.env" "$BACKUP_DIR/"
        echo -e "${GREEN}.env file backed up!${NC}"
    else
        echo -e "${GREEN}[DRY RUN]${NC} Would backup .env file"
    fi
else
    echo -e "${YELLOW}.env file not found, skipping...${NC}"
fi

# Backup SQL schema files
echo -e "${YELLOW}Backing up SQL schema files...${NC}"
if [ -d "$SCRIPT_DIR/install" ]; then
    sql_files=$(find "$SCRIPT_DIR/install" -name "*.sql" 2>/dev/null)
    if [ -n "$sql_files" ]; then
        if [ "$DRY_RUN" = false ]; then
            mkdir -p "$BACKUP_DIR/database"
            cp "$SCRIPT_DIR/install"/*.sql "$BACKUP_DIR/database/" 2>/dev/null
            echo -e "${GREEN}SQL schema files backed up!${NC}"
        else
            echo -e "${GREEN}[DRY RUN]${NC} Would backup SQL schema files:"
            for sql_file in $sql_files; do
                echo -e "${GREEN}[DRY RUN]${NC}   - $(basename "$sql_file")"
            done
        fi
    else
        echo -e "${YELLOW}No SQL schema files found.${NC}"
    fi
else
    echo -e "${YELLOW}Install directory not found.${NC}"
fi

# Create a README file in the backup directory
echo -e "${YELLOW}Creating README file with restoration instructions...${NC}"
if [ "$DRY_RUN" = false ]; then
    cat > "$BACKUP_DIR/README.txt" << EOL
=== Night Stalker Backup Restoration Guide ===
Timestamp: $TIMESTAMP

This backup contains sensitive data required to restore your Night Stalker installation.

1. Database Restoration:
   - Import the SQL dump using: mysql -u [username] -p [database_name] < database/${DB_NAME}_$TIMESTAMP.sql

2. Configuration Files:
   - Copy the 'config' directory to your Night Stalker installation root

3. PHP Include Files:
   - Copy files from the 'includes' directory to your Night Stalker 'includes' directory

4. Other Files:
   - Copy any other files (.env, .htaccess, wallet-auth.php, etc.) to your Night Stalker root directory

IMPORTANT: Keep this backup secure as it contains sensitive information!
EOL
    echo -e "${GREEN}Created README.txt with restoration instructions${NC}"
else
    echo -e "${GREEN}[DRY RUN]${NC} Would create README.txt with restoration instructions"
fi

# Create compressed archive
ARCHIVE_NAME="night_stalker_backup_$TIMESTAMP.zip"
echo -e "${YELLOW}Creating compressed archive...${NC}"
if [ "$DRY_RUN" = false ]; then
    # Check if zip command is available
    if command -v zip &> /dev/null; then
        # Create zip archive from backup directory
        (cd "$(dirname "$BACKUP_DIR")" && zip -r "$ARCHIVE_NAME" "$(basename "$BACKUP_DIR")") 
        echo -e "${GREEN}Backup archive created: $ARCHIVE_NAME${NC}"
    else
        echo -e "${RED}zip command not found. Using tar.gz format instead.${NC}"
        ARCHIVE_NAME="night_stalker_backup_$TIMESTAMP.tar.gz"
        tar -czf "$ARCHIVE_NAME" -C "$(dirname "$BACKUP_DIR")" "$(basename "$BACKUP_DIR")"
        echo -e "${GREEN}Backup archive created: $ARCHIVE_NAME${NC}"
    fi
    
    # Clean up temporary files
    echo -e "${YELLOW}Cleaning up temporary files...${NC}"
    rm -rf "$BACKUP_DIR"
    echo -e "${GREEN}Backup completed successfully!${NC}"
else
    # Check if zip command is available for dry run message
    if command -v zip &> /dev/null; then
        echo -e "${GREEN}[DRY RUN]${NC} Would create ZIP archive: $ARCHIVE_NAME"
    else
        echo -e "${GREEN}[DRY RUN]${NC} Would create TAR.GZ archive: night_stalker_backup_$TIMESTAMP.tar.gz (zip command not available)"
    fi
    echo -e "${GREEN}[DRY RUN]${NC} Would clean up temporary directory: $BACKUP_DIR"
    echo -e "${GREEN}[DRY RUN]${NC} Dry run completed successfully!"
fi

# Print summary
echo ""
echo "=== Night Stalker Backup Summary ==="
echo "Timestamp: $TIMESTAMP"
if [ "$DRY_RUN" = false ]; then
    echo "Archive: $ARCHIVE_NAME"
    echo ""
    echo "To restore this backup, extract the archive and follow the instructions in README.txt"
    echo "IMPORTANT: Keep this backup secure as it contains sensitive information!"
else
    echo "Mode: DRY RUN (no files were actually backed up)"
    echo ""
    echo "Run without --dry-run to perform the actual backup operation"
fi
