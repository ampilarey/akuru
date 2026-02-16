#!/usr/bin/env bash
# Build a zip of the Laravel app for uploading to test.akuru.edu.mv (excludes vendor, node_modules, .env, .git)
set -e
cd "$(dirname "$0")/.."
OUT="akuru-deploy-$(date +%Y%m%d-%H%M).zip"
zip -r "$OUT" . \
  -x "vendor/*" \
  -x "node_modules/*" \
  -x ".env" \
  -x ".env.backup" \
  -x ".env.production" \
  -x ".git/*" \
  -x "*.log" \
  -x "storage/logs/*" \
  -x "storage/framework/cache/data/*" \
  -x "storage/framework/sessions/*" \
  -x "storage/framework/views/*" \
  -x ".phpunit.cache/*" \
  -x ".cursor/*"
echo "Created: $OUT"
echo "Upload this file to your subdomain folder on the server, then unzip it there."
