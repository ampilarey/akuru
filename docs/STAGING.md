# Staging server — `test.akuru.edu.mv`

**Read this before any staging deploy or server git command.**  
Canonical deploy details also live in `docs/legacy/DEPLOYMENT.md` §6.1.


## Paths (cPanel account `akuruedu`)

| What | Path |
|------|------|
| cPanel username | `akuruedu` (shell prompt: `[akuruedu@sg-s2 …]`) |
| Home directory | `/home/akuruedu/` (same as `~`) |
| **Staging app (git repo)** | `~/test.akuru.edu.mv` |
| Production app (git repo) | `~/akuru-institute` |
| Staging URL | https://test.akuru.edu.mv |

**`akuruedu` is not a file in the Git repo** — it is the hosting account name.  
**`~` is not a git repository.** Always `cd` into the project folder before `git`, `composer`, or `php artisan`.

Wrong (fails with “not a git repository”):

```bash
cd ~
git pull origin main
```

Right:

```bash
cd ~/test.akuru.edu.mv && git pull origin main
```

## Current staging state (update after each deploy)

| Item | Value |
|------|--------|
| Last verified deploy | **2026-06-13** |
| Deployed commit | **`f8b8a56`** (`docs(status): record Phase 0 staging deploy at 9e8e8f6`) |
| Phase 0 application code | Included via **`e84c657`** in history |
| Verdict | **PHASE 0 STAGING PASSED** (deploy + public smoke) |
| Production | **Not deployed** — do not touch `~/akuru-institute` unless explicitly requested |

## Rules

1. **Mac pushes, server pulls** — never `git commit` or `git push` from staging.
2. **Test before production** — all changes go to `test.akuru.edu.mv` first.
3. **`route:clear`, not `route:cache`** — mcamara localized routes break with route cache.
4. **SMS on staging** — test numbers only: **`7820288`** and **`7972434`**. Do not SMS real customers.
5. **BML** — staging uses sandbox/UAT credentials only (`BML_ENVIRONMENT=sandbox` in `.env`).
6. **`npm`** — often **not** in server PATH; if assets change, run `npm run build` on Mac and upload `public/build/`, or enable Node in cPanel.

## Automated deploy (GitHub Actions webhook)

`.github/workflows/deploy-test-immediate.yml` auto-deploys staging on every push to
`main` (and via **Actions → Deploy TEST (immediate) → Run workflow**). It uses the same
**webhook self-pull** model as the bake-and-grill project — it keeps the "server pulls"
rule: the Action does not SSH in, it just POSTs to a deploy endpoint on the server and
the server pulls itself.

Flow:

1. Action `POST https://test.akuru.edu.mv/api/deploy/test-pull` with
   `Authorization: Bearer <secret>` and `{"sha": "<commit>"}` (retries until HTTP 202).
2. `App\Http\Controllers\Api\TestDeployWebhookController` checks the secret + that it is
   running on an allowed test host, then `App\Support\Services\TestDeployTrigger` spawns
   `scripts/pull-deploy-test.sh` in the background (nohup).
3. `scripts/pull-deploy-test.sh` fast-forwards to the pushed SHA, then runs
   `composer install --no-dev` (only if `composer.lock` changed) → `migrate --force` →
   `permission:cache-reset` (warn + continue if the command is missing) →
   `config:cache` → `route:clear` (never `route:cache`) → `view:cache` → `queue:restart`,
   then **`php artisan morph-map:verify`** and
   **`php artisan students:verify-unification`** (read-only — never `--backfill`)
   as post-chain gates (see below).
   Progress is logged to `~/self-update-test.log`.
4. The Action smoke-checks `/up` and `/en`.

### Morph-map deploy gate (auto-deploy log)

After the Laravel chain, the pull script runs `php artisan morph-map:verify` and
writes its **full output** into `~/self-update-test.log` (collapse counts and
kept/dropped pivot rows are the staging go/no-go evidence).

| Outcome | What you see in `~/self-update-test.log` | Deploy result |
|---------|------------------------------------------|---------------|
| **Green** | Verify body (optional collapse report) + `morph-map:verify OK` + `deploy complete: <sha>` | exit 0 |
| **Gate failed** | Verify body listing remaining FQCNs + a `======== MORPH-MAP GATE FAILED ========` block | exit 1 |
| **Command missing** | `WARN: morph-map:verify not available — skipping gate (older commit)` | exit 0 (script must work on pre-hotfix commits) |

Collapse counts appear in the verify output **only on the first deploy that
performs a rewrite** (or a later deploy that still finds duplicates). A clean
re-deploy after a successful rewrite typically prints “no composite-key duplicates
were merged” and then OK.

**First-deploy caveat (do not re-litigate):** bash keeps running the *pre-pull*
script after `git merge --ff-only`. The deploy that *introduces* this gate still
runs the old ungated script; the gate takes effect from the **second** auto-deploy
onward. Re-exec-after-pull was considered and rejected: a bad script on `main`
could abort after merge but before `migrate --force`, leaving staging on new code
against an un-migrated DB. If re-exec is revived later it must `bash -n` the pulled
script and fall through to the in-process path on parse failure.

### Student-unification deploy gate (S2.0)

