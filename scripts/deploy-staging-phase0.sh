#!/usr/bin/env bash
# Phase 0 staging deploy for test.akuru.edu.mv
#
# Run from the app root on the server (wherever the git checkout lives), e.g.:
#   cd ~/test.akuru.edu.mv && bash scripts/deploy-staging-phase0.sh
#
# "akuruedu" in docs is the cPanel account name (/home/akuruedu/...), not a repo path.
# Expected commit after pull: e84c657 (or newer approved main)
# Do NOT use route:cache — breaks mcamara localized routes (see DEPLOYMENT.md §6.1).

set -euo pipefail

EXPECTED_COMMIT="${EXPECTED_COMMIT:-e84c657}"
PHP="${PHP:-php}"

cd "$(dirname "$0")/.."
APP_DIR="$(pwd)"

echo "==> Pre-flight"
hostname
grep -E '^APP_URL=|^APP_ENV=|^DB_DATABASE=|^BML_ENVIRONMENT=' .env | sed 's/=.*/=***redacted***/' || true
echo "Current commit: $(git rev-parse --short HEAD)"
git status --short
if [ -n "$(git status --porcelain)" ]; then
  echo "ERROR: Uncommitted server-side changes. Stop and report." >&2
  exit 1
fi

echo "==> Backup .env"
cp -a .env ".env.backup.$(date +%Y%m%d-%H%M%S)"

echo "==> Backup database (adjust DB name/user from .env)"
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
BACKUP_FILE="storage/backups/staging-${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"
mkdir -p storage/backups
mysqldump -u "$DB_USER" -p "$DB_NAME" | gzip > "$BACKUP_FILE"
echo "Database backup: $BACKUP_FILE"

echo "==> Pull Phase 0 code"
git fetch origin
git checkout main
git pull origin main
DEPLOYED=$(git rev-parse HEAD)
echo "Deployed commit: $DEPLOYED"
if [ "$(git rev-parse --short HEAD)" != "$(echo "$EXPECTED_COMMIT" | cut -c1-7)" ]; then
  echo "NOTE: HEAD is not $EXPECTED_COMMIT — confirm this is an approved newer main commit."
fi

echo "==> Composer"
$PHP "$(which composer)" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Clear caches before migrate"
$PHP artisan optimize:clear

echo "==> Migration status"
$PHP artisan migrate:status

echo "==> Migrate"
$PHP artisan migrate --force

echo "==> Frontend build"
if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
else
  echo "WARN: npm not found — skip asset build or run manually."
fi

echo "==> Rebuild caches (route:clear, NOT route:cache)"
$PHP artisan config:cache
$PHP artisan route:clear
$PHP artisan view:cache

echo "==> Restart queue workers if used"
$PHP artisan queue:restart || true

echo "==> Post-deploy verification"
$PHP artisan route:list | tail -5
$PHP artisan test 2>&1 | tail -5 || echo "WARN: artisan test failed — check DB test config on server"

echo "==> Done. Deployed: $DEPLOYED"
echo "Smoke test: https://test.akuru.edu.mv/en and /inertia-test"
