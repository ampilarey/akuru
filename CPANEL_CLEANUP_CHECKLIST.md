# cPanel Cleanup Checklist - Before Deploying Akuru Institute

**Domain:** akuru.edu.mv  
**Purpose:** Clean cPanel before fresh Laravel deployment

---

## 🧹 Step-by-Step Cleanup Guide

### Step 1: Backup Current Files (If Any)

**Before deleting anything, backup existing files:**

1. Log in to cPanel
2. Go to **Backup** or **Backup Wizard**
3. Click **Download a Full Account Backup** or **Download a Home Directory Backup**
4. Wait for backup to complete
5. Download to your computer
6. ✅ Backup saved safely

---

### Step 2: Clean public_html Directory

**Go to File Manager → public_html/**

#### Files to DELETE (if they exist):
- [ ] `index.html` (default hosting page)
- [ ] `index.php` (old PHP files)
- [ ] `default.htm`
- [ ] `coming-soon.html`
- [ ] `cgi-bin/` folder (usually not needed)
- [ ] Any demo/test HTML files
- [ ] Old WordPress files (if any):
  - `wp-admin/`
  - `wp-content/`
  - `wp-includes/`
  - `wp-config.php`
  - All `wp-*.php` files

#### Files to KEEP:
- ✅ `.htaccess` (you can delete if you want fresh one)
- ✅ `error_log` (for checking errors later)
- ✅ `.well-known/` (for SSL verification - DON'T DELETE!)

**Goal:** `public_html/` should be completely empty (except `.well-known/`)

---

### Step 3: Clean Home Directory

**Go to File Manager → Home Directory (`/home/yourusername/`)**

#### Folders to DELETE (if not needed):
- [ ] Old website folders (check carefully first!)
- [ ] Backup archives (`.zip`, `.tar.gz` files - after verifying)
- [ ] `tmp/` old temporary files only
- [ ] Any old abandoned Laravel/PHP projects (verify first!)

#### Folders to KEEP - DO NOT DELETE:
- ✅ `public_html/` (cleaned in Step 2, but keep the folder)
- ✅ **ALL subdomain folders** (usually named like subdomain.domain.com)
- ✅ `.cpanel/` (cPanel configuration)
- ✅ `.ssh/` (SSH keys if you set them up)
- ✅ `etc/` (configuration files)
- ✅ `.well-known/` (SSL verification)
- ✅ `logs/` (server logs)
- ✅ `mail/` (email data - keep if using email)
- ✅ `ssl/` (SSL certificates)
- ✅ `public_ftp/` (if using FTP)

---

### Step 4: Clean Databases

**Go to MySQL Databases**

#### If you have old databases:
1. Go to **phpMyAdmin**
2. List all databases
3. Delete any old/unused databases:
   - [ ] Old WordPress databases
   - [ ] Test databases
   - [ ] Demo databases

#### Result:
- ✅ Only keep databases you actively need
- ✅ Or delete ALL and start fresh

**Note:** Write down which databases you deleted, in case you need to reference them

---

### Step 5: Clean Database Users

**Still in MySQL Databases section:**

1. Scroll to **Current Users**
2. Delete unused database users
3. Keep only the main cPanel user

---

### Step 6: Clean Email Accounts

**Go to Email Accounts**

#### If you have old email accounts:
- [ ] Delete unused email addresses
- [ ] Keep only the ones you need
- [ ] Or plan to create fresh ones later

**Suggestion:** Keep at least one email for:
- `admin@akuru.edu.mv`
- `noreply@akuru.edu.mv` (for system emails)

---

### Step 7: Review Subdomains & Addon Domains (DO NOT DELETE)

**Go to Domains or Subdomains**

#### ⚠️ IMPORTANT: DO NOT DELETE SUBDOMAINS

**Just review and verify:**
- [ ] List all existing subdomains
- [ ] Note which ones are active
- [ ] Verify they're still needed
- [ ] **DO NOT DELETE** - just document them

#### Keep ALL:
- ✅ Main domain: `akuru.edu.mv`
- ✅ All existing subdomains and their folders
- ✅ All addon domains

---

### Step 8: Clean Cron Jobs

**Go to Cron Jobs**

- [ ] Delete any old cron jobs
- [ ] We'll add fresh Laravel cron job later

---

### Step 9: Clean File Manager Hidden Files

**In File Manager:**

1. Click **Settings** (top right)
2. Enable **Show Hidden Files (dotfiles)**
3. Check for and delete:
   - [ ] `.htpasswd` (old password files)
   - [ ] Old `.env` files
   - [ ] `.git/` folders from old projects
   - [ ] `.DS_Store` (Mac files)

---

### Step 10: Check Disk Space Usage

**Go to Disk Usage**

1. See what's taking up space
2. Identify large files/folders
3. Delete if not needed

**Goal:** Have enough space for Laravel (minimum 500MB free)

---

### Step 11: Review Installed SSL Certificates

**Go to SSL/TLS Status**

1. Check if `akuru.edu.mv` has SSL
2. If expired or invalid, remove it (we'll reinstall)
3. Make sure AutoSSL is enabled

---

### Step 12: Clean Temporary Files

**In File Manager:**

Go to these folders and delete old files:
- [ ] `/home/yourusername/tmp/`
- [ ] `/home/yourusername/.trash/`

---

## ✅ Final Verification Checklist

After cleanup, verify:

- [ ] `public_html/` is empty (except `.well-known/`)
- [ ] Home directory has no old project files
- [ ] No unused databases
- [ ] No unused database users
- [ ] No old cron jobs
- [ ] Disk space available (500MB+ free)
- [ ] Domain points to your hosting
- [ ] SSL status is clear

---

## 🗂️ Recommended Final Structure

After cleanup, before deployment:

```
/home/yourusername/
├── public_html/              ← Empty (ready for Laravel public files)
│   └── .well-known/         ← Keep this for SSL
├── .cpanel/                 ← Keep (cPanel config)
├── .ssh/                    ← Keep (if exists)
├── etc/                     ← Keep
├── logs/                    ← Keep
└── [ready for akuru-institute/]  ← Will be uploaded here
```

---

## ⚠️ Important Safety Notes

1. **Always backup before deleting**
2. **Don't delete system folders** (`.cpanel`, `etc`, `.ssh`)
3. **Don't delete subdomain folders** (keep ALL subdomain.domain.com folders)
4. **Keep `.well-known/`** (needed for SSL)
5. **Keep `mail/`** (if you're using email)
6. **If unsure, don't delete** - ask first
7. **Take screenshots** of important configurations before deleting
8. **Verify before deleting** - better to keep than to lose important files

---

## 🚀 After Cleanup - Next Steps

Once cleanup is complete:

1. ✅ Clean cPanel ready
2. 📦 Run `./deploy-prepare.sh` locally
3. 📤 Upload deployment package
4. ⚙️ Follow `DEPLOY_QUICK_START.md`

---

## 📋 Cleanup Summary Template

Copy this after cleanup:

```
✅ Cleanup Completed - [DATE]

Deleted:
- public_html: [list files/folders deleted]
- Databases: [list databases deleted]
- Database users: [list users deleted]
- Subdomains: [list deleted]
- Email accounts: [list deleted]

Kept:
- Domain: akuru.edu.mv
- Disk space available: [XX GB/MB]
- SSL status: [Active/Pending/None]

Ready for deployment: YES ✅
```

---

## 🆘 If Something Goes Wrong

**Accidentally deleted something important?**

1. Restore from the backup you made in Step 1
2. Go to **Backup** → **Restore**
3. Select the backup file
4. Restore the files/database

---

## 📞 Need Help?

Before deleting, if unsure:
1. Take screenshots
2. List files you're unsure about
3. Ask for confirmation
4. Better safe than sorry!

---

**Ready to clean?** Start with Step 1 (Backup) and work your way down! 🧹

