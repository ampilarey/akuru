# AppShell navigation IA

**Status:** **Accepted as written by the owner on 2026-09-24, and implemented the same day** (STATUS §5ga). What follows is the proposal as accepted; the *Implemented* section at the end says where it lives.  
**Source:** `resources/js/Layouts/AppShell.jsx` (every Inertia screen). Round 3 ranked this #2 in `docs/KNOWN_ISSUES.md`. Proposal PR: **#98**.  
**Out of scope:** Hifz Blade chrome, public marketing nav, this file’s logout/locale row (those stay).

## Implemented (2026-09-24)

- **`App\Support\Navigation\NavigationMap`** — the map: a primary bar per
  role (admins · teacher · parent · student · course_creator · writer ·
  reviewer) and eleven groups holding every Inertia screen once.
- **`App\Support\Navigation\BuildNavigationAction`** — builds `nav` for the
  signed-in person. Visibility is read off each **route's own guard**
  (`role:`, `permission:`, `can:`, `role_or_permission:` in its gathered
  middleware); a page whose gate lives in the controller carries a `can`
  hint that mirrors it, and `auth`-only pages carry a `roles` hint saying
  who they are *for*. A link whose route does not exist is hidden, so the
  menu cannot carry a dead one. Labels come back translated from
  `lang/nav.php` (EN, DV, AR).
- **`HandleInertiaRequests`** shares `nav` and the shell's four words.
- **`AppShell.jsx`** renders the bar, marks the current screen
  (`aria-current`), and a **More** button opening every group in a panel
  that closes on choice, on Escape, and on a click elsewhere. 109 literal
  links became three (Alerts, the account link, and nothing else).
- **Tests:** `tests/Feature/Nav/NavigationIsGroupedByRoleTest.php` — every
  href is a real GET route; each role's bar; a teacher and a parent are
  refused by **none** of the links they are shown (the walk that failed
  before); the Docs guard now holds the other direction.
- **Walk:** `scripts/smoke/nav.mjs` (read-only) — a teacher finds Today,
  the office finds Years and Exams and Payroll under More, a parent is shown
  no staff screen and every link they are shown lets them in.

The primary bars below are as proposed, with two edits made while
implementing and recorded here: a teacher's *Learn* became *Teach* (the
teacher's own timetable) plus *Check in*, and a student got a bar of their
own (Learn · Schedule · Homework · My attendance · Results). Gradebook shows
to a teacher only where the exams routes and the gradebook's own gate admit
them — today they do not, and the menu says so by omission rather than
by a 403.

## Problem

`AppShell` renders **every** Inertia destination as a wrapping `<Link>` in one `<nav>`. Count on this commit: **74** `<Link href=` nodes. Today, Years, and Exams sit in the same wrap as Catalog taxonomy, HR payroll, and portal “My …” items. Duplicate labels (**Report cards**, **Awards**) appear twice (staff vs portal). Teachers cannot find **Today**; admins hunt for **Years** and **Exams** in the overflow.

This is an information-architecture change, not a colour tweak. Implementing without a confirmed map would just reshuffle the pile.

## Principles (if confirmed)

The map is grouped **by role** and by frequency. Primary destinations for the signed-in person’s first-week loop stay in the bar; everything else is a labelled group.

1. **Role first.** Show the destinations that role uses in the first week of term. Everyone else goes under a labelled group, not a flat list.
2. **Frequency second.** Daily loop (Today / Learn / Fees) beats setup (Weights, Leave types, Catalog taxonomy).
3. **One label, one place.** Staff “Report cards” lives under Exams; parent “Report cards” lives under Portal. Do not show both to both audiences.
4. **Hide by permission.** Links the user cannot open (403) should not appear. Today they all appear.
5. **No new Blade shell.** Inertia only. Hifz stays frozen.

## Proposed primary bar (always visible)

| Role | Order (left → right) |
|---|---|
| **Teacher** | Today · Registers · Gradebook · Absence notes (review) · Learn |
| **Admin / headmaster / supervisor** | Today · Years · Students · Exams · Gradebook · Invoices |
| **Parent** | Children · Fees · Results · Absence notes · Holidays |
| **Staff (HR self-service)** | Check in · My leave · Payslips · My performance |

Locale switch + name + **Log out** stay at the far end (already present).

## Proposed groups (overflow / “More” or left rail — pick one in implementation)

Do **not** flatten these back into the primary bar.

| Group | Items | Typical role |
|---|---|---|
| **School year** | Years, Rooms, Periods, Timetable, Bookings, Calendar, Holidays | admin |
| **People** | Students, Staff, Custom fields | admin |
| **Day loop** | Today, Registers, Plans, Attendance, Behavior, Requests, Review notes | teacher + admin |
| **Exams** | Exams, Weights, Gradebook, Scales, Exam types, Competencies, Standards, Report templates, Report cards, Awards | admin; Gradebook also teacher |
| **Catalog** | Courses, Offerings, Questions, Reviews, Arabic, Arabic report, Qur’an, Subjects, Audiences, Levels | admin / curriculum |
| **Learn** | Learn, Schedule, Teach, Children (portal learning) | student / teacher / parent |
| **Finance** | Fee items, Fee structures, Invoices, Arrears, Payment plans, Adjustments, Manual receipt, Collections, Reconciliation, Fees (portal) | admin / parent |
| **HR** | Staff attendance, Staff reports, Leave types, Leave balances, Contracts, Compliance, Jobs, Applications, Onboarding, Appraisals, Observations, CPD, Payroll | admin; self-service as above |
| **Portal (mine)** | My attendance, My behavior, Results, Report cards, Awards, Fees, My leave, Payslips, My performance | the signed-in person |

## Frequency (what must not drown)

| Cadence | Destinations that must be one click for the role that owns them |
|---|---|
| **Several times a day** | Today (teacher), Learn (student), Check in (staff) |
| **Daily / weekly** | Registers, Gradebook, Absence notes, Fees (parent) |
| **Term setup** | Years, Exams, Weights, Students, Invoices |
| **Rare / config** | Scales, Exam types, Custom fields, Leave types, Catalog taxonomy |

If a rare item is in the primary bar, the daily item has already lost.

## Decision required (owner) — decided 2026-09-24: **1. Accept as written**

Reply with one of:

1. **Accept as written** — next slice implements grouped nav in `AppShell.jsx` (role filter + More groups), tests for visible primary links per role, and a Chrome walk: teacher finds Today; admin finds Years and Exams without scanning the wrap.
2. **Accept with edits** — paste a revised primary-bar table; still no implementation in this PR.
3. **Reject** — keep the flat wrap; close this proposal.

Until that reply, **do not edit** `AppShell.jsx` for IA.

## DoD walk (this PR)

2026-08-26 local Chrome as `admin@akuru.edu.mv` on `/en/people/students`: the shell is still one wrapping strip (74 `<Link href=`). Clicked **Today** → `/en/academics/registers/today`, **Years** → `/en/academics/years`, **Exams** → `/en/exams/schedule`. Duplicate **Report cards** / **Awards** still appear twice. This walk documents the problem; it does not implement the map.
