# Setting up Laravel on test.akuru.edu.mv (LiteSpeed)

Use this when deploying the app to **https://test.akuru.edu.mv** for payment testing. The "Index of /" page means the server is not yet pointing to Laravel's `public` folder.

---

## 1. Deploy the Laravel app on the server

- Upload/copy the project to the server (e.g. `/home/akuru/akuru-institute` or `/var/www/test.akuru.edu.mv`).
- Or use Git: `git clone ...` into that directory.
- Ensure the **document root** will be the `public` folder inside the project (see step 2).

---

## 2. Point the subdomain to Laravel (LiteSpeed)

The site must be served from the **`public`** directory of your Laravel app, not the project root.

### Option A: LiteSpeed / cPanel virtual host

1. Create a **domain or subdomain**: `test.akuru.edu.mv`.
2. Set **Document Root** to the Laravel `public` folder, for example:
   - ` /home/akuru/akuru-institute/public`
   - or ` /var/www/test.akuru.edu.mv/public`
3. Ensure **AllowOverride** is enabled (e.g. **All**) so Laravel's `public/.htaccess` works.

### Option B: LiteSpeed vhost config (direct edit)

If you edit the server config by hand, the vhost for `test.akuru.edu.mv` should look like:

```apache
<VirtualHost *:443>
    ServerName test.akuru.edu.mv
    DocumentRoot /path/to/akuru-institute/public

    <Directory /path/to/akuru-institute/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # SSL directives (SSLEngine, certificates) as per your setup
</VirtualHost>
```

Replace `/path/to/akuru-institute` with the real path to the project. Restart LiteSpeed after changes.

---

## 3. .env on the server

On the **server**, use a `.env` that includes at least:

```env
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://test.akuru.edu.mv

BML_BASE_URL=https://api.uat.merchants.bankofmaldives.com.mv/public
BML_APP_ID=your_app_id
BML_API_KEY=your_api_key
BML_WEBHOOK_URL=https://test.akuru.edu.mv/webhooks/bml
# ... rest of BML and DB/config
```

Run:

```bash
cd /path/to/akuru-institute
php artisan config:cache
php artisan route:cache
```

---

## 4. Permissions and storage

```bash
cd /path/to/akuru-institute
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

(Use the user/group your LiteSpeed runs as if different from `www-data`.)

---

## 5. BML UAT portal

- Set **Webhook URL** to: **https://test.akuru.edu.mv/webhooks/bml**
- If there is an allowed redirect domains list, add **test.akuru.edu.mv**

---

## 6. Check

- Open **https://test.akuru.edu.mv** — you should see the Laravel app, not "Index of /".
- Run a test payment and check `storage/logs/payments-*.log` for BML webhook hits.

---

## Quick checklist

| Step | Action |
|------|--------|
| 1 | Deploy Laravel to server |
| 2 | Set document root to `.../akuru-institute/public` for test.akuru.edu.mv |
| 3 | .env with APP_URL and BML_WEBHOOK_URL = https://test.akuru.edu.mv |
| 4 | `php artisan config:cache` and fix storage/cache permissions |
| 5 | BML UAT webhook URL = https://test.akuru.edu.mv/webhooks/bml |
