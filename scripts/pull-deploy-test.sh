#!/usr/bin/env bash
# Immediate TEST pull + Laravel deploy for test.akuru.edu.mv (no CI wait).
#
# Used by:
#   - POST /api/deploy/test-pull (GitHub Actions, .github/workflows/deploy-test-immediate.yml)
#
# Optional arg: expected full SHA to deploy (retries fetch briefly if the tip lags).
#
# akuru specifics vs the generic Laravel deploy:
#   - single-root app (no backend/ subdir)
#   - QUEUE_CONNECTION=database (no redis worker)
#   - route:clear, NEVER route:cache (route cache breaks mcamara localized routes)
#
# PHP-FPM/nohup often has no HOME — set before `set -u`.
export HOME="${HOME:-/home/akuruedu}"
set -uo pipefail

export PATH="$HOME/bin:/usr/local/bin:/opt/cpanel/ea-php84/root/usr/bin:/usr/bin:/bin:${PATH:-}"
command -v php >/dev/null || { echo "$(date '+%F %T') php not found on PATH=$PATH"; exit 1; }
command -v git >/dev/null || { echo "$(date '+%F %T') git not found on PATH"; exit 1; }
command -v composer >/dev/null || { echo "$(date '+%F %T') composer not found on PATH=$PATH"; exit 1; }

ROOT="${DEPLOY_TEST_ROOT:-$HOME/test.akuru.edu.mv}"
LOCK="$HOME/.self-update-test.lock"
EXPECTED_SHA="${1:-}"

echo "$(date '+%F %T') pull-deploy-test starting (HOME=$HOME ROOT=$ROOT expected=${EXPECTED_SHA:-none})"

mkdir "$LOCK" 2>/dev/null || {
  echo "$(date '+%F %T') deploy already in progress — skipping"
  exit 0
}
trap 'rmdir "$LOCK"' EXIT

cd "$ROOT" || { echo "$(date '+%F %T') cannot cd to $ROOT"; exit 1; }

fetch_main() {
  git fetch origin main --quiet
}

fetch_main || { echo "$(date '+%F %T') git fetch failed"; exit 1; }

# Webhook/Actions pass a SHA that must be the tip of origin/main.
# Feature-branch SHAs will never match — fail fast after a short wait.
if [[ -n "$EXPECTED_SHA" ]]; then
  echoed_wait=0
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    REMOTE=$(git rev-parse FETCH_HEAD)
    [[ "$REMOTE" == "$EXPECTED_SHA" ]] && break
    if [[ "$echoed_wait" -eq 0 ]]; then
      echo "$(date '+%F %T') waiting for origin/main (${REMOTE:0:8}) to reach ${EXPECTED_SHA:0:8}"
      echoed_wait=1
    fi
    sleep 2
    fetch_main || true
  done
  REMOTE=$(git rev-parse FETCH_HEAD)
  if [[ "$REMOTE" != "$EXPECTED_SHA" ]]; then
    echo "$(date '+%F %T') origin/main tip ${REMOTE:0:8} != expected ${EXPECTED_SHA:0:8} — aborting (deploy only accepts main SHAs)"
    exit 1
  fi
fi

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse FETCH_HEAD)
if [[ "$LOCAL" == "$REMOTE" ]]; then
  echo "$(date '+%F %T') already on ${LOCAL:0:8} — nothing to deploy"
  exit 0
fi

echo "$(date '+%F %T') deploying ${LOCAL:0:8} -> ${REMOTE:0:8}"
git merge --ff-only FETCH_HEAD || { echo "$(date '+%F %T') fast-forward failed — manual attention needed"; exit 1; }

if git diff --name-only "$LOCAL" "$REMOTE" | grep -q '^composer.lock$'; then
  composer install --no-dev --optimize-autoloader --no-interaction \
    || { echo "$(date '+%F %T') composer install failed"; exit 1; }
fi

php artisan storage:link --force 2>/dev/null \
  || echo "$(date '+%F %T') WARN: storage:link failed — is public/storage a real directory?"

