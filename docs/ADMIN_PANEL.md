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
| `admin/users` | 8 | `role:super_admin` | Inertia (users, §5jd; roles & access, §5ig); Blade (OTP abuse) | yes, both | 6 files | `admin.mjs` |
| `admin/settings` | 2 | `role:super_admin` | Inertia (§5jb) | n/a | 4 files | `admin.mjs` |
| `admin/enrollments` | 11 | `role:super_admin\|admin\|headmaster` (§5ie); `can:payments.record` on the manual payment | Inertia (3: the two lists §5jf, the one enrolment §5jg) | yes, both lists | 7 files | `admin.mjs`, 2 others |
| `admin/payments` | 1 | `role:super_admin\|admin` + `can:payments.refund` | write only | n/a | 1 file | — |
| `admin/instructors` | 6 → 7 | `role:super_admin` (§5ie) | Inertia (list, form; §5je) | **added** | 2 → 4 files | `admin.mjs`, `admin-pages.mjs` |
| `admin/public-site` (CMS: pages, courses, research, daily content, subscriptions, leads, funnel) | 37 → 39 | `role:super_admin` (§5ie); `daily_content.manage` / `.approve` checked in the controllers | 15 Inertia (leads and funnel §5jh, subscriptions §5ji, research §5jj, daily content §5jk, pages §5jl, courses §5jm) | research, daily content, subscriptions, leads, funnel had it; **pages and courses added** | 20 files | `admin.mjs` |
| `admin/prayer-times` | 17 → 18 | role (4) + `can:prayer.manage` | 6 Inertia (islands, import, groups list and form, broadcasts list and form; §5jn) | islands, broadcasts had it; **groups added** | 4 files | `admin.mjs` |
| `admin/operations` | 5 | `role:super_admin\|admin` + `can:operations.manage` | Inertia (2) | yes, both | 2 files | `admin.mjs`, `operations.mjs` |
| `admin/translations` | 4 | `role:super_admin\|admin` + `can:translations.manage` | Inertia | yes | 1 file | `admin.mjs` |
| `admin/commerce` | 5 | `role:super_admin\|admin` + `can:commerce.manage` | Inertia | yes | 3 files | 3 walks |
| `admin/library` | 14 | `role:super_admin\|admin\|headmaster` + `can:library.manage` | Inertia (2) | yes, both | 9 files | 4 walks |
| `admin/pronunciation` | 5 | `role:super_admin\|admin` + `can:pronunciation.manage` | Inertia | yes | 1 file | `pronounce.mjs` |
| `admin/bookshop` | 37 | `role:super_admin\|admin\|bookshop_manager` + `can:bookshop.manage` | Inertia | yes, all ten | 17 files | 15 walks |

**Workspaces (2026-09-27, ADR-040, STATUS §5id).** The panel is not a
separate app: it is the sections under `/admin/*` inside the one
application. What an administrator sees is decided by their **workspace** —
one per job, `App\Support\Navigation\WorkspaceMap`:

| Workspace | Roles | Home | Bar | More |
|---|---|---|---|---|
| Institute | `super_admin` | `/admin` | Website CMS · Commerce · Library office · Bookstore · Manage users | Website & content · Shops & money · System · Mine |
| School | `admin`, `headmaster`, `supervisor`, `teacher` | `/school` (a teacher: their day) | the office's or the teacher's bar | Admissions · School year · People · Day loop · Exams · Catalog · Learn · Finance · HR · Library · Mine |
| Bookstore office | `bookshop_manager` | `/admin/bookshop` | Bookstore · Shop | Mine |
| Family / Learn | `parent` / `student` | the family portal | the family's or the pupil's bar | Learn · Mine |
| My shop / Writing / Catalog | `vendor` / `writer`, `reviewer` / `course_creator` | the shop, the desk or the queue, the catalogue | their own | Mine (+ Learn, Catalog) |

