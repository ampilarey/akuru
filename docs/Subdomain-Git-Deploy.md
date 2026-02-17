# Deploy and update subdomain using Git

Use Git on the server so **local**, **live**, and **test subdomain** stay in sync. After any change, run `git pull` on the server instead of uploading a zip.

---

## Initial setup (one time)

### 1. Clone the repo on the server

SSH into the server, go to the parent of where you want the app, then clone:

```bash
cd /path/to/domains    # e.g. /home/akuru or /var/www
git clone https://github.com/ampilarey/akuru.git test.akuru.edu.mv
cd test.akuru.edu.mv
```

### 2. Create .env

```bash
cp .env.test-subdomain.example .env
# Edit .env: set DB_*, BML_API_KEY, APP_URL=https://test.akuru.edu.mv
```

### 3. Install dependencies and run migrations

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan config:cache
php artisan route:clear
chmod -R 775 storage bootstrap/cache
php artisan migrate --force
```

### 4. Document root and webhook

- Point **test.akuru.edu.mv** document root to `SUBDOMAIN_FOLDER/public`
- Set BML webhook to `https://test.akuru.edu.mv/webhooks/bml`

(Details in [Setup-Test-Subdomain.md](Setup-Test-Subdomain.md).)

---

## Updating after any change

Whenever you push changes from your computer to GitHub:

### 1. On your computer

```bash
git add .
git commit -m "Your changes"
git push origin main
```

### 2. On the server (test subdomain folder)

```bash
cd /path/to/test.akuru.edu.mv    # your subdomain folder

# Pull latest code
git pull origin main

# If composer.json or composer.lock changed
composer install --no-dev --optimize-autoloader

# If you use npm for frontend
# npm ci && npm run build

# If there are new migrations
php artisan migrate --force

# Rebuild caches (do NOT use route:cache - it breaks mcamara localized routes)
php artisan config:cache
php artisan route:clear
```

---

## Quick update script (optional)

Save as `scripts/update-subdomain.sh` on your computer and run it via SSH, or create a similar script on the server:

```bash
#!/bin/bash
# Run this inside the subdomain folder on the server
set -e
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:clear
echo "Subdomain updated."
```

On the server:

```bash
cd /path/to/test.akuru.edu.mv
chmod +x scripts/update-subdomain.sh
./scripts/update-subdomain.sh
```

---

## Checklist

| Step | Done |
|------|------|
| 1 | `git clone` into subdomain folder on server |
| 2 | `.env` from `.env.test-subdomain.example`, set DB and BML |
| 3 | `composer install --no-dev`, `artisan key:generate`, `config:cache`, `migrate` |
| 4 | Document root → `SUBDOMAIN_FOLDER/public` |
| 5 | For updates: `git pull` → `composer install` (if needed) → `migrate` → `config:cache` |
