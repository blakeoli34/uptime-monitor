#!/bin/bash

# Exit on error
set -e

# Configuration
DEPLOY_PATH="/var/www/uptimemonitor"
BACKUP_PATH="/var/www/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}Starting deployment process...${NC}"

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_PATH

# Backup current deployment
echo "Creating backup..."
tar -czf $BACKUP_PATH/uptimemonitor_$TIMESTAMP.tar.gz -C $DEPLOY_PATH .

# Pull latest changes
echo "Pulling latest changes..."
cd $DEPLOY_PATH
git pull origin main

# Install/update PHP dependencies
echo "Updating PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Install/update Node.js dependencies
echo "Updating Node.js dependencies..."
npm ci --production

# Update environment file if needed
if [ ! -f ".env" ]; then
    echo -e "${RED}Warning: .env file not found!${NC}"
    echo "Creating from .env.example..."
    cp .env.example .env
    echo -e "${YELLOW}Please update .env with proper values${NC}"
fi

# Create necessary directories
echo "Ensuring directory structure..."
mkdir -p logs
mkdir -p public/assets
chmod -R 755 public
chmod -R 770 logs

# Clear PHP cache
echo "Clearing PHP cache..."
if [ -d "/var/www/uptimemonitor/cache" ]; then
    rm -rf /var/www/uptimemonitor/cache/*
fi

# Restart Node.js monitor service
echo "Restarting monitor service..."
pm2 reload uptimemonitor

# Reload PHP-FPM
echo "Reloading PHP-FPM..."
sudo systemctl reload php8.1-fpm

# Reload NGINX
echo "Reloading NGINX..."
sudo nginx -t && sudo systemctl reload nginx

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data $DEPLOY_PATH
find $DEPLOY_PATH -type f -exec chmod 644 {} \;
find $DEPLOY_PATH -type d -exec chmod 755 {} \;
chmod -R 770 $DEPLOY_PATH/logs

# Final check
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "Checking services..."

# Check Node.js service
if pm2 list | grep -q "uptimemonitor"; then
    echo -e "${GREEN}✓ Monitor service is running${NC}"
else
    echo -e "${RED}✗ Monitor service is not running${NC}"
fi

# Check NGINX
if systemctl is-active --quiet nginx; then
    echo -e "${GREEN}✓ NGINX is running${NC}"
else
    echo -e "${RED}✗ NGINX is not running${NC}"
fi

# Check PHP-FPM
if systemctl is-active --quiet php8.1-fpm; then
    echo -e "${GREEN}✓ PHP-FPM is running${NC}"
else
    echo -e "${RED}✗ PHP-FPM is not running${NC}"
fi

echo -e "\n${GREEN}Deployment process completed at $(date)${NC}"