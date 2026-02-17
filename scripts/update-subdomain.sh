#!/usr/bin/env bash
# Run this script on the server, inside the subdomain folder (test.akuru.edu.mv)
# After you push changes: SSH in, cd to the subdomain folder, then ./scripts/update-subdomain.sh
set -e

cd "$(dirname "$0")/.."

echo "Pulling latest code..."
git pull origin main

echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Running migrations..."
php artisan migrate --force

echo "Rebuilding caches..."
php artisan config:cache
# route:cache breaks mcamara localized routes; clear so routes load correctly
php artisan route:clear

echo "Subdomain updated successfully."
