# Upload production files to subdomain folder (test.akuru.edu.mv)

Use this to get your Laravel app files onto the server in the subdomain folder.

---

## 1. Choose the subdomain folder on the server

On your host, the subdomain usually has its own folder, for example:

- **cPanel:** `public_html/test` or `test.akuru.edu.mv` or `domains/test.akuru.edu.mv/public_html`
- **Direct server:** `/var/www/test.akuru.edu.mv` or `/home/username/test.akuru.edu.mv`

Find this path in your hosting panel (Subdomains / Domains) or ask your host. We’ll call it **SUBDOMAIN_FOLDER**.

---

## 2. What to upload

Upload the **whole Laravel project** into SUBDOMAIN_FOLDER so that the structure looks like:

```
SUBDOMAIN_FOLDER/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          ← later the web document root will point HERE
├── resources/
├── routes/
├── storage/
├── composer.json
├── artisan
└── ... (all other project files)
```

**Do not upload** (or delete after upload if present):

- `vendor/` — install on server with Composer
- `node_modules/` — install on server if you use npm
- `.env` — create on server from `.env.example` (see step 4)
- `.git/` — optional; omit if you don’t need git on server

---

## 3. How to upload

### Option A: Upload a ZIP (FTP/SFTP/File Manager)

1. **On your computer**, in the project root:
   - Create a ZIP of the whole project **excluding** `vendor`, `node_modules`, `.env`, and `.git`.
   - Or run (from project root):
     ```bash
     zip -r akuru-deploy.zip . -x "vendor/*" -x "node_modules/*" -x ".env" -x ".git/*"
     ```
2. Upload **akuru-deploy.zip** to SUBDOMAIN_FOLDER.
3. On the server, **unzip** inside SUBDOMAIN_FOLDER so that `public`, `app`, `composer.json`, etc. are directly inside SUBDOMAIN_FOLDER (not inside an extra subfolder).
4. Remove the ZIP file from the server if you want.

### Option B: Git (if the server has Git and your repo is online)

1. SSH into the server and go to the parent of SUBDOMAIN_FOLDER, e.g.:
   ```bash
   cd /var/www
   ```
2. Clone into the subdomain folder (use your real repo URL):
   ```bash
   git clone https://github.com/your-org/akuru-institute.git test.akuru.edu.mv
   ```
3. Then:
   ```bash
   cd test.akuru.edu.mv
   composer install --no-dev --optimize-autoloader
   # If you use Laravel Mix/Vite for frontend:
   # npm ci && npm run build
   ```

---

## 4. .env on the server

- **Do not** upload your local `.env` (it has local DB and paths).
- On the server, inside SUBDOMAIN_FOLDER:
  - Copy from example:  
    `cp .env.example .env`
  - Edit `.env` and set at least:
    - `APP_ENV=staging` or `production`
    - `APP_URL=https://test.akuru.edu.mv`
    - `BML_WEBHOOK_URL=https://test.akuru.edu.mv/webhooks/bml`
    - Database: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
    - BML: `BML_APP_ID`, `BML_API_KEY`, and the rest from your BML UAT setup

---

## 5. After upload – run on the server

SSH into the server, then:

```bash
cd SUBDOMAIN_FOLDER

# Install PHP dependencies (production, no dev packages)
composer install --no-dev --optimize-autoloader

# If you use npm for frontend assets
npm ci
npm run build

# Generate app key if .env was new
php artisan key:generate

# Cache config and routes
php artisan config:cache
php artisan route:cache

# Storage permissions (use the user that runs the web server)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Optional: create storage link if not exists
php artisan storage:link
```

---

## 6. Point the subdomain to `public`

In your hosting panel or LiteSpeed config, set the **document root** for **test.akuru.edu.mv** to:

**SUBDOMAIN_FOLDER/public**

Example: `/var/www/test.akuru.edu.mv/public`.  
Then the site will run Laravel instead of showing a directory list. See **docs/Setup-Test-Subdomain.md** for LiteSpeed details.

---

## Checklist

| Step | Done |
|------|------|
| 1 | Know SUBDOMAIN_FOLDER path on server |
| 2 | Upload project files (ZIP or git clone) into SUBDOMAIN_FOLDER |
| 3 | Create `.env` from `.env.example` and set APP_URL, BML_*, DB_* |
| 4 | Run `composer install --no-dev` (and npm build if needed) |
| 5 | Run `php artisan key:generate` and `config:cache`, `route:cache` |
| 6 | Set storage/cache permissions |
| 7 | Set document root to SUBDOMAIN_FOLDER/public |

After this, open **https://test.akuru.edu.mv** to confirm the app loads.
