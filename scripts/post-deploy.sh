#!/bin/bash

# SurrealPilot Post-Deployment Script
# This script runs on the server after code deployment

set -e

echo "🔧 Running SurrealPilot post-deployment tasks..."

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

# Ensure we're in the correct directory
cd /home/forge/surrealpilot.com

# Install/update dependencies
print_status "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and cache configuration
print_status "Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# Run database migrations
print_status "Running database migrations..."
php artisan migrate --force

# Seed subscription plans if needed
print_status "Seeding subscription plans..."
php artisan db:seed --class=SubscriptionPlanSeeder --force

# Clear application cache
print_status "Clearing application cache..."
php artisan cache:clear
php artisan queue:restart

# Set proper permissions
print_status "Setting file permissions..."
chown -R forge:forge /home/forge/surrealpilot.com
chmod -R 755 /home/forge/surrealpilot.com
chmod -R 775 /home/forge/surrealpilot.com/storage
chmod -R 775 /home/forge/surrealpilot.com/bootstrap/cache

# Verify critical services
print_status "Verifying application health..."

# Check database connection
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database OK';" > /dev/null 2>&1; then
    print_status "Database connection: OK"
else
    print_error "Database connection: FAILED"
    exit 1
fi

# Check if Prism-PHP is configured
if php artisan tinker --execute="echo config('prism.default_provider');" > /dev/null 2>&1; then
    print_status "Prism-PHP configuration: OK"
else
    print_warning "Prism-PHP configuration: Check required"
fi

# Restart services
print_status "Restarting services..."
sudo supervisorctl restart all

print_status "Post-deployment tasks completed successfully!"
print_warning "Don't forget to:"
echo "1. Update environment variables in Forge panel"
echo "2. Configure SSL certificate"
echo "3. Set up monitoring and backups"
echo "4. Test critical functionality"