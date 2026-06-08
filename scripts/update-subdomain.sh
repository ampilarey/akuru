#!/usr/bin/env bash
# Deploy latest main to test.akuru.edu.mv (same steps as DEPLOYMENT.md §6.1).
# On the server: bash scripts/update-subdomain.sh
# Or paste the one-liner from DEPLOYMENT.md into cPanel Terminal after git push.
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
