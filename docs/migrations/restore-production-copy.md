# Restore a production dump onto a scratch database

S1_SPEC tests item 1 / line 147: `students:verify-unification` must run
**green on a production-data copy** before Deploy 2. Staging’s synthetic
`a a` / `b b` rows cannot validate that gate.

This procedure is the only supported way to do that. It is an operator
task: this environment has no production dump.

## Hard rules

1. **Never** run `php artisan students:verify-unification --backfill` against
   production. The command refuses `--backfill` when `APP_ENV=production`.
   Do not work around that by setting `APP_ENV=local` on the production
   host.
2. **Never** point the production app `.env` at the scratch database, and
   **never** point a scratch `.env` at the production database name/host.
3. Do **not** run `users:clear-non-admin`, `migrate:fresh`, `db:wipe`, or
   `local:clear-registration` on the copy. Those destroy the rows we are
   validating.
4. Encrypted `registration_students.national_id` / `passport` only decrypt
   with the **production `APP_KEY`**. Copy that key into the scratch `.env`.
   A different key will make every national_id match look blank.

## 1. Dump production (read-only)

On the production host, from the app directory (see `docs/STAGING.md`
production path `~/akuru-institute`):

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

Copy the gzip **off the production host** (scp/rsync) to the machine that
will run verify. Do not leave the only copy on production.

Record in `STATUS.md`: dump filename, byte size, `sha256sum`, and the
production `git rev-parse HEAD` at dump time.

## 2. Create a scratch database

On a **non-production** MySQL (local VM, operator laptop, or a dedicated
scratch instance — not the production server):

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS akuru_unify_scratch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Do not reuse `akuru_test` (Pest) or the live `akuru_institute` schema.

## 3. Restore the dump

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

## 4. Scratch app `.env`

Use a **copy** of the production `.env`, then change **only**:

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akuru_unify_scratch
DB_USERNAME=root
# scratch credentials — never production user/password
```

Keep `APP_KEY` **identical** to production. Keep `APP_URL` unused (do not
serve this copy publicly).

```bash
php artisan config:clear
php artisan students:verify-unification
```

Do **not** pass `--backfill` on this first run. The report is
`storage/app/s11b-student-unification-report.json`.

## 5. Interpret the first verify

| Result | Meaning | Next |
|---|---|---|
| OK, mapped/created all zero, every RS already has a student | Deploy 1 backfill already applied on the dump | Archive JSON; Deploy 2 gate is this green report |
| FAILED, every RS maps to 0 students, mapped/created = 0 | Backfill never ran on this data | On **scratch only**: `--backfill`, then verify again |
| FAILED with collisions / ambiguous / guardian_user_id missing | Real data issues (A1 wipe leftovers, A2 matcher, duplicates) | Fix in code or operator resolution; re-run on a **fresh** restore |

## 6. Backfill on scratch only (if step 5 says so)

```bash
# Confirm DB_DATABASE is akuru_unify_scratch and APP_ENV is not production:
php artisan tinker --execute="echo config('database.connections.mysql.database').PHP_EOL.config('app.env');"

php artisan students:verify-unification --backfill
php artisan students:verify-unification
```

Copy the JSON to `docs/migrations/s11b-student-unification-report-prod-copy.json`
and paste **verbatim stdout** into `STATUS.md`.

Zero unresolved = A3 green = Deploy 2 / student-keyed-write / TRACK B
unblocked.

## 7. Destroy the scratch database when finished

```bash
mysql -u root -e "DROP DATABASE IF EXISTS akuru_unify_scratch;"
```

Do not keep a writable copy of production PII on a laptop longer than the
verify run.

## What this cloud agent cannot do

No production dump is present on the Cursor Cloud VM (`akuru_test` /
`akuru_institute` only). Until an operator provides a dump and the
commands above go green, **A3 is not green** and TRACK B must not start.
