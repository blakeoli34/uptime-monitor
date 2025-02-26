#!/bin/bash

# Load environment variables
source /var/www/uptimemonitor/.env

# Configuration
BACKUP_DIR="/var/www/backups/uptimemonitor"
MYSQL_USER=$DB_USER
MYSQL_PASSWORD=$DB_PASS
MYSQL_DATABASE=$DB_NAME
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30
LOG_FILE="/var/log/uptimemonitor/backup.log"

# Create backup directories if they don't exist
mkdir -p "$BACKUP_DIR/database"
mkdir -p "$BACKUP_DIR/logs"
mkdir -p "$(dirname $LOG_FILE)"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handling
handle_error() {
    log "ERROR: $1"
    if [ ! -z "$2" ]; then
        log "Details: $2"
    fi
    exit 1
}

# Start backup process
log "Starting backup process..."

# Database backup
DB_BACKUP_FILE="$BACKUP_DIR/database/uptimemonitor_db_$DATE.sql.gz"
log "Creating database backup: $DB_BACKUP_FILE"

mysqldump --user=$MYSQL_USER --password=$MYSQL_PASSWORD \
    --single-transaction \
    --quick \
    --lock-tables=false \
    $MYSQL_DATABASE | gzip > "$DB_BACKUP_FILE" \
    || handle_error "Database backup failed" "mysqldump error $?"

# Logs backup
LOGS_BACKUP_FILE="$BACKUP_DIR/logs/uptimemonitor_logs_$DATE.tar.gz"
log "Creating logs backup: $LOGS_BACKUP_FILE"

tar -czf "$LOGS_BACKUP_FILE" -C /var/www/uptimemonitor logs/ \
    || handle_error "Logs backup failed" "tar error $?"

# Clean up old backups
log "Cleaning up old backups..."

find "$BACKUP_DIR/database" -type f -name "uptimemonitor_db_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR/logs" -type f -name "uptimemonitor_logs_*.tar.gz" -mtime +$RETENTION_DAYS -delete

# Verify backups
if [ ! -f "$DB_BACKUP_FILE" ]; then
    handle_error "Database backup file not found after backup"
fi

if [ ! -f "$LOGS_BACKUP_FILE" ]; then
    handle_error "Logs backup file not found after backup"
fi

# Get backup sizes
DB_SIZE=$(du -h "$DB_BACKUP_FILE" | cut -f1)
LOGS_SIZE=$(du -h "$LOGS_BACKUP_FILE" | cut -f1)

# Check backup sizes
if [ $(stat -f%z "$DB_BACKUP_FILE") -eq 0 ]; then
    handle_error "Database backup file is empty"
fi

if [ $(stat -f%z "$LOGS_BACKUP_FILE") -eq 0 ]; then
    handle_error "Logs backup file is empty"
fi

# Final success log
log "Backup completed successfully!"
log "Database backup size: $DB_SIZE"
log "Logs backup size: $LOGS_SIZE"