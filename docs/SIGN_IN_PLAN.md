# Sign-in and workspaces: the EduPage account model (plan)

**Status:** audit done 2026-09-28 (re-audited the same day at the owner's
request); the owner said "Next" to the plan and its defaults the same day.
**ID1 shipped 2026-09-28 (STATUS §5jz)**: F1, F2 and F3 closed, and a
thirteenth finding (F13, below) fixed with it. **ID2a shipped 2026-09-28
(STATUS §5ka)**: *My learning*, and F14 found. Next: ID2c (a website
parent's Family), then ID2b (the old course portal retired) — in that
order, because ID2b removes the only page a website parent can see their
children's enrolments on.
**Owner's brief (2026-09-28, with five EduPage screenshots):** "Still there
is a problem with the login system. Logged in with a vendor account but I
see educational items also. I need the login style used in EduPage. When a
parent is enrolled in a course he sees, when he changes to student, the
educational items; when he is in parent, he sees all his children. But all
see a home page including promotional items that is main website content
before login."

Read with `docs/APPSHELL_NAV_IA.md` (the shell's IA), `docs/adr/ADR-040`
(workspaces) and STATUS §5ic/§5id (what shipped). Sections: 1 the
reference model, 2 what Akuru does today (the audit, with evidence), 3 the
target, 4 the decisions, 5 the slices, 6 what does not change, 7 how to
audit this document.

## 1. The reference: what EduPage does

From the owner's screenshots (EduPage 3.12.0, the `akuru` school):

- **One app sign-in holds several accounts.** The drawer opens with the
  account list: *School accounts* — one row per identity at the school
  (the same person as a pupil and as a parent, the institute account, a
  teacher account) — then *Personal accounts* (email logins), then *Logout
  / add user*. The active account is highlighted.
- **Picking an account changes the whole app.** Below the list, the drawer
  shows that account's sections and nothing else: *Communication*
  (Messages, Payments, Canteen menu, Photos / Noticeboard, Registration /
  Surveys, Applications, Student pick up, Chat), *Education* (Timetable,
  My courses, HW / exams, Preparations, Substitution, Substitutions
  administration, Library, Timetables online), *Evaluation* (Grades,
  Results, Attendance, Arrivals / departures, Competences), *Other* (Lost
  and found, Student work showcase, Motor abilities), *Settings* (My
  profile, Settings), *Get help* (Write to us, Online help).
- **The home is the account's tile grid.** A header naming the school and
  the role ("Akuru Institute · Teacher · akuru"), today's timetable strip,
  then a grid of tiles — the same functions as the drawer, Messages first
  with its unread state.
- **Nothing of the public website appears** once signed in.

In Akuru's vocabulary an EduPage *account* is a **workspace** (ADR-040):
one login, several jobs, one shown at a time. That layer exists. What does
not exist yet is the discipline EduPage applies to it: a workspace's menu
and home being *only* that workspace's, and every signed-in person holding
one.

## 2. What Akuru does today (audit, 2026-09-28)

Reproduced locally at phone width (iPhone 12 emulation) with the seeded
logins, and by dumping `ResolveWorkspacesAction` and `BuildNavigationAction`
for each kind of account. Each finding names the code that produces it.

| # | Finding | Evidence |
|---|---|---|
| F1 | **A vendor's menu carries school items.** **Fixed in ID1 (STATUS §5jz).** The vendor lands on *My shop* (right), but *More* lists Home, Messages, Notices, Forms, Digital Library, My library, My wallet, Bookstore, My orders, My wishlist, My quotes. | `NavigationMap::groups()` group `mine`: those items carry `roles => $everyone`; `WorkspaceMap::all()` gives every workspace the `mine` group. |
| F2 | **"Home" is the family portal for everyone.** **Fixed in ID1 (STATUS §5jz)**: Home is the workspace's home, and `/portal/home` sends a person who holds neither Family nor Learn to their own. In every workspace *More › Home* opens `/portal/home`, which for a vendor, a member of staff or a role-less account renders **"Student Dashboard"** with empty Attendance, Invoices, Exams, Course progress, Noticeboard, Homework tiles. | `mine` item `home` → `/portal/home`; `ComposePortalHomeAction` line 67 titles the page *Parent* if the person is a parent or has children, else *Student*. |
| F3 | **A person's own learning is mixed into the family's menu.** **Fixed in ID1 (STATUS §5jz)** for the menus; the parent-learner's Learner workspace is ID2a. A parent's *More* has 34 items in two groups; *Learn* and *Schedule* (the parent's own engine courses) sit inside *Family*; a teacher's School menu carries *Learn* too; a writer's Writing menu carries Learn, Schedule, Hifz, E-Learning. | `learn_group` is in the family, learn, school and writing workspaces; its `learn` and `schedule` items are `roles => $everyone`. |
| F4 | **An adult course learner has no workspace and leaves the app.** **Fixed for learners in ID2a (STATUS §5ka)**: they hold *My learning* and land on `/learn` inside the shell; a role-less login with no learning still lands on the Blade page until ID2b. A person who registered and enrolled in a public course holds no role, so `/dashboard` renders the Blade **"My Dashboard"** in the *website* layout: website menu (Courses, News, Articles…), hero, *Browse Courses*, *Open for enrollment* course cards, the marketing footer and the cookie banner. No shell, no switcher. This is the "home page with promotional items". | `DashboardController::publicUserDashboard()` → `dashboard/public-user.blade.php` (`@extends('public.layouts.public')`); `RoleLandingTest` pins it ("falls through to the public-user dashboard when the account has no role"). The adult gets a `students` row (`unified_student_id`) and a `CourseEnrollment`, never a role (`EnrollmentService`: only a child's login gets `student`). |
| F5 | **There are two portals.** Besides the shell there is an older *My Portal* in the website chrome: `/portal/dashboard`, `/portal/enrollments`, `/portal/payments`, `/portal/certificates`, `/portal/profile` (Blade `portal/layout.blade.php`, which extends the public layout), plus `/my-enrollments`. The website's phone bottom bar shows **My Portal** to every signed-in person and links there — so a parent or teacher who taps it from the website lands in the course-learner portal, not their workspace. | `routes/web_public.php` lines 260–269; `public/layouts/public.blade.php` line 205 (`@auth` → `route('portal.dashboard')`). |
| F6 | **Setting a password sends the person to the marketing home.** After *Set password* the redirect is `route('public.home')` with "You can now log in with your mobile number and password" — while they are signed in. | `AccountController::setPassword` line 68. |
| F7 | **Signing in itself is right.** Email / phone / ID card + password, an OTP path, both redirecting to `/dashboard`, which sends a person with a role to their workspace home (Institute, School office or a teacher's day, Family, Learn, My shop, Writing, Catalog, Bookstore office). | `AuthenticatedSessionController::store`, `OtpLoginController` line 131, `DashboardController::index`. |
| F8 | **The switcher is a pill, not an account list.** It shows only when a person holds more than one workspace, in the header; the phone *More* panel lists account and language but not the workspaces. Family and Learn share one home (`/portal/home`), told apart only by the switcher. | `AppShell.jsx` lines 69–104 (`workspaces.length > 1`); `WorkspaceMap` `family` and `learn` both `home => 'portal.home'`. |
| F9 | **The mobile app opens the website.** The Capacitor wrapper loads `https://akuru.edu.mv` — the marketing home — rather than the sign-in or the person's workspace. | `capacitor.config.ts` `server.url`. |
| F10 | **`student` is granted only one way.** A child's login made by the family form gets the role; a pupil's login made anywhere else needs the role screen; an adult self-enrolment gets a student record and no role (see F4). | `EnrollmentService::createChildUserAccount`; `SetUserRolesAction`. |
| F11 | **The parity document's E7 describes a different switcher.** E7 specifies `linked_accounts` (two *separate* logins linked, the session swapped between them). What shipped is one login with several workspaces (§5ic, §5id), which is what EduPage's *School accounts* are. Linked separate logins are EduPage's *Logout / add user* — a different, later thing. | `docs/EDUPAGE_FEATURES_PLAN.md` §E7. |
| F12 | **The Blade shell has the same menu.** **Closed with ID1**: the Blade shell renders the same regrouped map. The 24 signed-in Blade screens (`layouts/app.blade.php`: e-learning, Qur'an progress, substitutions, analytics, the auth pages) read the same map since §5id, so F1–F3 show there too and one fix covers both shells. | `layouts/navigation.blade.php`. |
| F13 | **The Personal items opened as a modal.** Found building ID1: the Digital Library, My library, My wallet, Bookstore, My orders, My wishlist and My quotes are Blade pages in the website layout, but the map did not mark them `hard`, so the Inertia shell opened each as a visit — a non-Inertia response, shown in a modal over the page. **Fixed in ID1**: all seven marked; `WorkspaceMenusAreTheirOwnTest` asks every map item as Inertia asks and fails on any Blade answer without `hard` (it fails on a one-line mutation of the Library item). | `NavigationMap::groups()` `mine` (now `me`). |
| F14 | **A parent who registers their children on the website never becomes a parent.** Found building ID2a. The public form's parent flow creates or matches the child and links the registering login as a guardian (`RegisterCourseStudentAction::forChild`), and a child given a password gets `student` — but nothing grants the registering adult `parent`, so they hold no Family workspace and see their children only on the Blade *My enrolments* list. **Planned: ID2c.** | `EnrollmentService` parent flow; `grep assignRole` finds no `parent` grant anywhere in `app/`. |

What is **not** broken, so the plan does not touch it: the gates (a vendor
who opens `/portal/home` is shown an empty page, never another family's
data — `HifzCrossRoleAccessTest` and the portal policies still hold);
the Institute/School split and the educational admin's permission set
(ADR-040); the primary bars per workspace; the homes the Institute and
the School already have.

## 3. The target

One login; one workspace shown at a time; a workspace is an EduPage
account.

1. **Every signed-in person holds at least one real workspace.** The
   existing eight, plus **Learner** — held by anyone whose login owns a
   student record with a course enrolment, or who self-enrolled on the
   engine. A parent who enrols in a course holds *Family* and *Learner*
   and switches between them: Family shows the children, Learner shows
   their own learning — the owner's case exactly. A person with no job
   and no learning holds *My account*, which is a page inside the shell,
   never the website.
2. **A workspace's menu is only its own.** Each workspace names its
   groups in EduPage's vocabulary — *Communication*, *Education*,
   *Evaluation*, *Other* — from the map, and only the school-facing
   workspaces (School, Family, Learn, Learner) carry school communication
   (Messages, Notices, Forms, Requests). Every workspace ends with the
   same short **Me** group (profile, wallet, Digital Library shelf, My
   library, Bookstore, My orders, My wishlist, My quotes) and
   **Settings / Help** (language, log out, help). *Home* is the active
   workspace's home, never the family portal.
3. **The account list is in the drawer.** The phone *More* panel opens
   with *Your accounts*: one row per workspace with the person's name and
   the role label (Parent, Pupil, Learner, Teacher, Dean, Vendor…), the
   active one marked, a tap switches; then *Log out*. The desktop keeps
   the header pill.
4. **Every door leads into the shell.** `/dashboard` after sign-in, the
   website's *My Portal*, *Set password*, the app's start address — all
   land on the active workspace's home. The website stays the website for
   visitors and for a signed-in person who goes there on purpose; its
   header offers the way back.
5. **Homes are tile grids.** Under each workspace home's today strip, the
   workspace's functions as tiles (Messages first, with its unread
   count), the EduPage look; the Institute and School office homes keep
   their card parts.
6. **The old course-learner portal is retired.** The Learner home and the
   Me group carry what its eight Blade views did (enrolments, payments,
   certificates, profile, browse courses), and the Blade goes.

## 4. Decisions

Defaults are what the plan builds unless the owner says otherwise.

| # | Decision | Default and why |
|---|---|---|
| D1 | Do the **Me** items (Digital Library, Bookstore, wallet, orders, wishlist, quotes) show in every workspace, or only under a personal account as EduPage's *Personal accounts* do? | **Every workspace, as the last group.** Buying a book or reading the Library is something any signed-in person does; a separate account for it would be a switch nobody expects. |
| D2 | Is a **Learner** a derived identity or a role? | **Derived**: a student record owned by the login with any course enrolment, or an engine self-enrolment. No new role to grant, nothing to backfill, nothing for the role screen to get wrong. |
| D3 | The old Blade course portal (`/portal/dashboard` and four pages, `/my-enrollments`, the public-user dashboard): retire into Learner, or keep? | **Retire**, in the slice that ships Learner. Two portals is the confusion the owner is describing. |
| D4 | Tile-grid homes: in this plan or later? | **In this plan, last** (ID5). It is the EduPage look the owner asked for, and it is cosmetic until ID1–ID4 make the menus right. |
| D5 | The website's *My Portal* button: to the shell, or to the old portal? | **To the shell** (`/dashboard`). |
| D6 | The mobile app's start address: the website, or the person's workspace? | **The workspace** (`/dashboard`, which is the sign-in page when signed out). The app is the portal; the website is one tap away in the Me group. |
| D7 | Linked *separate* logins (EduPage's *Logout / add user*, E7's `linked_accounts`)? | **Later, as its own track.** Workspaces already cover a parent-teacher, a pupil-writer, a parent-learner on one login; separate logins linked need both credentials and a security design. |

## 5. The slices

One slice per PR (CLAUDE.md rule 1); each ships its tests, a walk, its
STATUS section, and keeps `tests/Architecture` green. Every string keyed
EN/DV/AR. Order matters: ID1 first because everything else renders through
the map.

### ID1 — Workspace-scoped menus (one PR) — **shipped 2026-09-28, STATUS §5jz**

As built: the School keeps its office groups and gains *Teaching*
(`learn_group` without the person's own learning), *Communication* and
*My work*; Family and Learn hold *Communication, Education, Evaluation,
Other*; every workspace ends with *Personal* (`me`: profile, the Digital
Library, My library, the wallet, the Bookstore, orders, wishlist,
quotes). A group several workspaces share carries items tagged with
their workspaces (`workspaces`), so a teacher-parent's School shows none
of the family's fees or pick-up. The Institute has no school
communication. *Settings* stayed in the shell (language, log out) rather
than becoming a group; the family home's sections are fixed, so nothing
there changed. `/portal/home` sends a person who holds neither Family nor
Learn to their own home (a role-less account keeps it until ID2a). The
original bullets follow.


- `NavigationMap::groups()` regrouped: `communication`, `education`,
  `evaluation`, `other` per workspace (the existing items, no new screens),
  `me` and `settings` shared; `learn_group` and `mine` gone.
  `WorkspaceMap::all()` lists each workspace's groups; the vendor's are
  `me` and `settings` only (its own Messages live in My shop); Writing's
  and Catalog's likewise plus their bar.
- *Home* is the workspace's home (`WorkspaceMap::homeFor`), rendered by
  the shell, not a map item.
- Both shells (Inertia `AppShell`, Blade `layouts/navigation`) render the
  new groups; the family home's *sections* follow.
- **Tests:** `WorkspacesTest` and `NavigationIsGroupedByRoleTest`
  rewritten for the new groups; new `WorkspaceMenusAreTheirOwnTest`: a
  vendor's menu holds no school item and its Home is the shop; a parent's
  Family menu holds no *Learn*; a teacher's School menu holds no *Learn*;
  every item still names a real route; nothing a person could be refused.
- **Walk:** `workspaces.mjs` and `nav.mjs` updated; new `identity.mjs`
  signs in as the vendor, the parent, the teacher and the pupil and reads
  each *More* panel.

### ID2a — The Learner workspace and its home (one PR) — **shipped 2026-09-28, STATUS §5ka**

As built: the workspace is *My learning* (`learner`), held — derived, no
role — by a login that owns a student record with an enrolment not
refused, cancelled or withdrawn, unless it holds `student` (a school
pupil's courses are already in *Learn*); last in the map's order, so a
parent lands on Family and a teacher on the School. Its home is the
existing `/learn` ("My learning", SPEC §24), which gains the one §24 item
it had deferred, "Access/payment status": the enrolments still waiting,
on their payment or on the office. Its bar is My learning, Schedule,
Browse courses; its *Education* group adds My enrolments (the Blade list
of every enrolment the login made, until ID2b); the household's screens
name their workspaces, so a parent-learner's My learning shows none of
their children's. The Family home shows a parent's children and not their
own record once they have children. The query runs once per request per
person. *My account* inside the shell moved to ID2b, where the Blade page
it replaces is retired. The original bullets follow.


- `WorkspaceMap` gains `learner` (home `learn.home`, bar Learn, Schedule,
  My courses, Payments, Certificates); `ResolveWorkspacesAction` holds it
  for a login that owns a student record with a course enrolment or an
  engine self-enrolment (one query, cached on the session per request).
  *My account* keeps the fallback, rendered inside the shell
  (`Portal/AccountHome`: profile, wallet, library, bookstore).
- `Learn/Home` (Inertia): enrolments with status and payment, continue
  learning, the next sessions, certificates, the door to browse courses.
- **Tests:** `RoleLandingTest` "falls through" rewritten (a role-less
  learner lands on Learner; a role-less non-learner on My account, inside
  the shell); `DualIdentityLandingTest` gains the parent-learner (Family
  first, Learner offered); `LearnerWorkspaceTest`.
- **Walk:** `identity.mjs` gains the learner and the parent-learner.

### ID2c — A website parent's Family (one PR) — added 2026-09-28 (F14)

- The public registration's parent flow links the registering login to the
  child as a guardian but never grants `parent`, so a parent who registers
  their children on the website holds no Family workspace; only the
  office's role screen gives it. Grant `parent` where the link is made
  (as a child's login is granted `student`), and backfill it for logins
  that already hold a guardian link. The verification gate (STATUS §5gk)
  is unchanged: an unverified child shows as awaiting the office.
- **Tests:** registering a child grants `parent`; the backfill; the family
  home shows the child awaiting verification, then verified.

### ID2b — Retire the old course portal (one PR)

- Delete `dashboard/public-user.blade.php`, `portal/layout.blade.php` and
  its five pages, `my-enrollments/index.blade.php`; their routes redirect
  to the Learner home or its tabs; `publicUserDashboard()` goes; the Blade
  baseline drops by eight.
- **Tests:** the redirects; `NoNewBladeScreensTest` count.

### ID3 — Every door leads into the shell (one PR)

- `AccountController::setPassword` → `/dashboard`; the website header's
  *My Portal* → `/dashboard`; `capacitor.config.ts` start path
  `/dashboard`; the login page's copy says where each kind of person lands.
- **Tests:** the three redirects; the header's link for a signed-in person.
- **Walk:** `identity.mjs` step: from the website, *My Portal* lands on
  the workspace home.

### ID4 — The account list in the drawer (one PR)

- `AppShell` phone *More* panel opens with *Your accounts* (name, role
  label, active mark, tap to switch), then language, then the groups; the
  desktop pill stays. The Blade phone menu matches.
- **Tests:** `WorkspacesTest` (the list on every Inertia page, in the
  request language); `mobile.mjs` step.

### ID5 — Tile homes (one or two PRs)

- Family, Learn, Learner, the teacher's day and My shop homes gain the
  workspace's tile grid under the today strip: each *More* item as a
  tile, Messages first with its unread count. The Institute and School
  office homes keep their parts.
- **Tests:** the tiles are the menu (no tile the menu lacks); a phone
  screenshot per home in EN/DV/AR in the walk.

## 6. What does not change

Roles, permissions and every route gate (ADR-040, the role matrix by
migration); the Institute/School split; the primary bars; the Institute
and School office homes; the family and pupil dashboards' data; the
website for visitors. The map remains the one place a link is declared,
and `BuildNavigationAction` keeps reading visibility off the route.

## 7. How to audit this document

The audit in §2 is reproducible: seed (`RoleSeeder`, `SchoolSeeder`,
`ClassSeeder`, `UserSeeder`, `SmokeMarkerSeeder`), sign in at phone width
as `vendor@`, `parent@`, `student@`, `teacher@` and a role-less account,
read the landing address, the *More* panel and `/portal/home`; and dump
`app(ResolveWorkspacesAction::class)->execute($user)` and
`app(BuildNavigationAction::class)->execute($user, 'en', $workspace)` for
each. A finding is closed when the slice that names it has shipped and the
walk that covers it is in `scripts/smoke/all.mjs`. Update the table's
status inline, as `docs/EDUPAGE_FEATURES_PLAN.md`'s correction section
requires: verify against the code before recording anything as missing.
