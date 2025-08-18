#!/bin/bash

# SurrealPilot Forge Server Setup Script
# This script prepares a fresh Forge server for SurrealPilot deployment

set -e

echo "⚙️ Setting up Forge server for SurrealPilot..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Update system packages
print_status "Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install additional PHP extensions if needed
print_status "Installing additional PHP extensions..."
sudo apt install -y php8.3-gd php8.3-imagick php8.3-redis

# Install Node.js and npm (if not already installed)
if ! command -v node &> /dev/null; then
    print_status "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

# Install Redis (for caching and sessions)
if ! command -v redis-server &> /dev/null; then
    print_status "Installing Redis..."
    sudo apt install -y redis-server
    sudo systemctl enable redis-server
    sudo systemctl start redis-server
fi

# Configure Redis
print_status "Configuring Redis..."
sudo sed -i 's/# maxmemory <bytes>/maxmemory 256mb/' /etc/redis/redis.conf
sudo sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
sudo systemctl restart redis-server

# Create necessary directories
print_status "Creating application directories..."
mkdir -p /home/forge/surrealpilot.com/storage/logs
mkdir -p /home/forge/surrealpilot.com/storage/app/public
mkdir -p /home/forge/backups

# Set up log rotation
print_status "Setting up log rotation..."
sudo tee /etc/logrotate.d/surrealpilot > /dev/null <<EOF
/home/forge/surrealpilot.com/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0644 forge forge
    postrotate
        php /home/forge/surrealpilot.com/artisan queue:restart
    endscript
}
EOF

# Configure supervisor for queue workers
print_status "Setting up queue workers..."
sudo tee /etc/supervisor/conf.d/surrealpilot-worker.conf > /dev/null <<EOF
[program:surrealpilot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/surrealpilot.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=2
redirect_stderr=true
stdout_logfile=/home/forge/surrealpilot.com/storage/logs/worker.log
stopwaitsecs=3600
EOF

sudo supervisorctl reread
sudo supervisorctl update

# Configure cron jobs
print_status "Setting up scheduled tasks..."
(crontab -l 2>/dev/null; echo "* * * * * cd /home/forge/surrealpilot.com && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# Set up SSL certificate (Let's Encrypt)
print_status "SSL certificate setup..."
print_warning "SSL certificate should be configured through Forge panel"

# Configure firewall
print_status "Configuring firewall..."
sudo ufw allow 80
sudo ufw allow 443
sudo ufw allow 22
sudo ufw --force enable

# Install monitoring tools
print_status "Installing monitoring tools..."
sudo apt install -y htop iotop nethogs

# Create backup script
print_status "Creating backup script..."
tee /home/forge/backup-surrealpilot.sh > /dev/null <<EOF
#!/bin/bash
TIMESTAMP=\$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/forge/backups"

# Database backup
mysqldump -u forge -p\$DB_PASSWORD surrealpilot_production > "\$BACKUP_DIR/db_\$TIMESTAMP.sql"

# Application backup
tar -czf "\$BACKUP_DIR/app_\$TIMESTAMP.tar.gz" -C /home/forge surrealpilot.com --exclude='storage/logs/*' --exclude='storage/framework/cache/*'

# Keep only last 7 days of backups
find \$BACKUP_DIR -name "*.sql" -mtime +7 -delete
find \$BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: \$TIMESTAMP"
EOF

chmod +x /home/forge/backup-surrealpilot.sh

# Add backup to cron
(crontab -l 2>/dev/null; echo "0 2 * * * /home/forge/backup-surrealpilot.sh >> /home/forge/backup.log 2>&1") | crontab -

print_status "Forge server setup completed!"
print_warning "Next steps:"
echo "1. Configure environment variables in Forge panel"
echo "2. Set up database and configure connection"
echo "3. Deploy application code"
echo "4. Run post-deployment script"
echo "5. Configure domain and SSL certificate"
echo "6. Test application functionality"