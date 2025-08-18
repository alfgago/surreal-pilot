#!/bin/bash

# SurrealPilot Production Deployment Script
# This script handles deployment to Laravel Forge servers

set -e

echo "🚀 Starting SurrealPilot Production Deployment..."

# Configuration
FORGE_SERVER="surrealpilot.com"
DEPLOY_PATH="/home/forge/surrealpilot.com"
BACKUP_PATH="/home/forge/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Pre-deployment checks
print_status "Running pre-deployment checks..."

# Check if we're on the main branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "main" ]; then
    print_error "Not on main branch. Current branch: $CURRENT_BRANCH"
    exit 1
fi

# Check if working directory is clean
if [ -n "$(git status --porcelain)" ]; then
    print_error "Working directory is not clean. Please commit or stash changes."
    exit 1
fi

# Run tests
print_status "Running test suite..."
if ! php artisan test --stop-on-failure; then
    print_error "Tests failed. Deployment aborted."
    exit 1
fi

# Build assets
print_status "Building production assets..."
npm ci
npm run build

# Create deployment package
print_status "Creating deployment package..."
PACKAGE_NAME="surrealpilot-${TIMESTAMP}.tar.gz"
tar -czf "$PACKAGE_NAME" \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='.env' \
    --exclude='.env.local' \
    .

print_status "Package created: $PACKAGE_NAME"

# Deploy to Forge (this would typically be handled by Forge's deployment script)
print_status "Deployment package ready for Forge deployment"
print_warning "Manual steps required:"
echo "1. Upload $PACKAGE_NAME to Forge server"
echo "2. Extract to deployment directory"
echo "3. Run post-deployment script on server"

# Cleanup
rm "$PACKAGE_NAME"

print_status "Deployment preparation completed successfully!"