The shell shows one workspace at a time: `BuildNavigationAction` builds the
bar and the More groups for the active one, and both shells render that one
map — the Blade nav no longer lists links by hand. A person who holds
several switches from the header (a pill reading the active workspace), the
user menu or the phone menu; the choice is posted and remembered, and
opening a workspace's home (`/admin`, `/school`) makes it active too. A
person with one workspace sees no switcher. A workspace never grants
access: every route keeps its own gate and the map hides what the person
could only be refused.

**The homes.** `/admin` (the Institute) and `/school` (the School) are one
page shape (`Portal/WorkspaceHome`, `ComposeWorkspaceHomeAction`): today's
numbers for that job (`ComposeAdminTodayAction` — the Institute: new
accounts, paid today; the School: enrolments pending payment, enrolled
today, paid today, unfilled registers, ungraded exams, the roll and the
staff for a supervisor), then the workspace in parts. The Institute's parts
are the panel's: Website & content (the CMS with its eight screens, the
instructors, prayer times with its four, pronunciation), Shops & money
(Commerce, the Library office, the Bookstore), System (users, settings, the
ops checklist, the feature walkthrough, translations). The School's are
Admissions (enrolments with its payments screen), Academics (school year,
day loop, exams, catalog, teaching — each a card whose chips are its
screens) and Office (people, finance, HR, the lending library). The reading
alerts and the OTP-abuse log stay off the map on purpose (§3: opened from
their parent screen, by decision). A person opening a home they do not hold
is sent to their own, not refused.

**Who lands where.** `/dashboard` sends a person to the home of their
active workspace: the system admin to the Institute; the educational
admin, the dean and the supervisor to the School office; a teacher to their
day; a Bookstore manager to the office; a parent or student to the family
portal; a vendor to their shop, a writer to their desk, a reviewer to their
queue, a course creator to the catalogue; an account with no role to the
public course dashboard. Holding several, they land on the first by the
map's order — staff first (E7) — or the one they last switched to. The full
dashboards keep their own role-gated addresses (`/dashboard/numbers`,
`/dashboard/supervisor`, `/portal/overview`), each carrying the way home.
Nobody sees the admin panel who may not open it: the pill, the menu entry
and the route are all gated.

**Slice 2 (2026-09-27, STATUS §5ie)** made the gates agree with the
menus: the educational admin's permission set is
`App\Support\Authorization\RoleGrants::educationalAdmin()` — the school's
office and its money, the academics read only, nothing of the Institute —
shipped by migration and read by the seeder; the Institute's route groups
(the website, instructors, prayer times, commerce, the library office,
pronunciation, operations, translations) admit `super_admin` alone, the
Bookstore office `super_admin` and `bookshop_manager`, admissions
`super_admin`, `admin` and `headmaster`. The seeded logins gain
`superadmin@` (staging and local only) so the walks can run the Institute.

**Slice 3 (2026-09-27, STATUS §5if)**: the role labels people read are the
owner's names for the jobs — System admin, Educational admin, Dean,
Bookstore admin — from `lang/roles.php` in EN/DV/AR through `RoleLabels`,
on the users screen and its filter (now every role), the Blade user menu,
the linked-accounts list and the staff form. The keys in the database do
not change.

