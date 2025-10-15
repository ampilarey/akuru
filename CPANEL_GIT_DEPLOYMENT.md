# Deploy Akuru Institute Using Git in cPanel

**Domain:** akuru.edu.mv  
**Method:** Git Version Control (Recommended for easy updates)

---

## 🎯 Why Use Git in cPanel?

**Benefits:**
- ✅ Easy updates: Just `git pull` to update
- ✅ Version control: Track all changes
- ✅ Rollback: Revert if something breaks
- ✅ No FTP/file uploads needed
- ✅ Faster deployment

---

## 📋 Prerequisites

Before starting:
- [ ] cPanel cleaned up (see `CPANEL_CLEANUP_CHECKLIST.md`)
- [ ] Git repository ready on GitHub
- [ ] SSH access to cPanel (or use cPanel Git interface)

---

## 🔧 Method 1: Using cPanel Git Interface (Easiest)

### Step 1: Generate SSH Key in cPanel

1. Log in to cPanel
2. Go to **SSH Access** → **Manage SSH Keys**
3. Click **Generate a New Key**
   - **Key Name:** `github_akuru`
   - **Key Password:** (optional, leave empty for no password)
   - **Key Type:** RSA
   - **Key Size:** 4096
4. Click **Generate Key**
5. Click **Go Back**
6. Find your new key → Click **Manage** → **Authorize**
7. Click **View/Download** → Copy the **Public Key** (starts with `ssh-rsa`)

### Step 2: Add SSH Key to GitHub

1. Go to https://github.com/ampilarey/akuru
2. Click **Settings** (repository settings)
3. Click **Deploy keys** (left sidebar)
4. Click **Add deploy key**
   - **Title:** `cPanel akuru.edu.mv`
   - **Key:** Paste the public key from Step 1
   - ✅ Check **Allow write access** (if you want to push from server)
5. Click **Add key**

### Step 3: Clone Repository in cPanel

1. In cPanel, go to **Git™ Version Control**
2. Click **Create**
3. Fill in details:
   - **Clone URL:** `git@github.com:ampilarey/akuru.git`
   - **Repository Path:** `/home/yourusername/akuru-institute`
   - **Repository Name:** `akuru-institute`
4. Click **Create**
5. Wait for cloning to complete

### Step 4: Set Up the Application

Open **Terminal** in cPanel and run:

```bash
cd /home/yourusername/akuru-institute

# Copy environment file
cp .env.example .env

# Install dependencies (if Composer is available)
composer install --no-dev --optimize-autoloader

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed database
php artisan db:seed --force

# Create storage link
php artisan storage:link

# Build assets (if npm is available)
npm install
npm run build

# Optimize
php artisan optimize
```

### Step 5: Configure Document Root

**Option A: Using cPanel Domain Manager**
1. Go to **Domains**
2. Find `akuru.edu.mv`
3. Click **Manage**
4. Change **Document Root** to: `/home/yourusername/akuru-institute/public`
5. Click **Change**

**Option B: Using Symlink**
```bash
# Remove current public_html
rm -rf /home/yourusername/public_html

# Create symlink
ln -s /home/yourusername/akuru-institute/public /home/yourusername/public_html
```

---

## 🔧 Method 2: Using SSH/Terminal (More Control)

### Step 1: Set Up SSH Access

1. In cPanel, go to **SSH Access**
2. Click **Manage SSH Keys**
3. Generate key (or use existing)
4. Add to GitHub (same as Method 1, Step 2)

### Step 2: Connect via SSH

```bash
# From your Mac
ssh yourusername@yourhostname.com
# or
ssh yourusername@akuru.edu.mv
```

### Step 3: Clone Repository

```bash
cd ~
git clone git@github.com:ampilarey/akuru.git akuru-institute
cd akuru-institute
```

### Step 4: Set Up Environment

```bash
# Copy .env
cp .env.example .env
nano .env  # Edit database credentials
```

Edit `.env` with your cPanel database details:
```env
DB_DATABASE=akuru_edu_mv_db
DB_USERNAME=akuru_edu_mv_user
DB_PASSWORD=your_password_here
```

### Step 5: Install & Deploy

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate --force
php artisan db:seed --force

# Create storage link
php artisan storage:link

# Build assets (if Node.js available)
npm install
npm run build

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Step 6: Set Permissions

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Step 7: Configure Web Root

```bash
# Option 1: Symlink
rm -rf ~/public_html
ln -s ~/akuru-institute/public ~/public_html

# Option 2: Update via cPanel Domains (see Method 1, Step 5)
```

---

## 🔄 Updating Your Site (After Initial Deployment)

### Via cPanel Git Interface:

