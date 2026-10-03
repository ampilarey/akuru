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
| Institute | `super_admin` | `/admin` | Website CMS · Commerce · Library office · Bookstore · Manage users | Website & content · Shops & money · System · Personal |
| School | `admin`, `headmaster`, `supervisor`, `teacher` | `/school` (a teacher: their day) | the office's or the teacher's bar | Admissions · School year · People · Day loop · Exams · Catalog · Teaching · Finance · HR · Library · Communication · My work · Personal |
| Bookstore office | `bookshop_manager` | `/admin/bookshop` | Bookstore · Shop | Personal |
| Family / Learn | `parent` / `student` | the family portal | the family's or the pupil's bar | Communication · Education · Evaluation · Other · Personal |
| My shop / Writing / Catalog | `vendor` / `writer`, `reviewer` / `course_creator` | the shop, the desk or the queue, the catalogue | their own | Personal (+ Catalog) |

Since SIGN_IN_PLAN ID1 (STATUS §5jz) a workspace's More menu is only its own: a vendor sees their shop and the Personal group, nothing of the school; Home, first in the panel, is the workspace's home.

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
| 10 | **24 of the 36 admin screens are Blade** (users, settings, enrolments, instructors, the CMS, prayer times) against the Inertia convention. They are grandfathered by `NoNewBladeScreensTest`'s baseline and work; retiring them is IA, not a defect. | note | Recorded; BACKLOG C9 with finding 9, since a port is the moment to key the strings. **0 of 36 since 2026-09-27**: System Settings ported first (STATUS §5jb), then Manage users (§5jd), the instructors list and form (§5je), the enrolment and payments lists (§5jf), the one-enrolment page (§5jg), the CMS leads and funnel lists (§5jh), the daily subscriptions list (§5ji), the research list and form (§5jj), the daily content calendar, form and queue (§5jk), the pages list, form and preview (§5jl), the courses list and form (§5jm) and prayer times — islands, import, groups, broadcasts (§5jn). **Every `/admin/*` screen is Inertia**; `resources/views/admin/` is gone and `NavigationMap` carries no `hard` flag under the panel. The two full dashboards (`/dashboard/numbers`, `/dashboard/supervisor`) followed in slice 13 (§5jo), ported as they are at their own addresses. **No screen an administrator is sent to is Blade any more.** The two dashboard addresses **stay** (decided 2026-09-28, STATUS §5jv): the hub is the landing, the full dashboard one link away. C9 closed. |
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
| L10 | **The Blade shell loads Figtree from fonts.bunny.net** while the public layout self-hosts its fonts (decision 14). Office-only; a request to a third party per page. | note | Recorded; the C9 port decides the font. **Closed 2026-10-02 (C15 slice 5, STATUS §5np)**: Figtree, Amiri and Cairo come with `app.css` from `@fontsource` packages; no layout names a font host. |

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
| L24 | **Two homes.** Even with each linking the other (L23), an administrator still landed on a numbers page and went looking for the doors; offered one page or two, the owner said "I don't know". | medium | **Fixed**: one home. `/dashboard` sends administrators to `/admin`; the hub leads with a *Today* strip — pending payment, enrolled today, paid today, new accounts, unfilled registers, ungraded exams for the institute's roles; the roll and the staff for a supervisor — each tile opening where its number comes from, then a *Full dashboard* / *Staff overview* link. The full dashboards keep their own role-gated addresses (`/dashboard/numbers`, `/dashboard/supervisor`, `/portal/overview`). Numbers are asked of owning-domain Actions (rule 3). `AdminHubTest` (4th test rewritten), `RoleLandingTest`, `CountingPeopleTest`, `StaffOverviewTest` updated; `admin-hub.mjs` 26/26 (30 with a super admin); both sweeps carry `/dashboard/numbers`. The two dashboards are Inertia since C9 slice 13 (STATUS §5jo); their addresses stay (decided 2026-09-28, STATUS §5jv). |
| L25 | **The Inertia shell had no brand bar.** `/admin` and every Inertia screen opened under a plain white strip with maroon text links, while every Blade screen opened under the wine bar with the logo, "Akuru Institute" and white links; an administrator sent to `/admin` at sign-in read it as a page with no header (the owner's phone screenshot, 2026-09-27). | medium | **Fixed**: `AppShell.jsx` renders the same brand bar — the wine gradient, the on-dark logo, the wordmark linking home, white primary links with the current one highlighted, More, Alerts (gold badge), the account pill with an initial, Log out, the language switcher — sticky from `sm:` (on a phone the bar wraps, so it stays in the flow); the page title moves into the content as its `h1`, as on a Blade page; the More panel is capped at 80 vh and scrolls. Walked: `nav.mjs` 14/14, `admin-layout.mjs` (a brand-bar step added), `admin-hub.mjs`, `admin-pages.mjs`, `admin-mobile.mjs`. |
| L26 | **A person with several identities saw one.** `/dashboard` picks one home by precedence (staff first); the only other identity ever offered was a *Family view* pill for a staff member who is also a parent (E7). A parent who is also a vendor and a writer, or a super admin who is also a learner, had to find the other homes in the More menu. A vendor, writer, reviewer or course creator with no other role landed on the public "My Dashboard", a course page (the owner, 2026-09-27: "any one will see the admin panel? students, parents? vendors? writers?"). | medium | **Fixed**: `ResolveDashboardLandingAction` lists every home a person holds (`views`: admin panel, Bookstore office, a teacher's day, Family, Learn, My shop, Write, Review, Catalog — by role, no query) and lands a lone vendor/writer/reviewer/course creator on their job. Both shells offer the other views: pills in the Inertia header (the current one hidden), *Your views* in the Blade user menu and phone menu; a person with one identity sees none. EN/DV/AR. `DualIdentityLandingTest` (2 new), `RoleLandingTest` (1 new); `views.mjs` 8/8. |
| L27 | **One person saw the union of every role's screens, in two shells that each decided by hand.** An administrator got the school's bar, eleven More groups and the admin panel at once; the `admin` role holds every permission; a person with several roles reached the others through the menu, if at all (the owner, 2026-09-27: "still login and admin setting is really confusing … their setting should be seen when he changes to his specific role"). | high | **Fixed** (slice 1 of ADR-040): workspaces — Institute, School, Bookstore office, Family, Learn, My shop, Writing, Catalog — one per job, each with its home, bar and More groups; the shell shows one at a time and a switcher for the others; both shells render the one map; `/admin` and `/school` are the workspace homes with today's numbers and the parts; `/dashboard` goes to the active home. `WorkspacesTest` (6), `DualIdentityLandingTest`, `AdminPagesAreReachableTest` (rewritten: the Blade nav renders the map and reaches every landing as the role that runs it), `AdminHubTest`, `AdminPanelAuditTest`, `RoleLandingTest`; `workspaces.mjs`, `admin-hub.mjs`, `admin.mjs`, `nav.mjs`, `admin-layout.mjs`, the two sweeps with `/school`. |
| L28 | **The Inertia shell overflowed a phone, and the mobile walk could not see it.** The owner's screenshot (2026-09-28): the header's link row and language switcher ran past the screen and Safari zoomed the whole page out. The `<nav>` is a flex item whose minimum width is its content's — the nowrap link row laid end to end, 608 px on a 390 px phone — so the scroller never scrolled. `admin-mobile.mjs` measured `scrollWidth − innerWidth` under mobile emulation, where a wide document widens the layout viewport to match, so the difference read 0 on exactly the broken page. | medium | **Fixed** (STATUS §5jq): `w-full min-w-0` on the nav; the walk measures against `screen.width` and then found three more pages the phone zooms out on — the enrolments list (a positioned `sr-only` heading outside its unpositioned scroller), the library office (a six-button row that did not wrap) and the bookshop office (ten tables with no scroller, two grid columns with no `min-w-0`) — all fixed. 39 screens measure 390/390 in English and Dhivehi, at 360 px and with text scaled 1.3×. |

## 6. What the owner still owns


- Findings 7 and 8: which roles run the website, admissions and instructors,
  and whether `admin` should hold everything.
- KNOWN_ISSUES 4: rotate the super-admin password; and make a super admin
  on each host (`AUTHENTICATION_GUIDE.md`, "Making a super admin").
- Whether to fund BACKLOG C8 (a role and activation screen) and C9 (the
  panel in three languages, one screen at a time).
- From §7 (2026-10-02): the three host settings only the owner can check
  (P7, P8 — the cache store, OPcache, and the queue worker of BACKLOG C6).
  The tab bar's five (M7) were left to the builder on 2026-10-03 and are
  built (STATUS §5nu).

## 7. Performance and the phone (2026-10-02, the owner: "full audit of the system admin panel, recommend enhancements for better performance and user interface specifically for mobile")

The earlier passes asked whether every screen is reachable, gated,
titled, and fits a phone. This one asks how much each screen costs and
how it feels under a thumb. Everything below was measured on `main` at
`79ea4aa` (#649) with the seeded dataset, so query counts are exact and
times are indicative; the live host was probed from outside for what it
sends, not for what it does inside.

**How.** The 46 parameterless `GET` screens under `/admin/*`, plus `/admin`
and `/dashboard/numbers` — 48 pages. (1) Each dispatched through the kernel
as the seeded super admin with the query log on: status, wall time, queries,
repeated query shapes, and the Inertia props payload by key. (2) Each loaded
in Chromium at 390 × 844 under phone emulation: page width against the
phone's, DOM size, page height, every visible tap target's box, every form
control's font size, every table's width and whether it stacks, labels,
fixed bars, console errors. (3) The Vite manifest, raw and compressed.
(4) The live host: asset and page headers, time to first byte for a static
file, `/up` and three pages. The probes were throwaway; what they found is
here, and the width check joined `admin-mobile.mjs`.

### What held

- **Every screen answers 200 in 9 to 79 queries**; none has the classic
  N+1 (one query per row). The fixed cost of a signed-in request is 15
  queries, every one under a millisecond: the session, roles,
  permissions (from the cache table), a student lookup, linked accounts,
  two unread counts, two cache reads, two `settings` table checks, the
  activity row, and the session write.
- **The live host sends assets well**: HTTP/2 (h3 advertised), Brotli, and
  since #633 the hashed assets carry `immutable` for a year while pages
  are `no-store`. The 1.7 MB script arrives as 368 KB.
- **47 of 48 pages fit a phone's width**; every page shows its menu
  control; the header is one 72 px row (Cursor's §5nc–§5nj); no page has
  a fixed bar stealing height; nothing errors in the console.
- **The shell is installable**: a web manifest, `theme-color`, the Apple
  tags and a service worker that precaches the shell and serves an
  offline page.
- **The rich text editor is already its own chunk** (390 KB, loaded only
  on the screens that edit a body).
- **Form controls are 16 px on 41 of 48 pages**, so iOS does not zoom
  when a field is tapped (the exceptions are M4).

### Findings — performance

| # | Finding | Severity | Recommendation |
|---|---|---|---|
| P1 | **One script carries every page of every workspace.** `resources/js/app.jsx` resolves pages with `import.meta.glob(…, { eager: true })`, so the 247 page components — the vendor's shop designer, the Hifz dashboards, the parent portal, the Qur'an player — are one 1,743 KB file (368 KB Brotli, 404 KB gzip) that an administrator downloads before the first admin screen draws, and again after every deploy because the hash changes. Vite's own build prints the warning. The next largest piece is the 90 KB stylesheet (16 KB compressed). | **high** — the single largest cost on a phone | Drop `eager: true` so each page is its own chunk loaded on first visit (Inertia's documented default), with a `manualChunks` vendor split for React and Inertia so the shared part stays cached across deploys. Expected first load for an admin: the shell plus one page, well under a third of today. Zero behaviour change; verified by `npm run build` and the sweeps. **Built 2026-10-02 (C15 slice 1, STATUS §5nl)**: the glob is lazy, React and Inertia are a `vendor` chunk; the first load is 437 KB (121 KB gzip) against 1,833 KB (420 KB), plus the one page (4–75 KB). |
| P2 | **Every admin page ships its whole phrase book.** The `t` prop is `trans('admin')` — 43.8 KB of JSON on 38 of the 48 pages — or `trans('shop')`, 93.2 KB, on the six Bookstore office screens; it is sent on the first load **and on every Inertia visit**, because Inertia resends page props each time. Props run 55 KB on a typical page and 125 KB on the Bookstore office; the translations screen sends 143 KB (its 132 KB `groups`), the islands hub 100 KB (every island, P5). The shell adds 6.2 KB of `i18n.learn` to every page, admin or not. | medium | Inertia Laravel 3.1 has `Inertia::once()`: a prop sent on the first load and remembered by the client, keyed here by locale and file. Wrap `t` (and `i18n`) in it in the handful of controllers and the shared middleware; the pages do not change. Pair with P5 for the two oversized lists. **Built 2026-10-02 (C15 slice 2, STATUS §5nm)**: `Phrases::once()` keyed `t:<file>:<locale>` with a 30-minute TTL at 84 sites in 53 controllers, `i18n` in `shareOnce()`; a visit after the first carries neither (`OncePropsTest`, `once-props.mjs` 9/9 in Dhivehi). The two oversized lists stay with P5. |
| P3 | **Two synchronous writes per page view.** `TrackUserActivity` sits on 943 of the 1,181 routes (217 of them admin). After the response is built — but before it is sent — it inserts a `user_activities` row and runs an `updateOrCreate` on `dashboard_analytics`, both inline. In this probe the insert took 700 ms twice (a local fsync stall; the point is that a stall there holds the page). The table has no pruning. | medium | Make the middleware terminable (`terminate()`, after the response has gone), record `page_view` for GETs only when the analytics screen will use it (today one screen reads either table), and prune with `model:prune` after 90 days. A queue is the fuller answer and waits on C6. **Built 2026-10-02 (C15 slice 3, STATUS §5nn)**: the writes moved to `terminate()`, after the response; both tables pruned at 90 days by `akuru:prune-expired`; `page_view` rows kept (the analytics screen reads them). `TrackUserActivityTest`. |
| P4 | **Settings are read whole, per key, uncached.** `Setting::get()` loads the entire `settings` table on every call: 12 times on the Library settings screen, 6 on the Bookstore office. The `View::composer('*')` in `AppServiceProvider` asks `information_schema` whether the table exists, twice per request. | low | Memoise `Setting::all()` per request (a static, cleared on `set`) behind the existing 10-minute cache; replace `Schema::hasTable` with a config flag or a `rescue`. Ten lines. **Built 2026-10-02 (C15 slice 3, STATUS §5nn)**: the table is read once per request (the memo is tied to the request object and forgotten on write); the table check once per process. Library settings 23 → 18 queries, System settings 11 → 8. `SettingMemoTest`. |
| P5 | **Four screens render everything they have.** Prayer islands: 205 rows, 1,709 DOM nodes, 11,885 px tall on a phone. Translations: 185 rows per group, 1,949 nodes, 11,419 px, each row a textarea. Feature testing: 154 items, 1,349 nodes, 16,416 px — nineteen phone screens of scrolling. The Bookstore office: ten tables one under another, 654 nodes, 10,601 px. The median admin page is 90 nodes and 1,013 px. | low | Paginate islands and translations on the server (25 a page, with the search they already have); collapse Feature testing by audience with the counts on the headings; give the Bookstore office the section jump bar the vendor portal got (STATUS §5mp) and fold each table under its heading. **Built 2026-10-02 (C15 slice 4, STATUS §5no)**: islands 25 a page with a search (11,885 → 1,891 px); translations paged by group with the search and the suspect filter in the URL (11,419 → 2,011 px; the 132 KB catalog no longer travels); Feature testing folded to 17 headings (16,416 → 1,478 px); the office has the jump chips (its tables stay unfolded — that is M2's stacking work). `long-pages.mjs` 17/17. |
| P6 | **Fonts from third parties, in the render path.** `app.css` opens with `@import url('https://fonts.googleapis.com/…')` for Amiri and Cairo, which blocks every shell page's first paint on a round trip to Google — on every page, in English too. The Blade shell still loads Figtree from `fonts.bunny.net` (L10). The public layout self-hosts (decision 14); only Faruma (15 KB) is self-hosted here. | low | Self-host subsetted `woff2` for Amiri and Cairo with `font-display: swap`, loaded by `@font-face` and preloaded only when the locale is Arabic or Dhivehi; drop the Figtree link. **Built 2026-10-02 (C15 slice 5, STATUS §5np)**: `@fontsource` packages for Figtree, Amiri and Cairo imported in `app.css` (split by script with `unicode-range`, so an English page downloads four Latin files and nothing Arabic); the Google `@import`, the bunny.net links in three layouts and the Google Translate path's Cairo URL dropped. `FontsAreSelfHostedTest`; `fonts.mjs` 13/13. |
| P7 | **The default cache store is the database.** `CACHE_STORE=database` in `.env.example`; Spatie's permission cache and the site settings read from the `cache` table — two queries on every request, which is why they appear in the fixed cost above. Whether production overrides it was not checked (the host's `.env` is not for this repo). | note (host) | Owner: on the cPanel host, `CACHE_STORE=file` (or APCu, or Redis if the plan has it) removes both queries; `php artisan cache:clear` after the change. Nothing in the code depends on the store. |
| P8 | **Time to first byte on the live host, from this container**: a static file 0.9–1.2 s, `/up` 1.2–1.3 s, the sign-in page and two public pages 1.1–1.5 s. So the network from here is most of it and PHP adds 0.2–0.5 s — fine for a shared host, and the same order as the local probe's 50–130 ms once the network is taken out. Not measured: whether OPcache is on. | note (host) | Owner: `php -i | grep opcache.enable` on the host; the pull line already runs `composer install --optimize-autoloader` and `config:cache`. Adding `php artisan view:cache && php artisan event:cache` to it is safe (never `route:cache`, by rule). |

### Findings — the phone

| # | Finding | Severity | Outcome |
|---|---|---|---|
| M1 | **The peer reviewers page was 79 px wider than a phone.** Its table sits in a scroller, but the Remove column's `sr-only` heading is positioned and the scroller was not, so the heading escaped to the page — the exact shape of STATUS §5jq's enrolments list — and Safari would zoom the page out. It shipped on 2026-09-29 (R3b) and nothing said so, because `admin-mobile.mjs` listed the 40 screens of 2026-09-28 and not the ten added since. | medium | **Fixed**: `relative` on the scroller. `admin-mobile.mjs` now carries all 50 screens (the five Library office pages, Lending, the five Bookstore office pages) — **3/3**, nothing cut off, 50 of 50 at the phone's width. |
| M2 | **26 tables on 21 screens need a sideways swipe**, and only 2 of the panel's 43 tables use `.table-stack` (Bookstore Akuru stock and Customers). On Manage users the Role, Registered and **Action** columns — Roles & access, Delete — sit 443 px past the edge; payments 368 px (the Refund form with them); the funnel 373; the Library office 326; CMS pages 296; enrolments 277; insights 256; courses 245; gift cards 233; promotions 229; islands 196. L15 held this as "the usual pattern"; with the portal's `.table-stack` (STATUS §5js) in the stylesheet it is now a `data-label` per cell. | medium | Stack the listing tables, the office's first: users, enrolments, payments, pages, courses, news, instructors, leads, funnel, subscriptions, the Library office and insights and promotions and reviewers, gift cards, islands, broadcasts, reading alerts, OTP abuse, Lending. Action cells take `table-actions`, which already gives links 32 px below `sm` (§5mp). **C15 slice 6**, one group of screens per PR. **Group one built 2026-10-02 (slice 6b, STATUS §5nr)**: users, enrolments, payments, pages, courses, news, instructors are cards below `sm` with 44 px actions; `admin-mobile.mjs`'s swipe list drops from 23 tables to 16. **Group two built 2026-10-02 (slice 6c, STATUS §5ns)**: the Library office's six, insights, promotions, reading alerts, leads, funnel, OTP abuse, Commerce's three, islands and the numbers dashboard — the swipe list is down to the Bookstore office's four; a labelled cell is a grid so a cell's children stack. **Group three built 2026-10-02 (slice 6d, STATUS §5nt)**: the Bookstore office's thirteen tables (vendors, slips, orders, refunds, payouts, balances, history, invoices, tax report, funnels, low stock, rewards, referrals); `admin-mobile.mjs` reports no sideways scrolling on any of its 50 screens. **Closed.** |
| M3 | **Most tap targets are under 32 px.** 889 of the 1,246 visible targets (the skip link aside) measure under 32 px in one direction; nine screens have ten or more — Feature testing 314 (every mark pill), translations 195, CMS pages 56, users 53, courses 37, the Bookstore office 34, the Library office 20, enrolments 9, payments 7. They are the row actions (View, Roles & access, Delete, Refund), the `text-xs` links (Export CSV, the ← back links, the section chips) and the inline checkboxes. Apple and Android both ask for 44 px. | medium | A phone rule in `app.css` — below `sm`, a link or button inside `td`, a `.btn-link`, a back link and the chip rows get `min-height: 2.75rem` with matching padding, and checkboxes 1.5 rem — fixes most of it without touching pages; M2's stacking covers the row actions. **Built 2026-10-02 (C15 slice 6a, STATUS §5nq)**: below `sm`, a link or button in a table cell, a chip, a back or section link and the two button classes reach 44 px; checkboxes and radios are 24 px. `phone-targets.mjs`: 238 of 238 targets on 21 screens. |
| M4 | **204 of 386 form controls are under 16 px, on 7 screens**, so iOS zooms the page when one is tapped and leaves it zoomed: the translations grid (186 textareas at `text-sm`), the refund forms on payments (12), the Akuru stock row (2), the campaign shop select, and three textareas (recipient refs, member refs, the page body). | low | One rule: below `sm`, `input, select, textarea { font-size: 16px }`. **Built 2026-10-02 (slice 6a, STATUS §5nq)** — with a `:not()` chain so the rule outranks `.text-sm`; 143 of 143 visible controls on 21 screens read 16 px. |
| M5 | **38 fields on 11 screens have a placeholder and no label** — the searches on users and translations, the refund reasons, the gift-card form, the Library office's add-item fields, the pronunciation model form, the Bookstore office's note fields, news categories, promotions, the reviewer email. A placeholder vanishes when typing starts, a screen reader announces nothing, and the phone's keyboard cannot pick its mode. | low | `aria-label` (or a visible label) on each, and `inputMode`/`autoComplete` where the field is a number, an email or a phone. **Built 2026-10-02 (slice 6a, STATUS §5nq)**: 137 placeholder-only controls across 31 Inertia pages named from their placeholder, eleven more by hand; 143 of 143 visible controls on 21 screens have a name. `inputMode` left for the phone-first table pass. |
| M6 | **The four long pages (P5) have no way to jump**: twelve to nineteen phone screens of scrolling with the filters at the top and nothing fixed. | low | Folded with P5: pagination, collapsed groups, a jump bar. **Built 2026-10-02 (slice 4, STATUS §5no)**: three of the four are now one to two screens tall; the office keeps its length but a sticky row of fourteen chips jumps to any section. |
| M7 | **Every section is two taps away on a phone, and the hub is the only map.** Since §5nc the phone header is the logo and the initial; the bar links (Website CMS, Commerce, Library office, Bookstore, Manage users) live inside the drawer under the initial. The public site has a bottom tab bar on phones (W1, STATUS §5ki); the signed-in shell has none — every admin page measured zero fixed or sticky elements. | note (design) | A bottom bar for the Institute workspace on phones — Home, the four parts (Admissions, Website, Shops & money, System) or the five bar links, and Alerts — built from the same `NavigationMap` the hub uses, hidden from `sm:`. It is the one change that would make the panel read as an app on the owner's phone. **Owner decides the five tabs; C15 slice 7.** **Built 2026-10-03 (slice 7, STATUS §5nu)** — the owner left the tabs to the builder: Home, Website, Shops, System, Alerts; a part's tab opens a sheet of its screens on top of the bar. `WorkspaceMap::tabsFor()` names the groups, `nav.tabs` carries them; `admin-mobile.mjs` checks the bar on all 50 screens. **Closed.** |
| M8 | **Nothing prefetches.** No `<Link prefetch>` anywhere in the shell or the pages, so every section opens on a round trip. Inertia 3 prefetches on hover or mount. | note | `prefetch` on the hub's part cards and the bar links. Pairs with P1, since a prefetched page is then one small chunk. **Built 2026-10-02 (slice 1, STATUS §5nl)** on the hub's cards and screen links, on hover: Inertia's adapter prefetches on `mouseenter` and `mousedown` only, and prefetching on mount would send a request (and an activity row, P3) per card on every hub view, so the phone gains from P1 and not from this. |
| M9 | **Pages are `no-store`** (#633, to stop a signed-out phone showing a cached signed-in page). Safari does not keep a `no-store` page in its back-forward cache, so Back on a phone reloads the admin page it came from. | note | Accepted: correctness over a saved reload. If it matters later, `no-cache, private` keeps the revalidation and lets Safari restore. |

### The plan, in order (BACKLOG C15)

1. **Lazy page chunks and a vendor split; prefetch on the hub** (P1, M8) — half a day, the biggest win, no screen changes.
2. **`Inertia::once()` for the phrase books and the shell's strings** (P2).
3. **Tracking after the response, settings memoised** (P3, P4).
4. **Long pages: paginate islands and translations, fold Feature testing, jump bar on the Bookstore office** (P5, M6).
5. **Self-hosted Amiri and Cairo; Figtree dropped** (P6).
6. **Phone rules in the stylesheet (44 px targets, 16 px controls) and the listing tables stacked, one group per PR, with labels on the 38 fields** (M2–M5).
7. **A bottom bar for the Institute on phones** (M7) — built 2026-10-03 (STATUS §5nu) with the builder's five: Home, Website, Shops, System, Alerts.

Owner, meanwhile: the cache store and OPcache on the host (P7, P8), the
queue worker (C6).

**Walked**: `admin-mobile.mjs` **3/3** on 50 screens after M1's fix.
