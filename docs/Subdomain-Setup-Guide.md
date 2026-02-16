# Complete guide: Set up test.akuru.edu.mv subdomain

Follow these steps **in order**. Do each step before moving to the next.

---

## PART 1 – On your computer (prepare the files)

### Step 1: Create the deployment ZIP

1. Open **Terminal** (or Command Prompt).
2. Go to your project folder:
   ```bash
   cd /path/to/akuru-institute
   ```
   (Replace with your real path, e.g. `cd ~/Website/Akuru/akuru-institute`.)

3. Run the build script:
   ```bash
   bash scripts/build-deploy-zip.sh
   ```
   If that fails, run this instead:
   ```bash
   zip -r akuru-deploy.zip . -x "vendor/*" -x "node_modules/*" -x ".env*" -x ".git/*" -x "*.log"
   ```

4. You should see a new file like **akuru-deploy-20250212-143000.zip** (date/time in the name). This is the file you will upload.

---

## PART 2 – On the server (upload and install)

### Step 2: Find your subdomain folder

You need the **folder on the server** that is used for **test.akuru.edu.mv**.

- **cPanel:** Go to **Subdomains**. Find `test.akuru.edu.mv`. Note the **Document Root** (e.g. `test` or `test.akuru.edu.mv`). The full path is often:
  - `~/public_html/test` or
  - `~/test.akuru.edu.mv` or
  - `~/domains/test.akuru.edu.mv/public_html`
- **Plesk:** Domains → test.akuru.edu.mv → Hosting & DNS. Note the **Document root** path.
- **Direct server / SSH:** Often `/var/www/test.akuru.edu.mv` or `/home/username/test.akuru.edu.mv`.

We’ll call this folder **SUBDOMAIN_FOLDER**. Write it down: _________________

---

### Step 3: Upload the ZIP

1. Use **File Manager** (cPanel/Plesk) or **FTP/SFTP** (FileZilla, etc.).
2. Go to **SUBDOMAIN_FOLDER**.  
   If the subdomain is new, the folder might be empty or have a default page.
3. Upload **akuru-deploy.zip** (or the zip you created in Step 1) into this folder.
4. Wait until the upload finishes.

---

### Step 4: Unzip and check the structure

1. In File Manager, **right‑click** the ZIP file → **Extract** (or use “Extract” in the toolbar).
2. Extract **into the same folder** (SUBDOMAIN_FOLDER).
3. After extraction, inside SUBDOMAIN_FOLDER you must see:
   - **public** (folder)
   - **app** (folder)
   - **config** (folder)
   - **composer.json** (file)
   - **artisan** (file)
   - and other Laravel files.

   If you see **one folder** (e.g. `akuru-institute`) and the Laravel files are inside it, then:
   - Move everything from inside that inner folder **up** into SUBDOMAIN_FOLDER.
   - Delete the now‑empty inner folder.

4. Optional: Delete **akuru-deploy.zip** from the server to save space.

---

### Step 5: Create .env on the server

1. In SUBDOMAIN_FOLDER, find the file **.env.test-subdomain.example**.
2. **Copy** it and name the copy **.env**.
   - In cPanel File Manager: Copy, then rename to `.env`.
   - Or via SSH: `cp .env.test-subdomain.example .env`
3. **Edit .env** and set these (replace with your real values):

   | Variable       | What to put |
   |----------------|-------------|
   | `APP_KEY`      | Leave empty for now; we’ll generate it in Step 6. |
   | `DB_DATABASE`  | Your database name for this subdomain. |
   | `DB_USERNAME`  | Database user. |
   | `DB_PASSWORD`  | Database password. |
   | `BML_API_KEY`  | Your BML API Key (secret) from the BML portal. |

   Save the file.

---

### Step 6: Run Composer and Artisan (SSH or terminal on server)

You need **SSH** or a **Terminal** in your hosting panel.