After the morph-map gate, the pull script runs
`php artisan students:verify-unification` with **no `--backfill` flag**
(read-only verify; `--backfill` writes mappings and must never run from
auto-deploy) and writes its **full output** into `~/self-update-test.log`.
The command also refreshes `storage/app/s11b-student-unification-report.json`
on the server.

| Outcome | What you see in `~/self-update-test.log` | Deploy result |
|---------|------------------------------------------|---------------|
| **Green** | Mapped/created/guardian/enrollment counts + `students:verify-unification OK` + `deploy complete: <sha>` | exit 0 |
| **Gate failed** | Failure bullets + a `======== STUDENT-UNIFICATION GATE FAILED ========` block | exit 1 |
| **Command missing** | `WARN: students:verify-unification not available — skipping gate (older commit)` | exit 0 (script must work on pre-S1.1b commits) |

**Operator after a green (or failed) run:** copy the verbatim verify block
from `~/self-update-test.log` into `STATUS.md` (same format as the
morph-map capture) and archive
`~/test.akuru.edu.mv/storage/app/s11b-student-unification-report.json`
under `docs/migrations/` (S1 DoD line 158).

**Same first-deploy caveat as morph-map:** the deploy that *introduces*
this gate still runs the pre-pull script. Evidence appears from the
**second** auto-deploy onward. After S2.0 merges, land a trivial docs
commit on `main` to trigger that second run. Do not re-exec-after-pull
(same blast-radius rejection as above).

**Consequence for the morph-map hotfix deploy:** the automated gate does **not**
cover that first cutover. After the hotfix lands on staging, run manually:

```bash
cd ~/test.akuru.edu.mv \
  && php artisan morph-map:verify \
  && php artisan permission:cache-reset
```

**Required secret** — set the SAME value in two places:

| Location | Key | Notes |
|----------|-----|-------|
| Server `.env` (test) | `TEST_DEPLOY_WEBHOOK_SECRET` | >= 16 chars; empty = endpoint disabled (404) |
| GitHub (repo or `test` environment) | `TEST_DEPLOY_WEBHOOK_SECRET` | Settings → Secrets and variables → Actions |

Related server `.env` keys (defaults are fine): `TEST_DEPLOY_ALLOWED_HOSTS=test.akuru.edu.mv`,
`TEST_DEPLOY_HOME=/home/akuruedu`.

**One-time server setup:** `chmod +x scripts/pull-deploy-test.sh` and ensure the web PHP
user can run `git`/`composer`/`php` (the script prepends the cPanel ea-php84 path).

The endpoint returns `404` on non-test hosts / when disabled, `401` on a bad secret, and
`202` once the background deploy is spawned — so production (`~/akuru-institute`) never
auto-deploys even if the workflow secret leaks.

## Routine deploy (after `git push origin main` on Mac)

```bash
cd ~/test.akuru.edu.mv && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction && php artisan migrate --force && php artisan config:cache && php artisan route:clear && php artisan view:cache && php artisan queue:restart
```

Shorter (no view cache / queue):

```bash
cd ~/test.akuru.edu.mv && bash scripts/update-subdomain.sh
```

Full Phase 0-style deploy (backups + migrate status + optional npm):

```bash
cd ~/test.akuru.edu.mv && bash scripts/deploy-staging-phase0.sh
```

## Verify deploy

```bash
cd ~/test.akuru.edu.mv && git log -1 --oneline && git rev-parse HEAD && ls scripts/
```

Hash should match `origin/main` on GitHub.

Quick HTTP smoke:

```bash
curl -sI https://test.akuru.edu.mv/en | head -3
curl -sI https://test.akuru.edu.mv/inertia-test | head -3
```

## Pre-deploy backup (recommended)

```bash
cd ~/test.akuru.edu.mv
cp -a .env ".env.backup.$(date +%Y%m%d-%H%M%S)"
mkdir -p storage/backups
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
mysqldump -u "$DB_USER" -p "$DB_NAME" | gzip > "storage/backups/staging-${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"
```

## If `git pull` fails (divergent branches)

Staging had this on **2026-06-13**: five server-only commits duplicated GitHub work (CI fixes made directly on the server). GitHub had squashed the same changes as `6fceabd`.

**Before reset:** confirm server-only commits are redundant (compare with `git log --oneline origin/main..HEAD`).

**Align to GitHub (staging only):**

```bash
cd ~/test.akuru.edu.mv
git fetch origin
git log --oneline origin/main..HEAD
git reset --hard origin/main
git log -1 --oneline
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:clear
php artisan view:cache
php artisan queue:restart || true
```

Untracked junk in the app root (`error_log`, accidental tinker paste filenames) does **not** block `git pull` or `reset --hard`; clean up when convenient.

## Phase 0 staging verification (2026-06-13)

| Step | Result |
|------|--------|
| `.env` backup | Done |
| DB backup | `storage/backups/staging-akuruedu_test.akuru.edu.mv-20260613-082231.sql.gz` |
| Migrations | All ran; nothing pending |
| `npm run build` | Skipped (npm not on PATH; no frontend diff in deploy) |
| Public routes | `/up`, `/en`, `/dv`, `/ar`, courses, contact, gallery, news, `/inertia-test` — 200 |
| Login / BML / portal / admin / Hifz | Operator spot-check with credentials — not automated |

## Production (reference only)

```bash
cd ~/akuru-institute && git log -1 --oneline && git rev-parse HEAD
```

Do **not** run production deploy unless explicitly requested.
