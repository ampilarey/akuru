# Branch protection on `main`

Required by TRACK A4. The cloud agent **cannot** apply this (GitHub App
gets HTTP 403 `Resource not accessible by integration` on both GET and
PUT `/repos/ampilarey/akuru/branches/main/protection`). An org owner or
repo admin must set it in the GitHub UI.

Attempted 2026-08-25 from this agent (twice, including after ADR-021);
GET and PUT both returned 403. Protection was **not** applied. Do not
assume `main` is protected until an operator confirms the screenshot /
API GET succeeds. This is unrelated to live-data premises (ADR-021).

## Apply (GitHub UI)

Repository → Settings → Branches → Add classic branch protection rule.

- Branch name pattern: `main`
- Require a pull request before merging (1 approving review is enough)
- Require status checks to pass before merging
  - Require branches to be up to date
  - Status check: `quality` (workflow **CI**, job id `quality`; GitHub
    may display it as `CI / quality`)
- Require conversation resolution before merging
- Do not allow bypassing the above settings (keep admins honest)
- Do **not** allow force pushes
- Do **not** allow deletions

One slice per PR under this rule.

Merging from an agent token **is** allowed, but only once the required
check has reported `success` on the PR head — see ADR-027. Read the run's
conclusion back before merging; never assume it passed. Never weaken or
bypass the settings above to get a merge through: if protection blocks
the merge, report it rather than routing around it.

## Confirm

```bash
gh api repos/ampilarey/akuru/branches/main/protection --jq '.required_status_checks.contexts, .enforce_admins.enabled'
```

Success (non-403) + `quality` in contexts = A4 green. Paste stdout into
`STATUS.md`.
