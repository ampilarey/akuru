# EduPage → Akuru Parity Checklist

Context: Akuru Institute used EduPage (`akuru.edupage.org`) **before this platform
was built** — it is the system staff, parents, and students were accustomed to.
This document is the feature benchmark the platform should meet so nobody misses
the old system, and the design reference for the future app shell.
Companion to `docs/EDUPAGE_FEATURES_PLAN.md` (the buildable slices) and
`docs/RECITATION_MODULE_PLAN.md`.

**Method (2026-08-29, revision 2).** Revision 1 was built from five operator
screenshots of the installed app — it only saw the menu items this institute had
switched on. Revision 2 adds a crawl of EduPage's own help site
(`help.edupage.org`, English): **41 top-level modules, 3,720 documented
sub-pages**, plus the full text of ~16 mechanism pages. No account access was
used: the vendor's documentation describes every feature, including ones this
institute never enabled, which screenshots cannot show. (A live login was
considered — the operator confirms the instance holds no real data — but
headless Chromium cannot reach external hosts from a remote session; see
STATUS §5aa. Screenshots remain the way to capture layout and feel.)
"Verified" below = checked in this codebase; EduPage behaviour is cited from
its own docs.

Legend: ✅ parity or better · 🟡 partial · ❌ not built · ➖ not wanted / decide.

---

## 1. Corrections to revision 1

Two entries were wrong, one materially:

- **Library was marked "✅ better". It is not comparable.** EduPage's Library
  (31 doc pages) is a **physical lending system**: book titles and copies, QR
  code / barcode labels printed per item, lending and returning, a student's own
  QR card, availability lookup, plus a parallel **Textbook storage** module (21
  pages) for issuing textbooks with bulk borrow/return. Akuru's L-track Library
  is a **digital reader + bookstore + writer payouts** — a different product
  serving a different need. Akuru has no physical lending; EduPage has no
  reader or bookstore. Both entries below are now split.
- **"Applications (generic forms + approval)" understated a whole module.** The
  Requests/Applications module is an approval workflow engine with automatic
  follow-on actions, not a form builder. See §2 Communication.

## 2. Feature map (from EduPage's own module documentation)

Sub-page counts are given as a rough depth signal — they indicate how much
behaviour sits behind a menu item.

### Communication (44 + 21 + 11 + 10 + 6 doc pages)

| EduPage module | Akuru | Evidence / gap |
|---|---|---|
| **Messages** — send to users/classes/groups; **saved custom recipient groups** alongside course-derived ones; cross-class recipient picking; "Important" flag; **built-in voting/poll on a message** with results; **reply controls** (disable public replies → author-only, or disable replies entirely; reply-all off by default school-wide); mark handled/hide; delete a sent message; **block a user** for abuse | 🟡 | `Notifications\Models\Message` has sender/recipient/subject/body/attachments/priority/read state. Single-recipient rows, no threads, no groups, no poll, no reply policy, no block. |
| **Chat** (separate from Messages) — whole-class chat, custom user selection, delete own messages | ❌ | No real-time chat. Deliberately deferred. |
| **Noticeboard** — a teacher posts an announcement to colleagues **or to their own class's students/parents**, with file attachment; recipients notified on web + app | ❌ | Public news/gallery exist; no in-portal feed for enrolled families. |
| **Notifications** — per-user scope control, filter to only HW/tests, mark a notification "Done", push on/off | 🟡 | SMS/mail notices exist with consents; no user-facing notification centre or scope settings. |
| **Student Work Showcase** — photograph paper work, **AI recognises the pupil's handwritten name** and routes it to that parent; categorising; send from the lesson menu; misfile recovery | ❌ | Not built. Related to Akuru's own AI plans. |
| **Sign-up / Registration actions** — trips, seminars, electives, clubs; **"require parent's confirmation"** (green tick / red triangle in results, and only a real parent account can confirm); notify non-registered; copy templates; print results; **hand off to Payments as a per-target payment plan** | 🟡 | Admissions/event/course registration ✅; no generic sign-up builder, no parent-confirmation step, no registration→invoice handoff. |
| **Requests / Applications** — parents and staff file typed applications (leave of absence, PE exemption, board examination, personal curriculum, staff leave, sickness notification); **configurable approver per type** (class teacher, headmaster, authority); all parties notified; **approval auto-generates the follow-on action** — approving staff leave offers to insert that teacher into substitutions as absent | ❌ | Admissions applications ✅, but no general request/approval workflow and no chaining into substitutions. |
| **Electronic Applications** (admissions forms) — configurable layout, school-defined fields, embeddable on a non-EduPage site, parent can print/amend a submission | ✅ | Akuru admissions covers this. |
| **Lost and found** — staff log an item with photo + pickup location; families browse; states incl. "returned to owner" | ❌ | Trivial slice if wanted. |
| **Payments** (69) | ✅ better | BML gateway, invoices, wallet, gift cards, refunds (P4.3). |
| **Canteen** (51) | ➖ | Only if the Institute runs a canteen. |

