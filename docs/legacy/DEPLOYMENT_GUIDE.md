# Akuru Institute - cPanel Deployment Guide

**Domain:** akuru.edu.mv  
**Date:** October 15, 2025  
**Platform:** cPanel Shared Hosting

---

## 📋 Pre-Deployment Checklist

- [ ] cPanel access credentials
- [ ] FTP/SFTP credentials (or use cPanel File Manager)
- [ ] MySQL database created in cPanel
- [ ] Database user with full privileges
- [ ] SSL certificate (Let's Encrypt - free in cPanel)
- [ ] PHP 8.1+ enabled
- [ ] Composer available (or use local)

---

## 🚀 Deployment Steps

### Step 1: Prepare Production Files Locally

Run these commands on your local machine:

```bash
# Navigate to project
cd /Users/vigani/Website/Akuru/akuru-institute

# Install dependencies (production only)
composer install --no-dev --optimize-autoloader

# Build assets
npm run build

# Clear and optimize
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### Step 2: Create Production .env File

Create a file named `.env.production` with these settings:

```env
APP_NAME="Akuru Institute"
APP_ENV=production
APP_KEY=base64:YOUR_PRODUCTION_KEY_HERE
APP_DEBUG=false
APP_TIMEZONE=Indian/Maldives
APP_URL=https://akuru.edu.mv

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=akuru_edu_mv_db
DB_USERNAME=akuru_edu_mv_user
DB_PASSWORD=YOUR_SECURE_PASSWORD_HERE

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.akuru.edu.mv

MAIL_MAILER=smtp
MAIL_HOST=smtp.akuru.edu.mv
MAIL_PORT=587
MAIL_USERNAME=noreply@akuru.edu.mv
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@akuru.edu.mv
MAIL_FROM_NAME="${APP_NAME}"

# Firebase (if using push notifications)
FIREBASE_CREDENTIALS=
FIREBASE_DATABASE_URL=

# Social Login (optional)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://akuru.edu.mv/auth/google/callback

MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI=https://akuru.edu.mv/auth/microsoft/callback
```

**Important:** Generate a new APP_KEY for production:
```bash
php artisan key:generate --show
```

### Step 3: cPanel Setup

#### A. Create Database

1. Log in to cPanel at https://yourhostingpanel.com/cpanel
2. Go to **MySQL Databases**
3. Create new database: `akuru_edu_mv_db`
4. Create new user: `akuru_edu_mv_user` with strong password
5. Add user to database with **ALL PRIVILEGES**
6. Note down: database name, username, password

#### B. Set PHP Version

1. Go to **Select PHP Version** or **MultiPHP Manager**
2. Select your domain `akuru.edu.mv`
3. Choose **PHP 8.1** or **PHP 8.2** (not 8.4 - might not be available)
4. Enable these extensions:
   - ✅ mbstring
   - ✅ openssl
   - ✅ pdo
   - ✅ pdo_mysql
   - ✅ tokenizer
   - ✅ xml
   - ✅ ctype
   - ✅ json
   - ✅ bcmath
   - ✅ fileinfo
   - ✅ zip

### Step 4: Upload Files

#### Option A: Using cPanel File Manager (Easier)

1. **Compress project locally:**
   ```bash
   cd /Users/vigani/Website/Akuru
   zip -r akuru-institute.zip akuru-institute \
     -x "*.git*" \
     -x "*node_modules/*" \
     -x "*storage/logs/*" \
     -x "*.env"
   ```

2. **Upload via cPanel:**
   - Go to **File Manager**
   - Navigate to `public_html` (or your domain's root)
   - Upload `akuru-institute.zip`
   - Extract the archive
   - You should have: `public_html/akuru-institute/`

3. **Fix directory structure:**
   - Move everything from `akuru-institute/public/*` to `public_html/`
   - Keep Laravel files in `public_html/akuru-institute/`

#### Option B: Using FTP (Alternative)

1. Use FileZilla or Cyberduck
2. Connect to your server
3. Upload entire project to `/home/yourusername/akuru-institute/`
4. Upload contents of `public/` to `/home/yourusername/public_html/`

### Step 5: Configure File Structure

**Correct cPanel structure:**
```
/home/yourusername/
├── akuru-institute/           ← Laravel app (NOT public)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env                   ← Production .env here
│   └── artisan
└── public_html/               ← Document root
    ├── .htaccess
    ├── index.php              ← Modified Laravel public/index.php
    ├── favicon.ico
    └── build/                 ← Vite assets
```

### Step 6: Modify index.php

Edit `public_html/index.php` to point to Laravel:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../akuru-institute/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../akuru-institute/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../akuru-institute/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

### Step 7: Set Up .env File

1. Go to `File Manager` → `akuru-institute/`
2. Create `.env` file (copy from `.env.production`)
3. Update with cPanel database credentials
4. Save the file

### Step 8: Set Directory Permissions

In cPanel File Manager or via SSH:

```bash
# Storage and cache permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Make sure web server can write
chown -R yourusername:nobody storage
chown -R yourusername:nobody bootstrap/cache
```

Or just set permissions via File Manager:
- `storage/` folders → 775
- `bootstrap/cache/` → 775

### Step 9: Run Database Migration

#### Via SSH (if available):
```bash
cd /home/yourusername/akuru-institute
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

#### Via cPanel Terminal (if available):
- Go to **Terminal** in cPanel
- Run same commands as above

#### No SSH/Terminal? Use this PHP script:

Create `setup.php` in `public_html/`:

```php
<?php
// Temporary setup script - DELETE AFTER USE!

chdir(__DIR__ . '/../akuru-institute');
require __DIR__ . '/../akuru-institute/vendor/autoload.php';

$app = require_once __DIR__ . '/../akuru-institute/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "<pre>";
echo "Running migrations...\n";
$kernel->call('migrate', ['--force' => true]);

echo "\nRunning seeders...\n";
$kernel->call('db:seed', ['--force' => true]);

echo "\nCreating storage link...\n";
$kernel->call('storage:link');

echo "\nOptimizing...\n";
$kernel->call('optimize');

echo "\n✅ Setup complete! DELETE THIS FILE NOW!\n";
echo "</pre>";
```

Visit `https://akuru.edu.mv/setup.php`, then **DELETE IT IMMEDIATELY**.

### Step 10: Configure .htaccess

Make sure `public_html/.htaccess` contains:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Redirect to HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Redirect Trailing Slashes
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]
    
    # Send Requests To Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Step 11: Enable SSL (HTTPS)

1. Go to cPanel → **SSL/TLS Status**
2. Find `akuru.edu.mv`
3. Click **Run AutoSSL** (free Let's Encrypt)
4. Wait 2-5 minutes for certificate issuance
5. Your site will be available at `https://akuru.edu.mv`

### Step 12: Configure Cron Jobs (For Queue, Scheduling)

Go to **Cron Jobs** in cPanel:

```
* * * * * cd /home/yourusername/akuru-institute && php artisan schedule:run >> /dev/null 2>&1
```

This runs every minute and handles Laravel's scheduled tasks.

### Step 13: Final Checks

Visit these URLs to verify:

- ✅ https://akuru.edu.mv → Should show public homepage
- ✅ https://akuru.edu.mv/en → English version
- ✅ https://akuru.edu.mv/ar → Arabic version
- ✅ https://akuru.edu.mv/dv → Dhivehi version
- ✅ https://akuru.edu.mv/en/login → Login page
- ✅ https://akuru.edu.mv/en/dashboard → Dashboard (after login)

### Step 14: Post-Deployment

1. **Delete setup files:**
   - Remove `setup.php` if created
   - Remove any test files

2. **Monitor logs:**
   - Check `storage/logs/laravel.log` for errors

3. **Set up backups:**
   - cPanel → **Backup Wizard**
   - Schedule daily backups

4. **Performance:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 🔧 Troubleshooting

### Error 500 - Internal Server Error
1. Check `.env` file exists and is correct
2. Check file permissions (775 for storage/)
3. Check `storage/logs/laravel.log` for details
4. Make sure `index.php` path is correct

### Database Connection Error
1. Verify database credentials in `.env`
2. Make sure database user has privileges
3. Check if database exists

### CSS/JS Not Loading
1. Run `npm run build` locally
2. Re-upload `public/build/` folder
3. Clear browser cache
4. Check `.htaccess` file exists

### Can't Write to Storage
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Queue Jobs Not Running
Set up cron job (Step 12)

---

## 📞 Support

If you encounter issues:
1. Check `storage/logs/laravel.log`
2. Enable debug mode temporarily: `APP_DEBUG=true` in `.env`
3. Check cPanel error logs
4. Contact your hosting provider for server issues

---

## 🔐 Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong database password
- [ ] `.env` file is NOT in public_html
- [ ] SSL certificate active (HTTPS)
- [ ] `setup.php` deleted after use
- [ ] File permissions correct (not 777)
- [ ] Cron jobs configured
- [ ] Regular backups enabled

---

## 🎉 Congratulations!

Your Akuru Institute LMS should now be live at **https://akuru.edu.mv**!

**Default login:** Check `database/seeders/` for demo credentials.

