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

## 5. The layouts (2026-09-26, the owner: "did u audit admin layouts")

The first pass audited the two navigations for reachability and gating,
not the shells themselves. This pass did: `layouts/app.blade.php` and
`layouts/navigation.blade.php` (all 28 admin Blade views extend
`layouts.app`; two include-partials aside), and `Layouts/AppShell.jsx`
(every Inertia admin page).

**Held**: both shells set `lang` and `dir` from the locale (Dhivehi and
Arabic are right-to-left); `app.css` self-hosts Faruma and loads Amiri
and Cairo, so the scripts render; `[x-cloak]` is defined, so the desktop
dropdowns do not flash open before Alpine loads; the Inertia shell's
menus carry `aria-expanded`, `aria-controls` and `aria-current`, its
flash messages render centrally, and it has a language switcher.

| # | Finding | Severity | Outcome |
|---|---|---|---|
| L1 | **The Blade mobile menu stopped at the CMS.** On a phone the hamburger offered Dashboard, Enrolments, Students, Teachers, Announcements, Website CMS, Profile and Log out — no Instructors, Ops checklist, Translations, Commerce, Library, Bookstore, prayer times, Pronunciation, Users or Settings. `AdminPagesAreReachableTest` counted a link anywhere in the file, so the desktop dropdown satisfied it. | medium | **Fixed**: the same links with the same gates in the mobile block; the block is marked and the test now checks it separately. Walked at 390 px. |
| L2 | **Menus did not say whether they were open.** The desktop *More* dropdown and the hamburger had no `aria-expanded`, `aria-controls` or label (the user menu had `aria-expanded`); the mobile menu was not cloaked. | low (accessibility) | **Fixed**: attributes on both buttons, ids on both menus, `x-cloak` on the mobile one; tested and walked (the hamburger reads `aria-expanded=true` when open). |
| L3 | **No skip link and no `main` landmark** in either shell: a keyboard user tabbed through every menu link on every page. | low (accessibility) | **Fixed**: a "Skip to content" link first in the tab order and `<main id="main">` in both shells (translated in the Inertia one). Walked: the first Tab lands on it. |
| L4 | **Right-to-left mirrored the nav but not its dropdowns.** The user menu was anchored `right:0` and the *More* menu `left:0`, and the sign-out buttons `text-align:left`, so in Dhivehi or Arabic a dropdown opened away from its button. | low (RTL) | **Fixed**: logical properties (`inset-inline-end`, `inset-inline-start`, `text-align:start`). Walked in Dhivehi: the user menu opens inside the viewport. |
| L5 | **26 of 28 admin Blade screens set no `<title>`**, so every tab read the app name. | low | **Fixed** in the layout: a screen that names itself keeps its title; the rest are titled from their route (`admin.pages.index` → "Pages - Akuru"). Tested and walked. |
| L6 | **"Alerts" in the Inertia shell was hardcoded English** while every other label came from `nav.php`. | low | **Fixed**: `nav.alerts` in EN/DV/AR, whitelisted in the shared `i18n.nav`. Walked in Dhivehi. |
| L7 | **The Blade nav is 66 inline `style` attributes, 33 inline `onmouseover` handlers and hardcoded English**; the Inertia shell hardcodes its brand hexes rather than the Tailwind tokens. Two shells, two looks (maroon gradient header on white; white header on beige), one admin. | note (maintainability) | Not fixed: the port to one Inertia shell is BACKLOG C9's work, and restyling the Blade nav while it is being retired is rule 1's "while you're there". |
| L8 | **No language switcher in the Blade shell**; the locale is the URL prefix only. | note | Not fixed; the panel is English-only (finding 9), so nothing to switch to yet. Joins C9. |
| L9 | **Flash messages are per screen in Blade**: 20 of 28 views render `session('success')`, 2 render `session('error')` (the two whose controllers flash one), 3 read-only lists render neither. The Inertia shell renders both centrally. | note | Held: every screen that receives a flash shows it (the enrolment refusal that could not show was fixed in §5cn's round). Central rendering would double up on the twenty that already do; part of the C9 port. |
| L10 | **The Blade shell loads Figtree from fonts.bunny.net** while the public layout self-hosts its fonts (decision 14). Office-only; a request to a third party per page. | note | Recorded; the C9 port decides the font. |

### Every screen on a phone (the owner: "did u check the mobile layout of the admin")

The layout pass had checked one screen at 390 px. `admin-mobile.mjs` now
loads all 34 admin screens at 390 × 844 as a super admin and measures two
things the eye misses: content wider than the phone (the page-level
number), and content **cut off** inside an ancestor that hides its overflow
— which the page-level number cannot see, and which is the difference
between a table a swipe can reach and a column nobody can. Each offender
is named with the element that causes it.

| # | Finding | Severity | Outcome |
|---|---|---|---|
| L11 | **`/admin/users` cut off its table.** The card was `overflow:hidden`, so on a phone the Role, Status and **delete** columns were beyond the edge with no way to reach them. | medium | **Fixed**: the card scrolls sideways. |
| L12 | **Four admin tables had no scrolling wrapper** (instructors, prayer islands, groups, broadcasts): they fit today's data at 390 px and would have cut off the first long name. | low | **Fixed**: wrapped. |
| L13 | **The CMS headers did not wrap**: on Manage Pages the *Add New Page* button was pushed past the edge; Manage Courses and Users crammed their title and actions into one row. | low | **Fixed**: the header rows wrap. |
| L14 | **The Inertia shell's header took four rows on a phone** (title, six primary links wrapped over two rows, Alerts and account, the language switcher) — a third of the screen before any content. | low | **Fixed**: on a phone the six primary links are one row that scrolls sideways; More, Alerts, the account and the language switcher wrap beneath it and stay in view. The first version scrolled the whole bar and pushed the More button off-screen — the screenshots showed it, and the sweep, which had only checked that the button was rendered, now checks it is inside the phone. |
| L15 | **Twelve admin tables need a sideways swipe on a phone** (enrolments, payments, pages, courses, leads, funnel, OTP abuse, translations, commerce ×3, library ×3, reading alerts) inside a scrolling wrapper, with nothing to say so. | note | Held: the usual pattern for wide admin data; a "swipe" affordance or a card layout per row is C9 work. |

**Walked**: `admin-mobile.mjs` **3/3** (34 screens load, every menu button visible, nothing cut off) after the fixes; the twelve swipe tables listed in its output. `admin-layout.mjs` and `admin.mjs` re-walked on the changed shell.

**Walked**: `admin-layout.mjs` **14/14** — on a phone the menu starts closed and cloaked, the hamburger opens it and says so, it reaches the whole panel the admin role may open without Users or Settings, nothing overflows sideways; on a desktop the tab is titled after the screen, the More menu starts closed and says when it is open, the first Tab lands on the skip link; in Dhivehi the page is right-to-left and the user menu opens inside the viewport; the Inertia shell has the skip link, a main landmark, a language switcher, and Alerts in Dhivehi. `admin.mjs` and `operations.mjs` re-walked on the changed shells.

## 6. What the owner still owns


- Findings 7 and 8: which roles run the website, admissions and instructors,
  and whether `admin` should hold everything.
- KNOWN_ISSUES 4: rotate the super-admin password; and make a super admin
  on each host (`AUTHENTICATION_GUIDE.md`, "Making a super admin").
- Whether to fund BACKLOG C8 (a role and activation screen) and C9 (the
  panel in three languages, one screen at a time).