**Slice 4 (2026-09-27, STATUS §5ig)**: *Roles & access* under Manage
users — the system admin ticks the roles a person holds (by the names
people read) and deactivates or reactivates their account, never their
own System admin role or account and never the last System admin's. The
first system admin on a host is still made by tinker
(`docs/AUTHENTICATION_GUIDE.md`); every role after that comes from this
screen. All four slices of ADR-040 are built.

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
| 5 | **No screen assigns or removes a role.** `/admin/users` lists, exports and deletes; roles are granted by seeders, `bookshop:grant-manager`, or tinker. A super admin cannot make someone an admin, a teacher or a Bookstore manager from the panel, and cannot reactivate a deactivated account. | medium (gap) | **Built 2026-09-27** (ADR-040 slice 4, STATUS §5ig): *Roles & access* on every row of `/admin/users` — tick the roles by the names people read, deactivate and reactivate — with the protections `DeleteUserAccountAction` has (never the actor's own System admin role or account, never the last System admin). `UserRolesScreenTest`; walked in `admin.mjs`. |
| 6 | **Enrolment decisions record no actor.** Activate, reject, suspend, reinstate and the access window write the status and nothing about who did it or when; `reject` writes the status straight from the controller. Refunds, manual payments and wallet credits do record the actor. | medium (audit trail) | **Fixed 2026-09-27** (STATUS §5ih): additive `decided_by_user_id` / `decided_at` / `decision` on `course_enrollments`, stamped by the five Actions (`RejectEnrollmentAction` and `SetEnrollmentAccessWindowAction` new, so no status write is left in the controller), "Last decision" on the enrolment page, three CSV columns; the webhook's activation stamps nobody. `EnrollmentDecisionActorTest`; walked in `admin.mjs`. |
| 7 | **The CMS, instructors and enrolments are gated by role alone.** Any headmaster or supervisor may edit the public website, add instructors and (KNOWN_ISSUES 12) grant a place on a paid course, while the Blade nav shows *Website CMS* only to `super_admin` and `admin`. `daily_content.manage` and `daily_content.approve` exist and are checked inside their controllers; `hr.manage` exists but the instructor screens do not check it; no `cms.manage` exists. | medium (policy) | **Decided and fixed, 2026-09-27** (ADR-040 slice 2, STATUS §5ie): the website, its instructors and prayer times are the system admin's — `role:super_admin` on those groups; admissions `role:super_admin\|admin\|headmaster`. `EducationalAdminPermissionSetTest`, `EnrollmentDecisionRouteTest`. |
| 8 | **`admin` holds `Permission::all()`**, identical to `super_admin` (KNOWN_ISSUES 10); six of the nine roles exist only if `RoleSeeder` ran (KNOWN_ISSUES 11). | — | **Fixed, 2026-09-27** for the first half: `RoleGrants::educationalAdmin()` is the set, shipped by migration `2026_09_27_000001` and read by the seeder; `admin` is now migration-created too; since STATUS §5ij every school role is (KNOWN_ISSUES 11 closed). |
| 9 | **The whole panel is English-only.** All 24 Blade admin screens and the six Inertia admin pages outside the Bookstore (Operations, Features, Translations, Commerce, Library office, Reading alerts, Pronunciation, OTP abuse) carry hardcoded English; only `/admin/bookshop` uses `trans('shop')`. `TranslationParityTest` cannot see this — it checks keys that exist in EN against DV/AR, not strings that were never keyed. STATUS §5n deferred exactly this. | low (convention) | A long tail, one page at a time (Bookstore's `t` pattern). BACKLOG C9. **Started 2026-09-27** (STATUS §5jb–§5jl): System Settings, Manage users, the instructors screens, the three enrolment screens, the CMS leads, funnel and daily subscriptions lists, the research, daily content and pages screens are Inertia with every string keyed EN/DV/AR. The office reads English; families never see these screens. |
| 10 | **24 of the 36 admin screens are Blade** (users, settings, enrolments, instructors, the CMS, prayer times) against the Inertia convention. They are grandfathered by `NoNewBladeScreensTest`'s baseline and work; retiring them is IA, not a defect. | note | Recorded; BACKLOG C9 with finding 9, since a port is the moment to key the strings. **0 of 36 since 2026-09-27**: System Settings ported first (STATUS §5jb), then Manage users (§5jd), the instructors list and form (§5je), the enrolment and payments lists (§5jf), the one-enrolment page (§5jg), the CMS leads and funnel lists (§5jh), the daily subscriptions list (§5ji), the research list and form (§5jj), the daily content calendar, form and queue (§5jk), the pages list, form and preview (§5jl), the courses list and form (§5jm) and prayer times — islands, import, groups, broadcasts (§5jn). **Every `/admin/*` screen is Inertia**; `resources/views/admin/` is gone and `NavigationMap` carries no `hard` flag under the panel. The two full dashboards (`/dashboard/numbers`, `/dashboard/supervisor`) followed in slice 13 (§5jo), ported as they are at their own addresses. **No screen an administrator is sent to is Blade any more.** Whether the two dashboard addresses stay is the owner's IA decision (BACKLOG C9). |
| 11 | **`docs/AUTHENTICATION_GUIDE.md` names a `super_admin@akuru.edu.mv` test account** that no seeder creates; `admin@` is seeded with the `admin` role, so on a seeded database nobody can open `/admin/users` or `/admin/settings` without tinker. | low (docs) | **Fixed** in the guide: the line now says how a super admin is made. Since 2026-09-27 `UserSeeder` plants `superadmin@` (`super_admin`; staging and local only — production still makes its own by tinker), and the walks run the Institute as that login. |
| 12 | **The super-admin dashboard** queries `total_users`, course counts and the database size on every load; a placeholder attendance figure and a wrong month-over-month growth were removed earlier (comment in `DashboardController`). | note | Held. |
| 13 | **Clear cache** on `/admin/settings` runs `config:clear`, so a production host that deployed with `config:cache` runs uncached until the next deploy. Harmless (slower), and the deploy line re-caches. | note | **Fixed 2026-09-27** (STATUS §5il): `ClearApplicationCachesAction` runs `config:cache` where the configuration was cached and `config:clear` where it was not, never `route:cache`; the screen says which it will do and the flash says which it did. `ClearCachesKeepsConfigCachedTest` (Artisan facade mocked, since a real `config:cache` would outlive the test). |
| 14 | **Search on `/admin/users`** matches `national_id` and contact values with `LIKE`; parameter-bound, `super_admin` only. | note | Held. |
| 15 | **No general audit log** of admin actions exists (two domain audits: behaviour records, exam status). `trackActivity` records page visits, not writes. | low | Recorded with finding 6; a platform-wide activity log is its own decision. |

## 4. Walked

`scripts/smoke/admin.mjs`, as the seeded `admin@` and `superadmin@` (since
STATUS §5ie): the educational admin lands on the School office, opens the
two admissions pages, is refused every one of the 25 Institute screens and
the CSVs behind them, and from an Inertia School screen the More menu
reaches the whole School with nothing of the Institute; the system admin
lands on the Institute, opens all 25 landing pages with their headings and
the enrolment payments list, the More menu reaches the whole Institute; the
four CSVs download with their headers and each screen shows the link; a CMS
page is created with a `<script>` in its body (sanitised) and deleted; a
checklist item is ticked and unticked; the translation editor opens with
rows. The results are in STATUS §5hs and §5ie.

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

| L16 | **The super-admin dashboard did not fit a phone.** The owner's own phone showed it (2026-09-26): the main grid was `1fr 320px` with no breakpoint, so at 390 px the *Recent Enrollments* card was 40 px wide beside System Health and Prayer Times, and the lower two-column grid squeezed the same way. The sweep had covered `/admin/*` and not the `/dashboard` landing that sends an administrator there. | medium | **Fixed**: one column on a phone, the side column from `lg:`; the two landings (`/dashboard`, `/portal/overview`) are in the sweep now — 36 screens. |

**Walked**: `admin-mobile.mjs` **3/3** (36 screens load, every menu button visible, nothing cut off) after the fixes; the twelve swipe tables listed in its output. `admin-layout.mjs` and `admin.mjs` re-walked on the changed shell.

**Walked**: `admin-layout.mjs` **14/14** — on a phone the menu starts closed and cloaked, the hamburger opens it and says so, it reaches the whole panel the admin role may open without Users or Settings, nothing overflows sideways; on a desktop the tab is titled after the screen, the More menu starts closed and says when it is open, the first Tab lands on the skip link; in Dhivehi the page is right-to-left and the user menu opens inside the viewport; the Inertia shell has the skip link, a main landmark, a language switcher, and Alerts in Dhivehi. `admin.mjs` and `operations.mjs` re-walked on the changed shells.

### Every page, desktop and phone (the owner: "check each and every admin page, desktop and mobile — the layouts, all the tabs are there like home, back")

`admin-pages.mjs` loads every admin landing page and the detail, create and
edit pages it discovers from each index — 40 pages — at 1400 × 950 and at
390 × 844, as a super admin, and asks of each: does it load with a heading;
is its navigation there (the Blade bar with Dashboard and More, or the
Inertia shell with its bar, More and Alerts); is there a way home (a link
to `/dashboard`); on a page that is not an index, a way back (a link to its
section's index in the content); is anything wider than the viewport, cut
off inside a hidden-overflow ancestor, or is the menu button off-screen.
Each page is listed with what it lacks.

| # | Finding | Severity | Outcome |
|---|---|---|---|
| L17 | **No Inertia admin page had a way home.** The shell's "Akuru" wordmark was a label; the Blade bar's logo links to the dashboard, the Inertia shell's did not — ten pages (the staff overview, Operations, Features, Translations, Commerce, the Library office, Reading alerts, Pronunciation, OTP abuse, Deleted courses, the Bookstore office). | medium | **Fixed**: the wordmark links to `/dashboard`, the Blade router that sends each person to their own landing. |
| L18 | **Three Inertia sub-pages had no way back** to the screen they belong to: OTP abuse (→ Users), Reading alerts (→ Library office), Deleted courses (→ Manage Courses). | low | **Fixed**: a back link on each. |
| L19 | **The instructor form had no heading** — a breadcrumb (which carries its back link) and the form. | low | **Fixed**: "Add instructor" / "Edit instructor". |
| L20 | **On the payments screen the refund form's select stuck 15 px past a 1400 px desktop**: its controls sat on one line. | low | **Fixed**: they wrap. |

Sections with no seeded rows to discover an edit page from (instructors,
research, daily content, prayer groups and broadcasts) share one `form`
view between create and edit, so their create pages cover the form;
Bookstore invoices have no seeded row and are covered by `money.mjs`.

**Walked**: `admin-pages.mjs` **3/3** after the fixes (40 pages, both
viewports, nothing lacking); `admin-mobile.mjs`, `admin-layout.mjs`,
`admin.mjs` and `operations.mjs` re-walked on the changed shell.

| L21 | **Nothing answered at `/admin`.** The panel had thirteen sections and no front door; an administrator reached them from a menu or by URL. | medium | **Fixed**: `/admin`, an Inertia hub of the sections the person may open, described in three languages, linked first in both menus; `AdminHubTest` (a super admin sees all, a Bookstore manager one, a teacher 403, a guest the login). In both sweeps. |
| L22 | **The hub was a flat list.** Thirteen cards in one grid, in map order, with no grouping; the Blade More dropdown mixed school links and fourteen admin links with only rules between them (the owner: "still admin page is too much complicated"). | medium | **Fixed**: the panel in four parts — Admissions, Website & content, Shops & money, System — on the hub (a row of cards per part, a part-link row on top, the screens inside a section on its card), in the Inertia More menu (the admin column headed by the parts) and in both Blade menus (the same headings, plus *School*). `NavigationMap::adminPanel()` is the one map; `AdminHubTest` (3) pins the parts, the sections, the inner screens and the headings in both shells and in Dhivehi. `admin-hub.mjs` (20/20) walks it on desktop and phone. |
| L23 | **`/dashboard` and `/admin` did not explain each other.** An administrator's landing was a page of numbers with no link to the panel except in a menu, and the hub's only way back was a link at its bottom; the owner could not tell which was for what. | medium | **Fixed**: the rule in one line — *the dashboard is today's numbers, the Admin panel is where things are managed* — with an *Admin panel →* button on the super-admin dashboard, the supervisor dashboard and the staff overview (offered to whoever may open it), and *← Dashboard* with the rule at the top of the hub. EN/DV/AR. `AdminHubTest` (4th test) pins all four landings and a teacher's absence of the door; `admin-hub.mjs` walks the round trip (24/24; 27 with a super admin). |
| L24 | **Two homes.** Even with each linking the other (L23), an administrator still landed on a numbers page and went looking for the doors; offered one page or two, the owner said "I don't know". | medium | **Fixed**: one home. `/dashboard` sends administrators to `/admin`; the hub leads with a *Today* strip — pending payment, enrolled today, paid today, new accounts, unfilled registers, ungraded exams for the institute's roles; the roll and the staff for a supervisor — each tile opening where its number comes from, then a *Full dashboard* / *Staff overview* link. The full dashboards keep their own role-gated addresses (`/dashboard/numbers`, `/dashboard/supervisor`, `/portal/overview`). Numbers are asked of owning-domain Actions (rule 3). `AdminHubTest` (4th test rewritten), `RoleLandingTest`, `CountingPeopleTest`, `StaffOverviewTest` updated; `admin-hub.mjs` 26/26 (30 with a super admin); both sweeps carry `/dashboard/numbers`. The two dashboards are Inertia since C9 slice 13 (STATUS §5jo); retiring their addresses is the IA decision BACKLOG C9 leaves with the owner. |
| L25 | **The Inertia shell had no brand bar.** `/admin` and every Inertia screen opened under a plain white strip with maroon text links, while every Blade screen opened under the wine bar with the logo, "Akuru Institute" and white links; an administrator sent to `/admin` at sign-in read it as a page with no header (the owner's phone screenshot, 2026-09-27). | medium | **Fixed**: `AppShell.jsx` renders the same brand bar — the wine gradient, the on-dark logo, the wordmark linking home, white primary links with the current one highlighted, More, Alerts (gold badge), the account pill with an initial, Log out, the language switcher — sticky from `sm:` (on a phone the bar wraps, so it stays in the flow); the page title moves into the content as its `h1`, as on a Blade page; the More panel is capped at 80 vh and scrolls. Walked: `nav.mjs` 14/14, `admin-layout.mjs` (a brand-bar step added), `admin-hub.mjs`, `admin-pages.mjs`, `admin-mobile.mjs`. |
| L26 | **A person with several identities saw one.** `/dashboard` picks one home by precedence (staff first); the only other identity ever offered was a *Family view* pill for a staff member who is also a parent (E7). A parent who is also a vendor and a writer, or a super admin who is also a learner, had to find the other homes in the More menu. A vendor, writer, reviewer or course creator with no other role landed on the public "My Dashboard", a course page (the owner, 2026-09-27: "any one will see the admin panel? students, parents? vendors? writers?"). | medium | **Fixed**: `ResolveDashboardLandingAction` lists every home a person holds (`views`: admin panel, Bookstore office, a teacher's day, Family, Learn, My shop, Write, Review, Catalog — by role, no query) and lands a lone vendor/writer/reviewer/course creator on their job. Both shells offer the other views: pills in the Inertia header (the current one hidden), *Your views* in the Blade user menu and phone menu; a person with one identity sees none. EN/DV/AR. `DualIdentityLandingTest` (2 new), `RoleLandingTest` (1 new); `views.mjs` 8/8. |
| L27 | **One person saw the union of every role's screens, in two shells that each decided by hand.** An administrator got the school's bar, eleven More groups and the admin panel at once; the `admin` role holds every permission; a person with several roles reached the others through the menu, if at all (the owner, 2026-09-27: "still login and admin setting is really confusing … their setting should be seen when he changes to his specific role"). | high | **Fixed** (slice 1 of ADR-040): workspaces — Institute, School, Bookstore office, Family, Learn, My shop, Writing, Catalog — one per job, each with its home, bar and More groups; the shell shows one at a time and a switcher for the others; both shells render the one map; `/admin` and `/school` are the workspace homes with today's numbers and the parts; `/dashboard` goes to the active home. `WorkspacesTest` (6), `DualIdentityLandingTest`, `AdminPagesAreReachableTest` (rewritten: the Blade nav renders the map and reaches every landing as the role that runs it), `AdminHubTest`, `AdminPanelAuditTest`, `RoleLandingTest`; `workspaces.mjs`, `admin-hub.mjs`, `admin.mjs`, `nav.mjs`, `admin-layout.mjs`, the two sweeps with `/school`. |

## 6. What the owner still owns


- Findings 7 and 8: which roles run the website, admissions and instructors,
  and whether `admin` should hold everything.
- KNOWN_ISSUES 4: rotate the super-admin password; and make a super admin
  on each host (`AUTHENTICATION_GUIDE.md`, "Making a super admin").
- Whether to fund BACKLOG C8 (a role and activation screen) and C9 (the
  panel in three languages, one screen at a time).