1. Connect to the server (SSH or “Terminal” in cPanel/Plesk).
2. Go to the subdomain folder:
   ```bash
   cd SUBDOMAIN_FOLDER
   ```
   Example: `cd ~/public_html/test` or `cd /var/www/test.akuru.edu.mv`.

3. Install PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   If `composer` is not found, use: `php /path/to/composer.phar install --no-dev --optimize-autoloader` (ask your host for Composer path).

4. Generate app key and cache config:
   ```bash
   php artisan key:generate
   php artisan config:cache
   php artisan route:cache
   ```

5. Storage permissions (use the user that runs the web server; often `www-data` or your cPanel user):
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```
   If you don’t have `chown` or use a different user, your host’s docs will say (e.g. `nobody`, or skip `chown` and only use `chmod`).

6. Optional – if you use npm for frontend:
   ```bash
   npm ci
   npm run build
   ```

7. Run migrations (create tables):
   ```bash
   php artisan migrate --force
   ```

---

### Step 7: Point the subdomain to the `public` folder

Right now the site might show “Index of /” or a blank page because the **document root** is not the Laravel **public** folder.

1. **cPanel:** Subdomains → click **test.akuru.edu.mv** → change **Document Root** to:
   - `public_html/test/public`  
   or whatever path equals **SUBDOMAIN_FOLDER/public**.
2. **Plesk:** Domains → test.akuru.edu.mv → Hosting → Document root: set to `.../public`.
3. **Direct server (e.g. Apache/Nginx/LiteSpeed):** Set the vhost **DocumentRoot** to **SUBDOMAIN_FOLDER/public**. Example: `/var/www/test.akuru.edu.mv/public`.

4. Restart or reload the web server if needed.

---

### Step 8: Set BML webhook URL

1. Log in to **BML Merchant Dashboard (UAT)**.
2. Find **Webhooks** or **API / Callback URL**.
3. Set **Webhook URL** to:
   ```text
   https://test.akuru.edu.mv/webhooks/bml
   ```
4. Save.

---

## PART 3 – Check that it works

### Step 9: Test the site

1. Open **https://test.akuru.edu.mv** in your browser.
2. You should see the **Akuru Institute** site (not “Index of /” or an error).
3. Go to a course with a fee → Checkout → accept terms → Pay. You should be sent to BML’s payment page. After paying with a test card, you should return to your site and see success (or “Payment processing” then success).
4. Check logs: `storage/logs/payments-*.log` on the server for BML and webhook lines.

---

## Quick checklist

| # | Step | Done |
|---|------|------|
| 1 | Create ZIP on your computer (`bash scripts/build-deploy-zip.sh`) | ☐ |
| 2 | Find SUBDOMAIN_FOLDER on server | ☐ |
| 3 | Upload ZIP to SUBDOMAIN_FOLDER | ☐ |
| 4 | Unzip and ensure `public/`, `app/`, `composer.json` are in SUBDOMAIN_FOLDER | ☐ |
| 5 | Copy `.env.test-subdomain.example` to `.env` and set DB_* and BML_API_KEY | ☐ |
| 6 | Run `composer install --no-dev`, `artisan key:generate`, `config:cache`, `migrate`, permissions | ☐ |
| 7 | Set document root to SUBDOMAIN_FOLDER/public | ☐ |
| 8 | Set BML webhook URL to https://test.akuru.edu.mv/webhooks/bml | ☐ |
| 9 | Open https://test.akuru.edu.mv and test a payment | ☐ |

---

## If something goes wrong

- **Blank page or 500:** Check `storage/logs/laravel.log` on the server. Ensure `storage` and `bootstrap/cache` are writable (775 and correct owner).
- **“Index of /”:** Document root is still the subdomain folder, not the **public** subfolder. Change it to SUBDOMAIN_FOLDER/public.
- **Webhook not received:** Confirm BML webhook URL is exactly `https://test.akuru.edu.mv/webhooks/bml` and that the site is reachable from the internet. Check `storage/logs/payments-*.log`.
