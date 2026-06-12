# 🚀 Akuru Institute - Quick Deployment to cPanel

**Target:** akuru.edu.mv  
**Platform:** cPanel Hosting  
**Time:** ~30-45 minutes

---

## 📦 Option 1: Automated Deployment (Recommended)

### Run the preparation script:

```bash
cd /Users/vigani/Website/Akuru/akuru-institute
./deploy-prepare.sh
```

This will:
- ✅ Install production dependencies
- ✅ Build assets
- ✅ Clear caches
- ✅ Generate production .env
- ✅ Create deployment .zip file

**Output:** `akuru-deploy-YYYYMMDD-HHMMSS.zip` in `/Users/vigani/Website/Akuru/`

---

## 📤 Upload to cPanel

1. **Log in to cPanel**
   - URL: Your hosting provider's cPanel URL
   - Username: Your cPanel username
   - Password: Your cPanel password

2. **Go to File Manager**
   - Click on **File Manager** icon

3. **Upload & Extract**
   - Navigate to your home directory (not public_html yet!)
   - Click **Upload**
   - Upload the `akuru-deploy-*.zip` file
   - Right-click → **Extract**
   - You should now have `/home/yourusername/akuru-institute/`

---

## 🗄️ Create Database

1. **Go to MySQL Databases** in cPanel

2. **Create Database:**
   - Database name: `akuru_edu_mv_db` (or similar)
   - Click **Create Database**

3. **Create User:**
   - Username: `akuru_edu_mv_user`
   - Password: Generate a strong password (save it!)
   - Click **Create User**

4. **Add User to Database:**
   - User: `akuru_edu_mv_user`
   - Database: `akuru_edu_mv_db`
   - Privileges: **ALL PRIVILEGES**
   - Click **Add**

---

## ⚙️ Configure Environment

1. **Go to File Manager** → `akuru-institute/`

2. **Edit `.env` file** (or create from `.env.production`):
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://akuru.edu.mv
   
   DB_DATABASE=akuru_edu_mv_db
   DB_USERNAME=akuru_edu_mv_user
   DB_PASSWORD=your_strong_password_here
   ```

3. **Save the file**

---

## 🌐 Set Up Document Root

### Method 1: Subdomain Setup (Recommended)
1. Go to **Subdomains** or **Addon Domains**
2. Set Document Root to: `/home/yourusername/akuru-institute/public`
3. Done!

### Method 2: Move Files to public_html
1. Copy contents of `akuru-institute/public/*` to `public_html/`
2. Edit `public_html/index.php`:
   ```php
   require __DIR__.'/../akuru-institute/vendor/autoload.php';
   (require_once __DIR__.'/../akuru-institute/bootstrap/app.php')
       ->handleRequest(Request::capture());
   ```

---

## 🚀 Initialize Application

### If you have SSH/Terminal access:

```bash
cd /home/yourusername/akuru-institute
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

### If NO SSH access:

1. Create `setup.php` in `public_html/`:
```php
<?php
chdir(__DIR__ . '/../akuru-institute');
require __DIR__ . '/../akuru-institute/vendor/autoload.php';
$app = require_once __DIR__ . '/../akuru-institute/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "<pre>Running setup...\n";
$kernel->call('migrate', ['--force' => true]);
$kernel->call('db:seed', ['--force' => true]);
$kernel->call('storage:link');
$kernel->call('optimize');
echo "\n✅ Done! DELETE THIS FILE NOW!</pre>";
```

2. Visit `https://akuru.edu.mv/setup.php`
3. **DELETE setup.php immediately after**

---

## 🔒 Enable SSL

1. Go to **SSL/TLS Status** in cPanel
2. Find `akuru.edu.mv`
3. Click **Run AutoSSL**
4. Wait 2-5 minutes
5. ✅ Your site is now HTTPS!

---

## ⏰ Set Up Cron Job

1. Go to **Cron Jobs** in cPanel
2. Add new cron job:
   - **Minute:** `*`
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** `cd /home/yourusername/akuru-institute && php artisan schedule:run >> /dev/null 2>&1`

---

## ✅ Verify Deployment

Visit these URLs:
- https://akuru.edu.mv → Homepage ✅
- https://akuru.edu.mv/en → English ✅
- https://akuru.edu.mv/ar → Arabic ✅
- https://akuru.edu.mv/dv → Dhivehi ✅
- https://akuru.edu.mv/en/login → Login ✅

---

## 🔐 Default Admin Login

After seeding, use these credentials:

**Admin:**
- Email: `admin@akuru.edu.mv`
- Password: Check `DatabaseSeeder.php` or `UsersTableSeeder.php`

**Change password immediately after first login!**

---

## 🆘 Troubleshooting

### 500 Error
- Check `storage/logs/laravel.log`
- Verify `.env` database credentials
- Set permissions: `chmod -R 775 storage bootstrap/cache`

### CSS/JS Not Loading
- Make sure `public/build/` folder exists
- Check `.htaccess` file
- Clear browser cache

### Database Connection Failed
- Verify database name, username, password in `.env`
- Check database user has privileges

---

## 📞 Need Help?

Full guide: `DEPLOYMENT_GUIDE.md`  
Check logs: `storage/logs/laravel.log`  
Contact hosting support for server issues

---

## 🎉 Success!

Your Akuru Institute LMS is now live at:
**https://akuru.edu.mv**

Celebrate! 🎊

