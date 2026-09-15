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

## Checked 2026-09-15 — still **not applied**

The protection endpoint is still out of reach from an agent (no `gh`, no
direct API, and the GitHub MCP server exposes no branch-protection tool), but
the answer turns out not to need it. Two independent signals, both readable
with ordinary permissions, agree:

1. **`GET /repos/ampilarey/akuru/branches` reports `main` as
   `"protected": false`.** Every other branch too, but `main` is the one that
   matters.
2. **Pull request `mergeable_state` was `unstable`, not `blocked`, while the
   `quality` check was still running** (#402). A *required* status check that
   has not reported yet produces `blocked`. `unstable` means the failing or
   pending check is **not required**.

So on 2026-09-15: **`main` is unprotected, direct pushes are possible, force
pushes are possible, and the `quality` check is not required before merge.**

**What that means for everything upstream of it.** Every merge gate this
project relies on — one slice per PR, CI green before merge, no direct pushes —
has been **discipline, not mechanism**. It has held, but nothing was enforcing
it, and ADR-027's "read the conclusion back, never assume it" is load-bearing
rather than belt-and-braces.

**`CLAUDE.md` currently states the opposite** (merge gates: *"`main` is
branch-protected — required CI check pre-merge, no direct pushes"*). That
line is not true today. Correcting the governing document is an owner's call,
so it is flagged here rather than edited.

One caveat, stated so nobody over-reads this: `protected` in the branches
listing reflects **classic** branch protection. If the repository used a
**ruleset** instead, that field could read `false` while rules were in force —
but signal 2 would then be hard to explain, because a ruleset requiring
`quality` would also produce `blocked`. A repo admin running the `gh api` line
above settles it in one command.