1. Go to **Git™ Version Control**
2. Find your repository
3. Click **Manage**
4. Click **Pull or Deploy** → **Update from Remote**
5. Done!

### Via SSH/Terminal:

```bash
cd ~/akuru-institute
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

**Create an update script** (`update.sh`):

```bash
#!/bin/bash
cd ~/akuru-institute
echo "📥 Pulling latest changes..."
git pull origin main

echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "🗄️  Running migrations..."
php artisan migrate --force

echo "🏗️  Building assets..."
npm run build

echo "⚡ Optimizing..."
php artisan optimize

echo "✅ Update complete!"
```

Make it executable:
```bash
chmod +x update.sh
```

Then just run `./update.sh` whenever you need to update!

---

## 🔐 Security Best Practices

### 1. Protect .env File

Add to `.htaccess` in `public/`:
```apache
<Files .env>
    Order allow,deny
    Deny from all
</Files>
```

### 2. Set Proper Permissions

```bash
# Files: 644
find ~/akuru-institute -type f -exec chmod 644 {} \;

# Directories: 755
find ~/akuru-institute -type d -exec chmod 755 {} \;

# Storage & cache: 775
chmod -R 775 ~/akuru-institute/storage
chmod -R 775 ~/akuru-institute/bootstrap/cache
```

### 3. Enable HTTPS

1. Go to **SSL/TLS Status**
2. Run AutoSSL for `akuru.edu.mv`
3. Force HTTPS in `.htaccess`:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## ⚙️ Set Up Automated Deployments (Advanced)

### Option 1: GitHub Webhooks

1. In GitHub repository → **Settings** → **Webhooks**
2. Click **Add webhook**
3. **Payload URL:** `https://akuru.edu.mv/deploy-webhook.php`
4. **Content type:** `application/json`
5. **Secret:** Generate a random string
6. **Events:** Just the push event
7. Click **Add webhook**

Create `public/deploy-webhook.php`:
```php
<?php
$secret = 'your-secret-here';
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$payload = file_get_contents('php://input');
$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($signature, $hash)) {
    die('Invalid signature');
}

// Execute deployment
$output = shell_exec('cd ~/akuru-institute && ./update.sh 2>&1');
echo $output;
```

### Option 2: Cron-based Auto-Pull

Add to **Cron Jobs**:
```bash
*/15 * * * * cd ~/akuru-institute && git pull origin main && php artisan optimize
```

This checks for updates every 15 minutes.

---

## 🧪 Testing Deployment

### Test these URLs:
- ✅ https://akuru.edu.mv
- ✅ https://akuru.edu.mv/en
- ✅ https://akuru.edu.mv/ar
- ✅ https://akuru.edu.mv/dv
- ✅ https://akuru.edu.mv/en/login

### Check logs:
```bash
tail -f ~/akuru-institute/storage/logs/laravel.log
```

---

## 🆘 Troubleshooting

### Git clone fails: "Permission denied"
- Make sure SSH key is added to GitHub
- Check key is authorized in cPanel

### Composer not found
- Use absolute path: `/usr/local/bin/composer` or `/usr/bin/composer`
- Or upload `vendor/` folder via FTP

### npm not available
- Build assets locally (`npm run build`)
- Upload `public/build/` folder manually

### 500 Error after deployment
1. Check `.env` file exists and is correct
2. Run `php artisan config:clear`
3. Check `storage/logs/laravel.log`
4. Verify file permissions

### Storage link fails
- Create manually: `ln -s ~/akuru-institute/storage/app/public ~/akuru-institute/public/storage`

---

## 📊 Deployment Workflow Summary

```
Local Development
       ↓ (git push)
    GitHub
       ↓ (git pull)
  cPanel Server
       ↓ (artisan commands)
  Live Site
```

---

## ✅ Final Checklist

After Git deployment:
- [ ] Git repository cloned successfully
- [ ] `.env` configured with correct database credentials
- [ ] Dependencies installed (composer, npm)
- [ ] Database migrated and seeded
- [ ] Storage linked
- [ ] Assets built
- [ ] Document root pointing to `public/`
- [ ] SSL enabled (HTTPS)
- [ ] Cron jobs configured
- [ ] Site loads correctly
- [ ] Can log in as admin
- [ ] Update script created

---

## 🎉 Success!

Your Akuru Institute LMS is now deployed via Git!

**To update in the future:**
```bash
ssh user@akuru.edu.mv
cd ~/akuru-institute
./update.sh
```

Or use cPanel Git interface: **Pull or Deploy** → **Update from Remote**

---

**Need help?** Check `DEPLOYMENT_GUIDE.md` for more details!