# route:clear (NOT route:cache) — route cache breaks mcamara localized routes.
# permission:cache-reset is guarded: missing command (older Spatie / absent package)
# must not abort the deploy.
run_permission_cache_reset() {
  if php artisan list --raw 2>/dev/null | grep -qE '^permission:cache-reset([[:space:]]|$)'; then
    php artisan permission:cache-reset
  else
    echo "$(date '+%F %T') WARN: permission:cache-reset not available — skipping"
    return 0
  fi
}

php artisan migrate --force \
  && run_permission_cache_reset \
  && php artisan config:cache \
  && php artisan route:clear \
  && php artisan view:cache \
  && php artisan queue:restart \
  || { echo "$(date '+%F %T') Laravel deploy steps failed"; exit 1; }

# Morph-map gate runs AFTER the chain so a failed verify still leaves caches rebuilt.
# Aborting mid-chain would skip cache rebuilds without undoing migrate — worse state.
# NOTE: bash keeps executing the pre-pull script body after `git merge` above, so the
# first deploy that *introduces* this gate still runs the old ungated script. The gate
# takes effect from the second deploy onward. The morph-map hotfix deploy therefore
# still needs the manual post-deploy:
#   php artisan morph-map:verify && php artisan permission:cache-reset
# Re-exec-after-pull was considered and rejected (blast radius: bad script on main
# could abort after merge but before migrate). See docs/STAGING.md.
if php artisan list --raw 2>/dev/null | grep -qE '^morph-map:verify([[:space:]]|$)'; then
  VERIFY_OUT="$(php artisan morph-map:verify 2>&1)" || VERIFY_RC=$?
  VERIFY_RC="${VERIFY_RC:-0}"
  # Full verify output (collapse counts / kept-dropped rows) into the deploy log.
  printf '%s\n' "$VERIFY_OUT"
  if [[ "$VERIFY_RC" -ne 0 ]]; then
    echo "======== MORPH-MAP GATE FAILED ========"
    echo "$(date '+%F %T') morph-map:verify exited ${VERIFY_RC} — staging deploy FAILED"
    echo "Inspect collapse/FQCN report above. Do not treat this deploy as green."
    echo "======================================"
    exit 1
  fi
  echo "$(date '+%F %T') morph-map:verify OK"
else
  echo "$(date '+%F %T') WARN: morph-map:verify not available — skipping gate (older commit)"
fi

# Student-unification gate (S2.0). READ-ONLY — never pass --backfill
# (that writes mappings). Same first-deploy caveat as morph-map: this
# block takes effect from the *second* deploy after it lands on main.
# Evidence: full stdout in ~/self-update-test.log plus
# storage/app/s11b-student-unification-report.json (operator copies both
# into STATUS.md + docs/migrations/).
if php artisan list --raw 2>/dev/null | grep -qE '^students:verify-unification([[:space:]]|$)'; then
  UNIFY_OUT="$(php artisan students:verify-unification 2>&1)" || UNIFY_RC=$?
  UNIFY_RC="${UNIFY_RC:-0}"
  printf '%s\n' "$UNIFY_OUT"
  if [[ "$UNIFY_RC" -ne 0 ]]; then
    echo "======== STUDENT-UNIFICATION GATE FAILED ========"
    echo "$(date '+%F %T') students:verify-unification exited ${UNIFY_RC} — staging deploy FAILED"
    echo "Nonzero unresolved (or other verify failure). Do not treat this deploy as green."
    echo "Copy the report above and storage/app/s11b-student-unification-report.json"
    echo "into STATUS.md + docs/migrations/. Student-keyed S2 writes stay blocked."
    echo "================================================"
    exit 1
  fi
  echo "$(date '+%F %T') students:verify-unification OK"
else
  echo "$(date '+%F %T') WARN: students:verify-unification not available — skipping gate (older commit)"
fi

echo "$(date '+%F %T') deploy complete: ${REMOTE:0:8}"
