# ADR-040: Workspaces — one shell, one job at a time

**Status:** accepted 2026-09-27 (the owner: "Go", after the recommendation in STATUS §5id).

## Context

A signed-in person saw the union of everything their roles could open. An
administrator got the school's bar (Today, Years, Students, Exams,
Gradebook, Invoices), a *More* menu of eleven groups and the admin panel,
all at once; the `admin` role holds every permission, so an admin also saw
registers, exams, HR and finance whether or not they ran the school. Two
shells (Blade and Inertia) each decided by hand which links to show. A
person with several roles landed on one page by precedence and reached the
others through the menu, if at all. The owner, after three rounds of
grouping and linking: "still login and admin setting is really confusing …
I think super admin or admin role is to control everything related to
website, users, business etc, everything related education should be
managed by principal/dean or supervisor, teacher … admin or super admin
can be a principal/dean or supervisor, teacher but their setting should be
seen when he changes to his specific role."

The roles the owner named map onto the existing ones: system admin =
`super_admin`, dean = `headmaster`, educational admin = `admin`,
supervisor, teacher, parent, student. The owner decided: Finance and HR are
school administration (the educational admin's) and the dean may see fees;
an admin does not open the School without a school role; payroll stays
with the educational admin; the public website's instructors and course
pages are website content (the system admin's); the course creator role is
kept for an outside author.

## Decision

1. **A workspace is one job.** `App\Support\Navigation\WorkspaceMap` names
   them, in the order a person who holds several is offered them and lands:
   Institute (`super_admin`: website, users, system, shops, the library
   office), School (`admin`, `headmaster`, `supervisor`, `teacher`:
   admissions, academics, the office), Bookstore office, Family, Learn, My
   shop, Writing, Catalog; a person with no role holds their account alone.
   Each has a home route, a primary bar drawn from the person's roles inside
   it, and the *More* groups that belong to it. The admin panel's four parts
   are four groups: Admissions is the School's, the other three the
   Institute's.
2. **The shell shows one workspace at a time.** `BuildNavigationAction`
   builds the bar and the groups for the active workspace only; both shells
   render that one map (the Blade nav no longer lists links by hand). The
   active workspace is the one the person last switched to (a POST, from a
   switcher in the header, the user menu and the phone menu) or the last
   workspace home they opened (`RememberWorkspace`), else the first they
   hold. `/dashboard` sends a person to the active workspace's home.
3. **A workspace never grants access.** Every route keeps its own gate and
   the map hides what the person could only be refused. The workspace
   decides what is *shown*: which home, which bar, which menu. Holding a
   role in another workspace changes nothing in this one.
4. **Homes are one page shape.** `/admin` (the Institute) and `/school` (the
   School) are the same page: today's numbers for that job, then the
   workspace's groups as parts of cards, each card a section with its
   screens. A teacher's, a family's, a vendor's and a writer's homes stay
   their own pages. A person opening a home they do not hold is sent to
   their own.
5. **Roles keep their keys.** The labels people read are the owner's names
   for the jobs — System admin, Educational admin, Dean — in
   `lang/roles.php` (EN/DV/AR) through `App\Support\Authorization\RoleLabels`
   (slice 3, 2026-09-27, STATUS §5if), wherever a role is shown: the users
   screen and its filter, the Blade user menu, the linked-accounts list, the
   staff form. The educational admin's
   permission set is a decision, not `Permission::all()` (slice 2,
   2026-09-27, STATUS §5ie): `App\Support\Authorization\RoleGrants` names
   it — the school's office (admissions and their money, the people, fees,
   HR and payroll, the noticeboard, messages, forms, requests, the calendar,
   rooms, meetings and events, the registers' oversight, reports), the
   academics read only, nothing of the Institute — and one migration ships
   it (`syncPermissions`, so the role also *stops* holding things) while
   `RoleSeeder` reads the same list. The Institute's routes (the website,
   instructors, prayer times, commerce, the library office, pronunciation,
   operations, translations) admit `super_admin` alone; the Bookstore office
   admits `super_admin` and `bookshop_manager`; admissions admit
   `super_admin`, `admin` and `headmaster` (a supervisor no longer grants a
   place on a paid course).

## Consequences

- Signing in as the system admin shows the Institute and nothing of the
  school; as the educational admin, the dean or the supervisor, the School
  office; as a teacher, their day; with several roles, a switcher. What a
  person sees is what their job is, and switching jobs changes everything
  below the header.
- The Blade shell and the Inertia shell cannot drift: one map, one
  visibility rule, one switcher. The reachability tests read the rendered
  Blade menus as the roles that run each workspace.
- Deep links across workspaces (an email link to a School screen while the
  Institute is active) open the screen under the other workspace's menus;
  the switcher is one tap away. Opening a workspace's home realigns it.
- Two workspaces share a home (Family and Learn both open the family
  portal); the switcher tells them apart by posting the choice, and the
  remember-on-open rule stays silent when a home is ambiguous.
- A super admin who needs the School grants themselves the dean's role
  (the role screen, slice 4) — by design, not a gap.
- Since slice 2 the gates agree with the menus: an educational admin who
  types an Institute address is refused, and a headmaster or supervisor can
  no longer edit the public website by URL. An educational admin sees the
  academics and cannot mark, grade, run exams or edit the timetable — the
  screens still open, their writes refuse — which is the decision: the dean
  and the supervisor run the academics.
- A deployment gets the set by `migrate`, never by `db:seed`; changing the
  set later is a new migration, and a re-seed syncs to the same list.
- The seeded logins gain a system admin (`superadmin@`, staging and local
  only; production still makes its own by tinker) because the walks that
  run the Institute can no longer do so as `admin@`.
