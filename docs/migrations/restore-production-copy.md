# Restore a production dump onto a scratch database

S1_SPEC tests item 1 / line 147: `students:verify-unification` must run
**green on a production-data copy** before Deploy 2. Staging’s synthetic
`a a` / `b b` / `v v` rows (DOB `2000-01-01`, mangled by
`users:clear-non-admin`) cannot validate that gate.

This procedure is **read-only verify**. It never runs `--backfill`.

## Hard rules

1. **`--backfill` is never run against production itself.** The artisan
   command refuses that flag when `APP_ENV=production`. Do not work
   around it by setting `APP_ENV=local` on the production host.
2. **This procedure does not pass `--backfill` on the scratch copy
   either.** The gate answers “is this dump already unified?”, not “can
   we make it unified by writing?”. Writing belongs to a Deploy 1
   backfill job, not to verification.
3. **Never** point the production app `.env` at the scratch database, and
   **never** point a scratch `.env` at the production database name/host.
4. Do **not** run `users:clear-non-admin`, `migrate:fresh`, `db:wipe`, or
   `local:clear-registration` on the copy.
5. Encrypted `registration_students.national_id` / `passport` only decrypt
   with the **production `APP_KEY`**. Copy that key into the scratch `.env`.

## 1. Dump production (read-only)

On the production host (`~/akuru-institute` — see `docs/STAGING.md`):

```bash
cd ~/akuru-institute
mkdir -p storage/backups
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
mysqldump -u "$DB_USER" -p \
  --single-transaction --routines --triggers \
  "$DB_NAME" \
  | gzip > "storage/backups/production-${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"
```

Copy the gzip **off the production host**. Record in `STATUS.md`: dump
filename, byte size, `sha256sum`, and production `git rev-parse HEAD`.

## 2. Scratch database

On **non-production** MySQL only:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS akuru_unify_scratch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Do not reuse `akuru_test` or the live `akuru_institute` schema.

## 3. Restore

```bash
gunzip -c production-*.sql.gz | mysql -u root akuru_unify_scratch
```

Sanity check (counts must be non-trivial; staging synthetics are a dozen
RS rows):

```bash
mysql -u root -N -e "
  SELECT 'registration_students', COUNT(*) FROM akuru_unify_scratch.registration_students
  UNION ALL
  SELECT 'students', COUNT(*) FROM akuru_unify_scratch.students
  UNION ALL
  SELECT 'student_guardians', COUNT(*) FROM akuru_unify_scratch.student_guardians
  UNION ALL
  SELECT 'course_enrollments', COUNT(*) FROM akuru_unify_scratch.course_enrollments;
"
```

If names look like `a a` / `b b` with DOB `2000-01-01`, this is **staging
synthetic data**, not a production copy. Stop.

## 4. Scratch app + pending migrations

Use a **copy** of the production `.env`, then change **only** host/name
credentials so they point at `akuru_unify_scratch`. Keep `APP_KEY`
identical to production. Set `APP_ENV=local` on the scratch app (this is
not production).

```bash
php artisan config:clear
php artisan migrate --force
```

Migrate is additive schema only (rule 9). It must **not** invoke
`students:verify-unification --backfill`.

## 5. Read-only verify (never `--backfill`)

```bash
php artisan tinker --execute="echo config('database.connections.mysql.database').PHP_EOL.config('app.env');"
# Must print akuru_unify_scratch and not production.

php artisan students:verify-unification
```

Do **not** add `--backfill`. Report file:
`storage/app/s11b-student-unification-report.json`.

Copy it to `docs/migrations/s11b-student-unification-report-prod-copy.json`
and paste **verbatim stdout** into `STATUS.md`.

| Result | Meaning |
|---|---|
| OK, zero unresolved | A3 green. Deploy 2 / student-keyed-write gate satisfied. |
| FAILED, every RS maps to 0 students, mapped/created = 0 | Deploy 1 backfill never ran on this dump. Gate stays red. Do not `--backfill` here. |
| FAILED with collisions / ambiguous / guardian pivots | Real data issues. Fix in code (A1/A2) or operator resolution; restore a **fresh** dump and verify again. |

Zero unresolved = A3 green = TRACK B unblocked.

## 6. Destroy the scratch database

```bash
mysql -u root -e "DROP DATABASE IF EXISTS akuru_unify_scratch;"
```

## What this environment cannot do

No production dump is present on the Cursor Cloud VM (`akuru_test` /
`akuru_institute` only). Until an operator provides a dump and step 5 is
green, **A3 is not green** and TRACK B must not start.
