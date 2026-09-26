# The admin panel — inventory and audit (2026-09-26)

The owner: "audit everything related to admin panel". This is the record.
**What the admin panel is**, for this document: every route under `/admin/*`
(149 routes in 14 groups on the day), the `/dashboard` landing that sends an
administrator there, the two navigations that reach it (the Blade nav and
the Inertia shell's More menu), and the roles and permissions that gate it.
The staff screens outside `/admin` — academics, people, exams, finance,
HR, the catalogue — were swept in STATUS §5cl and are not re-audited here.

Everything below was checked against the code on `main`, not against
STATUS or a plan. Where a finding was fixed, the fix is in the same PR as
this document; where it was not, the reason is given and it is recorded in
KNOWN_ISSUES or BACKLOG.

## 1. The inventory

| Group | Routes | Gate on the route | Screens | CSV | Tests | Walk |
|---|---|---|---|---|---|---|
| `admin/users` | 5 | `role:super_admin` | Blade (users, OTP abuse) | yes, both | 5 files | `admin.mjs` |
| `admin/settings` | 2 | `role:super_admin` | Blade | n/a | 4 files | `admin.mjs` |
| `admin/enrollments` | 11 | `role:super_admin\|admin\|headmaster\|supervisor`; `can:payments.record` on the manual payment | Blade (3) | yes, both lists | 5 files | `admin.mjs`, 2 others |
| `admin/payments` | 1 | `role:super_admin\|admin` + `can:payments.refund` | write only | n/a | 1 file | — |
| `admin/instructors` | 6 → 7 | role only (4 roles) | Blade (3) | **added** | 2 → 3 files | `admin.mjs` |
| `admin/public-site` (CMS: pages, courses, research, daily content, subscriptions, leads, funnel) | 37 → 39 | role only (4 roles); `daily_content.manage` / `.approve` checked in the controllers | Blade (17) + 1 Inertia | research, daily content, subscriptions, leads, funnel had it; **pages and courses added** | 20 files | `admin.mjs` |
| `admin/prayer-times` | 17 → 18 | role (4) + `can:prayer.manage` | Blade (8) | islands, broadcasts had it; **groups added** | 3 files | `admin.mjs` |
| `admin/operations` | 5 | `role:super_admin\|admin` + `can:operations.manage` | Inertia (2) | yes, both | 2 files | `admin.mjs`, `operations.mjs` |
| `admin/translations` | 4 | `role:super_admin\|admin` + `can:translations.manage` | Inertia | yes | 1 file | `admin.mjs` |
| `admin/commerce` | 5 | `role:super_admin\|admin` + `can:commerce.manage` | Inertia | yes | 3 files | 3 walks |
| `admin/library` | 14 | `role:super_admin\|admin\|headmaster` + `can:library.manage` | Inertia (2) | yes, both | 9 files | 4 walks |
| `admin/pronunciation` | 5 | `role:super_admin\|admin` + `can:pronunciation.manage` | Inertia | yes | 1 file | `pronounce.mjs` |
| `admin/bookshop` | 37 | `role:super_admin\|admin\|bookshop_manager` + `can:bookshop.manage` | Inertia | yes, all ten | 17 files | 15 walks |

**Landing.** `/dashboard` resolves by role (`ResolveDashboardLandingAction`):
`super_admin` gets the Blade super-admin dashboard, `admin`/`headmaster`
the portal overview, `supervisor` its dashboard, `bookshop_manager` the
Bookstore office. The two navigations: the Blade nav
(`layouts/navigation.blade.php`) lists every admin landing (gated by
`AdminPagesAreReachableTest`); the Inertia shell's More menu now does too
(§3, finding 1).

## 2. Checked and held

- **Every one of the 149 routes is behind `auth` and a role.** 96 also
  carry a `can:` permission on the route; the 53 that do not (enrolments,
  instructors, the CMS) are the oldest groups. The money writes are the
  tightest: refunds `can:payments.refund`, manual payments
  `can:payments.record`, wallet credits and gift cards `commerce.manage`,
  payouts `library.manage` / `bookshop.manage`.
- **No unguarded write route** (`WriteRoutesAreGuardedTest`, baseline
  carries no admin entry). Every admin POST/PUT/PATCH/DELETE is under the
  `web` group's CSRF.
- **Every raw HTML render is declared** and every CMS body is sanitised
  on write with `HtmlSanitizer::PROFILE_CMS` (pages, courses, research);
  daily content is rendered escaped.
- **Thin controllers**: 4 baselined long methods, all CSV exports
  (`AdminEnrollmentController::export` 56 lines, three CMS exports).
  Writes go through Actions; `AdminEnrollmentController::reject` is the
  one status write done in the controller (finding 6).
- **Every listing exports CSV** — after this audit (finding 2).
- **Account deletion** refuses the actor's own account and any
  `super_admin`, deactivates instead of deleting when history depends on
  the account, and hard-deletes only what SPEC §29 allows. The user CSV
  leaves out identity documents and addresses.
- **Every admin GET screen is a crash gate**: `StaffScreensDoNotCrashTest`
  loads all parameterless staff screens (more than 120, `admin/` among
  them) as a super admin and fails on any 5xx; `AdminPanelSmokeTest`
  loads 34 named routes with seeded data.
- **Every admin landing is reachable** from the Blade nav
  (`AdminPagesAreReachableTest`), and now from the Inertia shell.
- **The settings screen** shows drivers and whether SMS and BML are
  configured — read from `config()`, never `env()` — to `super_admin` only.

## 3. Findings

| # | Finding | Severity | Outcome |
|---|---|---|---|
| 1 | **The Inertia shell's More menu reached four admin pages** (operations, features, translations, Bookstore). An admin on any Inertia admin screen had no link to Users, Settings, Enrolments, Instructors, the CMS, Commerce, the Library office, prayer times or Pronunciation — the mirror of STATUS §5ab, which fixed the same hole in the Blade nav. Blade pages cannot be Inertia `<Link>`s (the response is not an Inertia page, so the shell shows it in a modal). | medium | **Fixed**: nine entries added to `NavigationMap`'s admin group; Blade ones carry `hard`, which `BuildNavigationAction` passes through and `AppShell` renders as a plain `<a>` (full page load). Labels in EN/DV/AR. `AdminPagesAreReachableTest` gains the Inertia check; `AdminPanelAuditTest` pins the gating (a plain admin never sees Users or Settings). |
| 2 | **Four listings had no CSV** against the every-listing convention: instructors, prayer recipient groups, CMS pages, CMS courses. | low | **Fixed**: four exports, each the screen's own query, with an *Export CSV* link and a test; declared before the resource routes so `pages/export` is not swallowed by `pages/{page}`. |
| 3 | **`users:clear-non-admin` had no production guard.** It turns foreign-key checks off, hard-deletes every non-admin user and their `payments` rows (rule 12), and `--force` skips the only confirmation. Its purpose is test data; on production one line would have wiped every family. | high (latent — nobody runs it there) | **Fixed**: refused outright on production, before any count; tested. The rule-12 delete of `payments` on non-production stays, because that is the command's job on staging. |
| 4 | **The prayer-times import accepted any file size** (`required|file`). | low | **Fixed**: capped at 20 MB (the bundled `salat.db` is 470 KB); tested. |
| 5 | **No screen assigns or removes a role.** `/admin/users` lists, exports and deletes; roles are granted by seeders, `bookshop:grant-manager`, or tinker. A super admin cannot make someone an admin, a teacher or a Bookstore manager from the panel, and cannot reactivate a deactivated account. | medium (gap) | Not built here — a slice of its own (a role and activation screen with the super-admin protections `DeleteUserAccountAction` already has). BACKLOG C8. |
| 6 | **Enrolment decisions record no actor.** Activate, reject, suspend, reinstate and the access window write the status and nothing about who did it or when; `reject` writes the status straight from the controller. Refunds, manual payments and wallet credits do record the actor. | medium (audit trail) | Not fixed here — a `decided_by` / `decided_at` pair is a schema change on a live-data table (rule 9 discipline) and belongs with the admissions work. KNOWN_ISSUES. |
| 7 | **The CMS, instructors and enrolments are gated by role alone.** Any headmaster or supervisor may edit the public website, add instructors and (KNOWN_ISSUES 12) grant a place on a paid course, while the Blade nav shows *Website CMS* only to `super_admin` and `admin`. `daily_content.manage` and `daily_content.approve` exist and are checked inside their controllers; `hr.manage` exists but the instructor screens do not check it; no `cms.manage` exists. | medium (policy) | Owner's decision: which roles run the website and admissions. Tightening is one `can:` per group plus a permission migration; widening the nav is one `@if`. Recorded in KNOWN_ISSUES next to item 12. |
| 8 | **`admin` holds `Permission::all()`**, identical to `super_admin` (KNOWN_ISSUES 10); six of the nine roles exist only if `RoleSeeder` ran (KNOWN_ISSUES 11). | — | Already recorded; owner's. Still true on the day. |
| 9 | **The whole panel is English-only.** All 24 Blade admin screens and the six Inertia admin pages outside the Bookstore (Operations, Features, Translations, Commerce, Library office, Reading alerts, Pronunciation, OTP abuse) carry hardcoded English; only `/admin/bookshop` uses `trans('shop')`. `TranslationParityTest` cannot see this — it checks keys that exist in EN against DV/AR, not strings that were never keyed. STATUS §5n deferred exactly this. | low (convention) | Not fixed — a long tail, one page at a time (Bookstore's `t` pattern). BACKLOG C9. The office reads English; families never see these screens. |
| 10 | **24 of the 36 admin screens are Blade** (users, settings, enrolments, instructors, the CMS, prayer times) against the Inertia convention. They are grandfathered by `NoNewBladeScreensTest`'s baseline and work; retiring them is IA, not a defect. | note | Recorded; BACKLOG C9 with finding 9, since a port is the moment to key the strings. |
| 11 | **`docs/AUTHENTICATION_GUIDE.md` names a `super_admin@akuru.edu.mv` test account** that no seeder creates; `admin@` is seeded with the `admin` role, so on a seeded database nobody can open `/admin/users` or `/admin/settings` without tinker. | low (docs) | **Fixed** in the guide: the line now says how a super admin is made. The walk asserts the two screens refuse `admin@`, and opens them when `SMOKE_SUPER_ADMIN` is given. |
| 12 | **The super-admin dashboard** queries `total_users`, course counts and the database size on every load; a placeholder attendance figure and a wrong month-over-month growth were removed earlier (comment in `DashboardController`). | note | Held. |
| 13 | **Clear cache** on `/admin/settings` runs `config:clear`, so a production host that deployed with `config:cache` runs uncached until the next deploy. Harmless (slower), and the deploy line re-caches. | note | Held; noted on the screen would be enough. |
| 14 | **Search on `/admin/users`** matches `national_id` and contact values with `LIKE`; parameter-bound, `super_admin` only. | note | Held. |
| 15 | **No general audit log** of admin actions exists (two domain audits: behaviour records, exam status). `trackActivity` records page visits, not writes. | low | Recorded with finding 6; a platform-wide activity log is its own decision. |

## 4. Walked

`scripts/smoke/admin.mjs`, as the seeded `admin@`: all 24 admin landing
pages open with their heading; Users and Settings answer 403 to the admin
role (and 200 to a super admin when `SMOKE_SUPER_ADMIN` is set); from an
Inertia admin screen the More menu lists the whole panel, its Blade entries
as plain links, Users and Settings absent for a plain admin; a Blade entry
opens the Blade screen; the four new CSVs download with their headers and
each screen shows the link; a CMS page is created with a `<script>` in its
body (sanitised) and deleted; a checklist item is ticked and unticked; the
translation editor opens with rows. The result is in STATUS §5hs.

## 5. What the owner still owns

- Findings 7 and 8: which roles run the website, admissions and instructors,
  and whether `admin` should hold everything.
- KNOWN_ISSUES 4: rotate the super-admin password; and make a super admin
  on each host (`AUTHENTICATION_GUIDE.md`, "Making a super admin").
- Whether to fund BACKLOG C8 (a role and activation screen) and C9 (the
  panel in three languages, one screen at a time).