### Education (523 + 333 + 103 + 41 + 69 doc pages)

| EduPage module | Akuru | Evidence / gap |
|---|---|---|
| **Timetable** (523 — the aSc lineage) | 🟡 | `Academics\Models\Timetable` + `TimetableConflictException`; portal views exist. aSc's builder is far ahead; accept simpler editing, keep an import/export path. |
| **Class register** (103) — per-lesson record, **homework assigned from the lesson itself** | 🟡 | `LessonLog` with a `homework` field; registers UI exists. |
| **Homework & exams** (41) — assign from the register, **auto-dated to the class's next lesson** (date picker highlights days that class actually meets); simple text HW **or** HW with follow-up questions (ABCD etc.); file attachments both directions; per-question points, extra points, return evaluated work, **annotate a photo of the pupil's work**; **read/done tracking** (answers for question-HW, pupil self-marks done for text HW); assign to selected pupils; **digest notification mode — one daily summary at a chosen time that also lists what is due tomorrow**; exam planning screen, announce exam, study topics, **enter grades now / publish later** | 🟡 | Exams ✅. Homework is a free-text field on `LessonLog` with no due date, no pupil-facing list, no done state, no attachments. |
| **Plans, preparations, standards** (333) — reusable materials library feeding homework and tests | 🟡 | Lesson logs + teacher plan views (D3); no materials library. |
| **Substitutions / cover** (69) | ✅ | `TeacherAbsence`, `SubstitutionRequest`, `SubstitutionAssignment`. |
| **Interest groups / school clubs** (15) — clubs as their own courses with plans, registration, printable rosters, external members | 🟡 | Course engine could model these; not set up as a distinct concept. |
| **Library — physical lending** (31) — titles + copies, QR/barcode labels, lend/return, student QR card, availability | ❌ | Akuru's Library is digital (reader/bookstore/payouts). Different product — see §1. |
| **Textbook storage** (21) — issue textbooks, bulk borrow/return, filter by subject/grade | ❌ | Not built. |
| **EduPage AI** (15) — generate materials and tests, create homework from uploaded material, **grade paper tests from a photo**, correct essays, presentations | ❌ | Akuru's AI plan is recitation (its own module) — a different and more defensible bet. Worth knowing the incumbent grades paper tests by camera. |

### Evaluation (169 + 27 + 19 + 31 doc pages)

| EduPage module | Akuru | Evidence / gap |
|---|---|---|
| **Grades** (169) | ✅ | Gradebook + weights + report cards (C2). |
| **Attendance of students** (27) — **electronic absence notes with an anti-falsification story**; custom absence types; **tardy→absence conversion rules** (e.g. 3 tardies = 1 lesson); rounding policy; "who is absent today"; tardy/early-departure summaries | 🟡 | Registers with parent notification ✅ and `/portal/absence-notes` exists; no custom types, tardy conversion or rounding policy. |
| **Arrivals / departures** — access-control/gate integration | ❌ | Only staff attendance (HR). Hardware question. |
| **Attendance of teachers** (19) — workload types, one-off adjustments, **absences feed straight into substitutions**, rights model | 🟡 | Staff attendance ✅ and substitutions ✅; the automatic link between them is the gap. |
| **Certificates / report printing** (31) — country templates, verbal evaluation, grade categories, subject areas | 🟡 | Report cards ✅; printing templates are thinner. |
| **Competences** | ❌ | Not built (Quran milestones are a subject-specific cousin). |

### Structural (108 + 102 + 94 + 70 + 68 + 42 + 19 + 8 doc pages)

