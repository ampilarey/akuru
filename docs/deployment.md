# Deployment — the pull, and backups

SPEC "Deployment / Backups" asks for this document. The pull line is the
one every merge reports. Backups (BK1, STATUS §5up) are the part that
protects families' records and wallet money, so most of this page is about
them.

## The pull

```
cd /home/akuruedu/akuru-institute && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction && php artisan migrate --force && php artisan config:cache && php artisan route:clear && php artisan view:clear && php artisan cache:clear && php artisan queue:restart
```

Never run `php artisan route:cache`. Never run the demo or smoke seeders on
production.

## Backups

### What is backed up, and when

- **The database:** every table, from one consistent moment
  (`mysqldump --single-transaction`).
- **The uploaded and private files:** everything under `storage/app`
  (report cards, ID cards, Library files, pictures), except the backups
  themselves.
- **When:** every night at 03:00 Maldives time, by the scheduler
  (`akuru:backup`). The minute cron with `schedule:run` must be running,
  as it already is for the other jobs.

Each night makes one file, `akuru-YYYYMMDD-HHMMSS.akb`. It is compressed and
**encrypted**: XChaCha20-Poly1305, with the key stretched from the
passphrase by Argon2id. A file that has been cut short or altered is refused
rather than half-restored.

### Where the copies are kept

- **On this server**, in `storage/app/backups` (`BACKUP_LOCAL_PATH`). This
  protects against a mistake. It does not protect against losing the
  server.
- **Off the server**, once configured. Set `BACKUP_DISK=backups` and the
  `BACKUP_S3_*` keys of an S3-compatible store (for example Backblaze B2,
  Wasabi or AWS S3) in `.env`, then run `php artisan config:cache`. Until
  this is done, every copy is on the same server as the data.

### How long they are kept

- the newest **7** daily copies;
- the newest copy of each of the last **4** weeks;
- the newest copy of each of the last **6** months.

Older copies are removed, here and off-site (`BACKUP_KEEP_DAILY`,
`BACKUP_KEEP_WEEKLY`, `BACKUP_KEEP_MONTHLY`).

### The passphrase — keep it somewhere else

- The copies are encrypted with `BACKUP_PASSPHRASE`, or with `APP_KEY` if
  that is not set.
- **A backup cannot be opened without it.** Keep a copy in a password
  manager, not only on the server.
- If the server is lost and the passphrase only lived in its `.env`, the
  backups are lost with it.
- Changing the passphrase does not re-encrypt old copies. Keep the old one
  until they have aged out (six months).

### Run one now, and list what is kept

```
php artisan akuru:backup
php artisan akuru:backup-list
```

### Restore

1. **Check a backup** (do this once after setting up, and every few months):
   make an empty database in cPanel (for example `akuruedu_restorecheck`),
   then:
   ```
   php artisan akuru:backup-restore akuru-20261029-030000.akb --into=akuruedu_restorecheck --files=/home/akuruedu/restorecheck-files
   ```
   It fetches the copy, from off-site if it is no longer on the server,
   decrypts it and fills that database. Look at a few tables in phpMyAdmin,
   then delete the database and the folder.
2. **Bring the live site back from a backup** (after data loss):
   - put the site into maintenance: `php artisan down`;
   - restore into the live database:
     ```
     php artisan akuru:backup-restore akuru-20261029-030000.akb --into-live
     ```
     On production it asks you to type the database's name first.
   - restore the files: `--files=/home/akuruedu/akuru-institute/storage/app`;
   - then `php artisan migrate --force` (for a backup older than the code),
     `php artisan config:cache` and `php artisan up`.
   - Payments the bank confirmed after the backup was made come back with
     `payments:reconcile`, which the scheduler runs every ten minutes.

### The restore is tested

SPEC: "A backup is not considered valid unless the restore procedure has
been tested." `tests/Feature/Settings/BackupRestoreTest.php` runs on every
CI run:
- it backs up the test database and a folder of files;
- it restores both into a scratch database and another folder;
- it checks the tables, a row written before the backup, and a file.

It also checks that the wrong passphrase and a cut file are refused, the
off-site copy, the retention rule, and a failed run being recorded.

### If a backup fails

Each run is a row in `backup_runs`, with the reason when it fails. The usual
causes:
- **`mysqldump` not found:** set `BACKUP_MYSQLDUMP_PATH` (and
  `BACKUP_MYSQL_PATH` for restores) to the paths `which mysqldump` and
  `which mysql` print.
- **A MySQL 8 client against a MariaDB server:** set
  `BACKUP_MYSQLDUMP_OPTIONS=--column-statistics=0`.
- **Disk full:** the copies on the server count against the account's
  quota.
  - Each copy is about the size of the database plus `storage/app`
    compressed: the development copy of 2026-10-29 was 457 MB.
  - A run needs about twice that free while it works.
  - A write that does not land stops the run with *the disk may be full*.
    It never leaves a short file that looks finished.
  - Lower `BACKUP_KEEP_DAILY`, or rely on the off-site copy.
