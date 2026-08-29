# EduPage → Akuru Parity Checklist

Context: Akuru Institute used EduPage (`akuru.edupage.org`) **before this platform
was built** — it is the system staff, parents, and students were accustomed to.
This document maps every feature of that previous system (from operator screenshots
of the still-installed app, 2026-08-29, EduPage 3.12.0) to what exists in this
codebase. It serves as the feature benchmark the platform should meet so nobody
misses the old system, and as the design reference for the future app shell.
Companion to `docs/RECITATION_MODULE_PLAN.md`.

Legend: ✅ parity or better · 🟡 partial · ❌ not built.
"Verified" = checked in code this session, not assumed.

## 1. Feature map (from the real akuru.edupage.org menu)

### Communication

| EduPage item | Akuru | Evidence / gap |
|---|---|---|
| Messages / All inboxes | 🟡 | `Notifications\Models\Message` (attachments, read_at) exists; no polished inbox UI — not a daily habit yet |
| Chat | ❌ | No real-time chat; async messages only |
| Payments | ✅ better | BML gateway, invoices, wallet, gift cards, refunds (P4.3) |
| Canteen menu | ❌ | Not built — decide if relevant for the Institute at all |
| Photos / Noticeboard | 🟡 | Public gallery + news exist; no in-portal noticeboard feed for enrolled families |
| Registration / Surveys | 🟡 | Admissions/event/course registration ✅; survey builder ❌ |
| Applications (generic forms + approval) | 🟡 | Admissions applications ✅; generic form builder ❌ |
| Student pick up from school | ❌ | Not built |

### Education

| EduPage item | Akuru | Evidence / gap |
|---|---|---|
| Timetable | ✅ | `Academics\Models\Timetable` + `TimetableConflictException`; portal views exist |
| My courses | ✅ | Portal enrollments |
| HW / exams | 🟡 | Exams fully ✅; homework lives only in `LessonLog` — no student-facing homework list with due dates |
| Preparations (lesson prep) | 🟡 | Lesson logs + teacher plan views (D3); not a prep/materials library |
| Substitution (+ administration) | ✅ | `TeacherAbsence`, `SubstitutionRequest`, `SubstitutionAssignment` |
| Library | ✅ better | Reader + bookstore + writer payouts (L-track) |
| Timetables Online (aSc builder) | 🟡 | Timetable data model ✅; aSc's drag-drop builder is far ahead — accept simpler editing |

### Evaluation

| EduPage item | Akuru | Evidence / gap |
|---|---|---|
| Grades / Results | ✅ | Gradebook + weights + report cards (C2) |
| Attendance | ✅ | Registers with parent-notified tracking |
| Arrivals / departures of student | ❌ | Only staff attendance exists (HR); no student check-in/out |
| Competences | ❌ | Not built (Quran milestones are a subject-specific cousin) |

### Structural / other

| EduPage item | Akuru | Evidence / gap |
|---|---|---|
| Multi-account switcher (parent + own student + teacher accounts in one app) | 🟡 | Roles on one login ✅; switching between separate accounts without re-login ❌ |
| Lost and found | ❌ | Trivial slice if wanted |
| Student work showcase | ❌ | — |
| My profile / Settings / contact | ✅ | — |

## 2. The home-screen pattern to copy (design target for the app shell)

EduPage's home screen (teacher view screenshot) earns daily opens with three moves
the Akuru app shell should reproduce:

1. **"Timetable tomorrow" strip at the top** — the next school day's periods visible
   before any tap. Akuru equivalent: next classes / next assignment due / next exam
   from the D1 composed portal home data.
2. **Tile grid where every tile shows live status** — "Messages · No new messages",
   badge counts, beta tags. Tiles are a glanceable summary, not just navigation.
   Akuru equivalent: unread messages, homework due count, unpaid invoices, next
   prayer (already computed), attendance flags.
3. **Identity always visible** — school name + "Teacher · akuru" under the title;
   with multiple accounts the switcher is one tap away. Akuru equivalent: name +
   role chip; account switcher only if/when gap #8 is built.

The red "+" FAB (quick actions: new message, new homework…) is worth copying for
teachers.

## 3. Gaps ordered by daily-parent/student impact (the migration path)

Effort assumes the established slice discipline; each is independently shippable.

| # | Gap | Why it matters daily | Rough effort |
|---|---|---|---|
| 1 | **Portal home as status-tile dashboard** (pattern in §2, web now, app shell later) | It is the screen; everything else hangs off it | 1–2 wks |
| 2 | **Messaging inbox UX** (threads, compose to teacher/class, unread badges, attachments — model exists) | The #1 parent habit in EduPage | 2 wks |
| 3 | **Student-facing homework** (assignment list + due dates + done state; teacher entry via register/lesson log they already fill) | Second most-opened screen | 1–2 wks |
| 4 | **Noticeboard feed** (school/class announcements with photos, in-portal) | Replaces the photos/notice habit | 1 wk |
| 5 | **Surveys / generic forms** (build form → target group → collect + CSV) | Registration/Surveys + Applications parity | 2 wks |
| 6 | **Student pick-up notice** (parent taps "picking up now" → gate/teacher list) | Small but loved; islands context may differ | ~3 days |
| 7 | **Arrivals/departures** (student check-in/out, parent visibility) | Only if the Institute physically wants gate tracking | 1 wk + hardware question |
| 8 | **Account switcher** (multiple linked logins, one app) | Parents who are also staff | 1 wk |
| 9 | Competences framework | Nice-to-have reporting | 2 wks, defer |
| 10 | Canteen, lost & found, work showcase | Only if the Institute runs these services | small, defer |

Items 1–4 are the credible "leave EduPage" core for families; 5 covers the office;
6–10 are optional depth. Recitation module (own plan) is Akuru-only differentiation
EduPage cannot match, alongside the L-track library, prayer times, and Dhivehi UI.

## 4. How to use this checklist

EduPage is the *former* system, so there is no dual-running to manage — this list is
the bar of remembered expectations. Items 1–4 are the features families were
accustomed to daily and would notice most if the platform's app launches without
them; ship those before (or with) the app shell. If the EduPage subscription or the
aSc timetable-builder desktop tool is still paid for or occasionally used, item
"Timetables Online" is the one capability worth keeping an export/import path for
rather than rebuilding their editor.