| EduPage module | Akuru | Evidence / gap |
|---|---|---|
| **Calendar and events** (19) — internal school calendar, custom event types, whole-day events, school holidays, **room booking / classroom change**, teachers' meetings, message all participants of an event, day/week views | 🟡 | Public events ✅; no internal staff calendar, holidays table or room booking. |
| **Parent-teacher meetings** (9) — consultation-hour slots, assign a slot to a parent, configurable break between consultations, teachers booking each other | 🟡 | `/portal/meetings` exists; slot booking is thinner. |
| **User accounts** (42) + **User rights** (8) — granular per-teacher grants (attendance, grades, per-course admin, account administration) | ✅ | Spatie roles + permissions, `role:`/`can:` route guards. |
| **Multi-account switcher** — parent + own-student + staff accounts in one app; note EduPage **enforces the separation** (a parent signed in with a pupil's credentials cannot give parent confirmation) | 🟡 | Roles stack on one login; no switching between separate accounts. See E7 and the teacher-parent priority bug below. |
| **Student sensitive information** (5) — health/general notes visible to authorised staff | ❌ | Not built; would need a privacy decision. |
| **Reports** (70), **Pedagogical documentation** (52), **New school year** (18), **Final exam printing** (25) | 🟡 | Assorted admin/statutory outputs, mostly Slovak-curriculum shaped. Low relevance. |
| **Mobile application** (94) | 🟡 | Capacitor scaffold exists (Phase 5); no shipped app. |
| **Webpage** (34) | ✅ better | Full public site with CMS, news, courses, library. |
| **Kindergarten** (174) | ➖ | Not applicable. |

## 3. The home-screen pattern to copy (design target for the app shell)

EduPage's home screen earns daily opens with three moves Akuru's E1 reproduces:

1. **"Timetable tomorrow" strip** — the next school day's periods visible before
   any tap. Note the homework digest does the same trick from the other side:
   its daily summary explicitly lists *what is due tomorrow*. Tomorrow is the
   organising idea of the whole product.
2. **Tile grid where every tile shows live status** — "Messages · No new
   messages", badge counts, beta tags. Tiles summarise; they are not just links.
3. **Identity always visible** — school name + "Teacher · akuru" under the
   title, with the account switcher one tap away.

The red "+" FAB (new message, new homework…) is worth copying for teachers.

## 4. Gaps ordered by daily impact (the migration path)

| # | Gap | Why it matters daily | Effort |
|---|---|---|---|
| 1 | **Portal home as status-tile dashboard** (§3) | It is the screen; everything hangs off it | 1–2 wks |
| 2 | **Messaging** — threads, groups, reply policy, poll, unread badges | The #1 parent habit | 2–3 wks |
| 3 | **Student-facing homework** — list, due dates, done state, attachments | Second most-opened screen | 1–2 wks |
| 4 | **Noticeboard feed** | Replaces the photos/notice habit | 1 wk |
| 5 | **Requests / approvals** (incl. staff leave → substitution chaining) | Office workflow; the chaining is the clever part | 2 wks |
| 6 | **Sign-up actions** with parent confirmation + payment-plan handoff | Trips, electives, clubs | 2 wks |
| 7 | **Student pick-up** (a real protocol — see plan E6) | Small but loved; islands context may differ | 1 wk |
| 8 | **Account switcher** | Parents who are also staff — and Akuru has a live bug here | 1 wk |
| 9 | Attendance policy depth (tardy conversion, custom types, note integrity) | Quiet but reduces disputes | 1 wk |
| 10 | Internal calendar + room booking; consultation slots | Staff coordination | 1–2 wks |
| 11 | Physical library / textbook lending; lost & found; canteen; competences; work showcase | Only if the Institute runs these | defer |

Items 1–4 are the credible "nobody misses EduPage" core for families; 5–6 cover
the office. Akuru already beats EduPage on payments, the public website, the
digital library/bookstore, prayer times and Dhivehi UI — and the recitation
module is differentiation EduPage cannot match.

## 5. A live bug this comparison surfaced

Roles in Akuru are additive but `DashboardController::index()` is an `elseif`
chain that checks `isTeacher()` before `isParent()`. **A teacher who is also a
parent can never reach the parent home from login.** EduPage solves the same
problem with account switching *and* enforces that only a genuine parent account
may give parent confirmation on a registration. Whatever E7 does, it must not
paper over this ordering bug — fix the routing, then add switching.

## 6. How to use this checklist

EduPage is the *former* system, so there is no dual-running to manage — this is
the bar of remembered expectations. Ship 1–4 before or with the app shell. If
the aSc timetable desktop tool is still used, keep an import/export path rather
than rebuilding its editor. Modules marked ➖ need an owner decision about
whether the Institute runs that service at all, not engineering effort.
