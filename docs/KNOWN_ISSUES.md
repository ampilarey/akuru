# Known issues

Ranked by **harm > wrong data > blocked task > confusion > cosmetic**.  
Evidence is a pilot section, a `file:line` on current `main` (after #86–#93), or a merged PR.

Re-checked 2026-08-26 against merged `main` and Round 3 (`docs/PILOT_REHEARSAL.md`);
the owner-decision list below re-audited against `main` on **2026-09-12**. Round 2 items that landed in #86–#92 are in **Fixed on main** at the bottom — not dropped, not still claimed open.

---

## Decisions only the owner can make (consolidated 2026-09-10, re-audited 2026-09-12)

The agent-buildable backlog is empty. **Re-audited 2026-09-12** — item 6 was
wrong (all seven Wave 4 features ship), item 9 was materially overstated, and
the audit itself uncovered a live defect, now fixed (see "Found by the
2026-09-12 audit" below). Everything below was raised during
autonomous sessions, deliberately **not** decided, and scattered across STATUS
sections — collected here so there is one list to work from. Each is phrased as
a question with a default, so "do nothing" is always a legible choice.

**Before any deploy**

1. ~~**Walk the app on `test.akuru.edu.mv`.**~~ — **done 2026-09-24
   (STATUS §5fz).** All thirty-five browser walks pass against the staging
   host, seeded there by the owner: six runs, the reds all seeder
   assumptions, walk timing and the walker's timezone, plus one dead
   dashboard list and one silent recorder, every one fixed. The deploy
   script, the built assets and the seeded database on that host are sound
   for everything the walks cover. Still unwalked there: OTP login (owner
   action 1's second box).
2. **Set `BML_WEBHOOK_SECRET`**, and confirm with BML that they sign HMAC-`sha256`
   over the raw body under `X-BML-Signature`. The webhook now fails closed
   (STATUS §5bp), so **with no secret and no opt-out, no payment will confirm**.
   The implementation's assumption about their scheme has never been checked
   against BML's own documentation.
3. **Verify the `permissions` table** on `test.akuru.edu.mv` holds all 34 dotted
   names, and that the six seeder-only roles exist. §5bo fixed three rows going
   forward; it cannot tell you what that database currently holds.
4. **Rotate the super-admin password.** Raised repeatedly; the seeded
   credentials are in `docs/AUTHENTICATION_GUIDE.md`.
5. **Apply branch protection** (`docs/BRANCH_PROTECTION.md`). Structurally
   impossible from an agent session.

**Product scope**

6. ~~**Wave 4 — does the Institute actually run these?**~~ **All seven are
   built** (re-verified against `main` on 2026-09-12: routes, models,
   migrations and feature tests for each). E8 pick-up, E15 lost & found,
   E16 physical lending, E17 interest groups, E18 gate arrivals, E19 sensitive
   information, E21 work showcase.

   This entry said "Verified against the code on 2026-09-10: all seven
   genuinely unbuilt" and priced them at ≈7½–8½ weeks. That was already wrong
   when written for some of them and wrong for all seven within days, and it
   is the worst kind of stale: it asked the owner to decide whether to fund
   work that already existed. Two decisions **do** survive it, and they are
   about operating the features rather than building them: **E18 needs the
   hardware decision** (what scans at the gate) and **E19 needs the privacy
   policy** that governs who may read a sensitive note.
7. **`docs/APPSHELL_NAV_IA.md`** — accept / accept with edits / reject. The nav
   wrap is still live. See top-five item 2. Now measured in a browser: ~90
   links across **eleven rows**, roughly the top quarter of a 1200px viewport,
   on every page (STATUS §5bv).
7b. **Bidi alignment on Dhivehi and Arabic screens** (STATUS §5bz). An English
   sentence on an RTL page renders its full stop at the **front**
   (`.No exams still in marks entry after the exam date`). One CSS rule fixes
   it — `unicode-bidi: plaintext` under `[dir="rtl"]`, tested live — but the
   same rule left-aligns English text, and with ~87% of the UI still English
   that means nearly every line on every RTL screen. **Correct punctuation and
   left alignment, or wrong punctuation and right alignment**, until translation
   catches up and the question disappears. Screenshots of both were captured
   during the walk; decide from those, not from this description.
8. **Confirm or reject `docs/migrations/s11-deploy-3-cleanup-proposal.md`.**

**Security and permissions**

9. **The Hifz module declares no role guard on its routes** —
   `app/Domains/Hifz/routes.php` is still `['auth', 'trackActivity']` and
   nothing else. **Narrowed twice since this was written.** F5 (ADR-029) took
   sessions, session records, mistakes and the mushaf admin out of that file
   entirely; what is left is the hub, five dashboards, programmes, enrolments,
   milestones and reports.

   And the remaining surface is **not** the open door the sentence implies.
   `tests/Feature/Hifz/HifzCrossRoleAccessTest.php` now sweeps every
   parameterless Hifz GET as two different families in the same halaqa and
   pins what actually happens: every controller either authorizes explicitly or
   scopes its query through `HifzScopeService`, the reports are gated on
   `view_hifz_reports`, and the one controller that does neither
   (`HifzHubController`) is a redirect ending in `abort(403)`. No screen showed
   one family the other's child.

   So the role matrix is still a product decision worth taking — a parent can
   load `/hifz/supervisor` and see a supervisor-shaped page scoped to their own
   children, which is confusing — but it is a **tidiness** decision, not an
   exposure. Default: leave the routes as they are and keep the test.
10. **`admin` is granted `Permission::all()`, identical to `super_admin`**,
    while the comment directly above it in `RoleSeeder` says "most permissions
    (school operations, not system-level)". The code and its comment disagree;
    which one is wrong is yours to say.
11. **Only three of the nine roles are created by a migration** (`super_admin`,
    `reviewer`, `writer`). The other six exist only if `RoleSeeder` has run, so
    every permission-granting migration no-ops its role grants on a
    migrate-only database — meaning **role changes cannot be shipped by deploy
    at all**. Moving role creation into a migration touches the role matrix.

    **Partly acted on, 2026-09-13 (§8 sweep).** Migration
    `2026_09_13_000005` now `firstOrCreate`s two of them: `course_creator`,
    which SPEC §8.3 names and which did not exist at all, and `supervisor`,
    whose §8.4 duties all answered 403 because the role held no `courses.`
    permission and was absent from the `/catalog` route group. On every
    existing deployment `supervisor` is already there, so that half is a no-op
    and the role matrix does not move; it matters only on a migrate-only
    database, where the grant would otherwise have silently done nothing and
    left §8.4 as broken as it was found.

    **Still open, and still yours:** `admin`, `teacher`, `student`, `parent`
    and `headmaster` remain seeder-only. `SpecRolesExistTest` carries an
    expectation that **fails when this is fixed**, pointing back here, so the
    note cannot rot into a false claim.
12. **A supervisor can grant a place on a paid course.** The admissions group is
    guarded by role alone, while the money endpoints next door also require
    `can:payments.refund` / `can:payments.record`. Tightening it changes who can
    do their job during admissions.

**Data model**

13. **Repeating pupils keep a roster row on the *old* academic year.**
    `repeat()` touches the existing row rather than creating one on the target
    year. Intended, or a gap?

**Language**

14. **200 English UI keys have no Dhivehi and no Arabic**, 152 of them
    referenced from live `public.*` pages — the marketing site, admissions and
    checkout. A ratchet stops it growing (STATUS §5bm); closing it needs a
    native speaker, and deliberately was not attempted by the agent. Both
    languages are now editable from the admin screen without a deploy
    (§5bn), so this can be done by a person with no repository access.

---

## Found by the Bookstore audit (2026-09-26)

### Deleting a customer or a vendor would take their orders and money records with them — **open, latent, platform-wide**

`bookshop_checkouts.user_id`, `orders.user_id` and the `vendor_id` foreign
keys on `orders`, `vendor_earnings` and `vendor_payouts` are
`cascadeOnDelete` (B2, B6). Nothing deletes a user or a vendor today —
accounts are deactivated, shops suspended — and Finance's
`payments.user_id` cascades the same way, so the Bookstore followed the
platform's precedent. But a paid order or an earning that vanishes with
its person is against the spirit of rule 12 (money records are kept, not
dropped). **Fix**: one additive migration, platform-wide, that re-creates
those foreign keys `restrictOnDelete` (payments, the wallet ledger,
orders, checkouts, earnings, payouts) so a delete is refused while money
hangs off the row. Not a Bookstore-only change, so not done in the audit
(BOOKSHOP_PLAN §15 finding 7).

### A suspended shop's owner cannot move its orders in flight — **open, by design; a "paused" state would soften it**

Suspension shuts the portal (BOOKSHOP_PLAN §15 finding 8). Orders already
paid at that moment can only be refunded or cancelled by the office from
`/admin/bookshop`. If a shop needs to stop selling but finish delivering,
a `paused` vendor status (portal open, products hidden) is one enum value
and one query clause.

## Found by the bookstore's B3 walk (2026-09-26)

### Ten cart adds in a minute got the checkout refused (429) — **fixed for the bookstore (2026-09-26); open elsewhere**

Laravel's plain `throttle:N,1` keys on the signed-in user alone
(`ThrottleRequests::resolveRequestSignature`), not on the route, so every
route with a plain throttle shares **one counter per person**, each checking
it against its own limit. A customer who put ten or more things in the cart
within a minute (`throttle:60,1`) was then refused at checkout
(`throttle:10,1`) with a bare 429. The B3 walk found it by running the
checkout walk back to back: intermittent, at a different step each time.

**Fixed for the bookstore**: each of its seven limits now names its own
prefix, the third argument (`throttle:60,1,shop-cart`,
`throttle:10,1,shop-checkout`, …; `routes/web_public.php`).
`BookshopCheckoutTest` fills twelve cart lines and checks out, and fails with
a 429 on the old routes.

**Still open**: about thirty other routes use a plain `throttle:N,1`
(library checkout, wallet redeem, gift cards, course waitlist and syllabus,
the public forms), so they still share one counter per person or IP — a
parent who sends several forms and then buys a book in the same minute can
be refused. Harm is low (one minute's wait) and nobody uses the site for
real yet (ADR-021). The fix is the same one-word prefix per route, best done
in one sweep with a test that pins every `throttle:` in the route files to
carry a prefix. Not done here: outside B3's scope (rule 1).

---

## Found by the Library completion audit (2026-09-25)

### A PDF uploaded to the Library could not be read — **fixed (2026-09-25)**

**Severity:** a book the office uploaded as a PDF, with no HTML body, was
listed with *Read online* and opened to "This item has no reader pages
yet" — for a paid item, after payment. The original was stored privately
(correct) and never read; pages came only from the body's page-break
markers.

**Fix (STATUS §5gl):** pages are extracted from the PDF on save by a
pure-PHP reader (`App\Support\Pdf`) behind `PdfPageTextExtractor`; the
body wins when both exist; a scan yields no pages and the uploader is told
at save time. **Production, once after deploying:**
`php artisan library:sync-pages` rebuilds pages for every item that has a
PDF and no pages.

### A library buyer got an "Enrollment confirmed" email — **fixed (2026-09-25)**

**Severity:** confusion, and a wrong message to the office. Since L3 every
confirmed payment raised the enrolment notice: the payer received
*Enrollment confirmed* with an empty course table and an SMS, and the office
received *new enrolment* mail and SMS for a book. Found by the gift card
purchase slice, which would have sent the same. **Fix (STATUS §5gn):**
`RaisePaymentNoticeReady` raises the notice only for payments that carry a
course; library items and gift cards have their own listeners.

### Nothing linked a signed-in reader to the Library — **fixed (2026-09-25)**

`/my-library` and `/my-wallet` were addresses to type. The shell's *Mine*
group and the public site's account menu now carry them (STATUS §5gn).

### PDF text extraction has known limits — open, by design of the hosts

- A two-column page reads across the columns, not down them.
- Producers that write right-to-left text glyph-reversed (some office
  suites) come out reversed within words. Browser-printed PDFs (the
  tested case) read correctly in English, Arabic and Dhivehi. **Spot-read
  page one of a Dhivehi or Arabic PDF before publishing it**; if it reads
  wrongly, paste the text into the body, which always wins.
- A scanned book is pictures: no text, no pages, and the save says so.
  OCR is not available on these hosts.
- Page *images* (§36's other option) are not produced: no Imagick,
  poppler, ghostscript or mutool on the hosts.

`--all` on `library:sync-pages` rebuilds every item after the extractor
improves.

## Found by owner decision 13 (2026-09-25)

### A stranger could link themselves to a real pupil from the public form — **fixed (2026-09-25)**

**Severity:** disclosure of a child's records to somebody outside the
family. The public registration form links the registering account to the
child it names, matching an existing pupil by ID card number. The link's
`verification_status` existed and the office could set it, but nothing
read it, so anyone with a phone could register a "child" under a real
pupil's number and then read that pupil's attendance, invoices and
messages, receive their absence SMS, and book a meeting about them.

**Fix (STATUS §5gk):** verification is a gate. Every family-facing resolver
goes through `VerifiedGuardianLink`; existing links were backfilled
verified; the office's own attach verifies as it goes; a self-registered
link starts unverified and shows as *awaiting the office* until a member
of staff verifies it on the pupil's Guardians tab. The office finds them
with the *Awaiting parent verification* filter on the student directory.
`GuardianLinkGateTest` pins each door; `family.mjs` walks the loop.

## Found by Deploy 3 slice 2 (2026-09-25)

### A child's login, made at registration, was never linked to the child — **fixed (2026-09-25)**

**Severity:** a family is told their child has an account and the child
cannot use it. When a parent registers a new child and sets a password,
`EnrollmentService` created the child's user, then copied the parent's
verified mobile onto it as a contact. A number belongs to one account
(`user_contacts_type_value_unique`), and registration requires a verified
contact, so for every parent who verified by mobile the copy failed. The
exception was logged and swallowed, the new user was left behind with no
student attached, and nothing on screen said so.

**Fix:** the copy is gone, because it was never needed. The forgot-password
page already finds a child's student record, then its guardian, then the
guardian's verified mobile. The user, its role and the link to the student
are now written in one transaction. `RegistrationWritesStudentsOnlyTest`
registers a child with a password, checks the login is on the student, and
requests a reset by the child's ID card to check the code goes to the
parent's phone.

### A registrant who leaves gender empty is recorded as male — **open**

**Severity:** wrong data, small. The registration forms let gender be left
empty; `students.gender` is a required `enum('male','female')`. The dual
write filled the gap with `male`, and `RegisterCourseStudentAction` keeps
that default so this slice changes no behaviour. The honest fix is a
nullable column plus every reader that assumes a value, or a required field
on the form. That is a product choice, not a cleanup step.

## Found by the gate card slice (2026-09-24)

### The QR on certificates and ID cards cannot be scanned — **fixed (2026-09-25)**

**Severity:** a promise on paper that does not work. `App\Support\Services\StudentNumberQr`
draws the three corner squares of a QR code and fills the rest with hashed
bits; it is not a QR encoding. `IssueCertificateAction` prints it as the
certificate's *scan to verify* code and `GenerateIdCardAction` prints it on
the ID card. Checked 2026-09-24 with a real decoder (`jsQR`, the one the gate
uses): the generator's code for `https://akuru.edu.mv/verify/ABC123` does not
decode at all; a genuine QR of the same address decodes at once. So nobody
has ever been able to verify a certificate by scanning it.

**Why not fixed in that slice** (rule 1): the gate cards draw their QR in the
browser with the `qrcode` package; certificates and ID cards are
server-rendered documents, so the fix needs a real encoder on the server.
Composer's GitHub-hosted QR packages were unreachable from the agent sandbox
that day (its GitHub access is scoped to this repository), which is also a
question for the fix: a pure-PHP encoder vendored in, or the package added by
someone with normal network access. The test to write first is a round trip:
encode, render, decode, compare.

**Fix (STATUS §5gi):** a small pure-PHP encoder in the app,
`App\Support\Qr\QrMatrix` (byte mode, level M, versions 1–10), behind the
same `StudentNumberQr::svg()` both documents already call. Its output matched
the npm `qrcode` package module for module in 64 comparisons across
versions 1–10 and all eight masks; `QrCodeTest` keeps a representative set as
a fixture. The certificate walk now decodes the printed QR with `jsQR` and
sends the stranger wherever it points, and it fails on the old generator.

## Found by the navigation slice (2026-09-24)

### Any parent or student can open the whole school's attendance report — **fixed (2026-09-24)**

**Severity:** disclosure. `RoleSeeder` grants the `parent` and `student`
roles `view_attendance`, and `AttendanceReportController::index` admits
`view_attendance || manage_attendance`. So a family signed in to the portal
can type `/academics/attendance` and read every pupil's rows, the chronic-
absence list and the unexcused list for the school. Found by
`NavigationIsGroupedByRoleTest`'s open-every-link check the day the shell
began hiding links people cannot open — this one it *could* open (STATUS §5ga).

**Fixed** in the slice after (STATUS §5gb): `AttendanceReportController`
gates the report and both CSVs on `manage_attendance || registers.manage` —
every staff role holds one, no family role holds either.
`AttendanceReportIsStaffOnlyTest` seeds the real roles and reads a parent
and a student refused (each still holding `view_attendance`) and all five
staff roles admitted. The navigation hint mirrors the new gate.

## Found by the 2026-09-12 audit

### A full class could be oversold from the admin screen — **fixed (2026-09-13)**

**Severity: P1 — wrong data (a class over its own limit), one click deep.**

`AdminEnrollmentController::activate()` was `$enrollment->update(['status' =>
'active'])`: no Action, no seat check. `rejected`, `cancelled` and `suspended`
are not occupying statuses, the Blade screen offers "Activate enrolment" on
anything not already active, and one click moved a row into an occupying status
without consulting the limit.

`SuspendEnrollmentAction::reinstate()` **already did the check**, with a comment
explaining why. The same crossing was guarded on one route into `active` and not
on the other.

`ActivateEnrollmentAction` (Courses) now charges only the crossing — a `pending`
enrolment already holds its seat and must not be charged twice, or every
activation on an exactly-full class would be refused. Both paths now reserve and
save in one transaction, which `EnforceSeatLimitAction`'s docblock requires and
`reinstate()` was not doing.

### An abandoned checkout burned a discount code for good — **fixed (2026-09-13)**

**Severity: P2 — a family loses a discount they never used.**

`RecordDiscountRedemptionAction`'s docblock describes `RELEASED` as the state
for a failed payment, *"so usage limits never leak from abandoned checkouts
forever"*. **Nothing ever wrote it** — `transition()` was only ever called with
`'confirmed'`, and there is no payment-failed event in the system.

`ResolveDiscountAction` counts pending and confirmed against `usage_limit` and
`per_user_limit`, so opening checkout and closing the tab spent the code, and a
100-use code ran out after 100 attempts.

`akuru:prune-expired` now releases the pending redemptions of the enrolments it
cancels and deletes. Confirmed ones are left alone.

### The admin enrolment screen could not show a refusal — **fixed (2026-09-13)**

**Severity: P3 — confusion, but it hid the two fixes above.**

`admin/enrollments/show.blade.php` rendered `session('success')` and not
`session('error')`. `suspend()` and `reinstate()` have returned
`back()->with('error', ...)` since they shipped, so a refused action redirected
to a page that said nothing. Found by walking the seat fix, not by reading.

### A salary deduction decided by a substring of a free-text note — **fixed (2026-09-13)**

**Severity: P1 — wrong money, on a payslip.**

`leave_types.paid` is a boolean. `ApproveStaffLeaveAction` had it in hand and
spent it writing an English sentence into `staff_attendance.remarks`
("Approved unpaid leave"), and `CountUnpaidLeaveDaysAction` read it back with
`where('remarks', 'like', '%unpaid%')->count()`. That count multiplies
`basic_salary / working_days` onto a payslip.

Three failures, all confirmed by tests that fail against the old code:

1. **A half day cost a whole day's pay.** `'Half-day unpaid leave'` matches
   `%unpaid%`, and `count()` counts rows. The half that `CountLeaveDaysAction`
   and the leave ledger are both careful about was dropped at the only point
   where it cost money. On a 10,000 salary: **454.55 deducted instead of
   227.27.**
2. **The platform is trilingual.** A remark in Dhivehi or Arabic never matched,
   so unpaid leave silently became paid.
3. **Any note containing the word deducted a day** — including one saying the
   leave was *not* unpaid — and editing a note changed someone's salary.

This was the **only** place in the application where business logic read a
free-text field with `LIKE` (the one other match is a CLI search).

Fixed by carrying the fact: `staff_attendance.leave_paid` and
`leave_day_fraction`, written by the approval action and read by the counter
(rule 11). Additive migration + exact backfill — the four strings were
machine-written, so half-days recover precisely — then the reader switched
(rule 9). `remarks` keeps every word it had and is a note for a human again.

Walked in Chromium (2026-09-13) with `PAYROLL_ENABLED` on locally: a half-day
unpaid leave gives Aishath Shifa gross **10,000.00**, net **9,072.73** on
`/en/hr/payroll`. Before the fix, net was 8,845.45.

**Still open (owner question, not a defect):** `CountLeaveDaysAction` counts
**calendar** days, so leave spanning a weekend or a public holiday spends
entitlement on days nobody works. The system knows its holidays —
`calendar_days` and `AutoFillHolidayStaffAttendanceAction` — but no spec says
whether leave is counted in calendar or working days, so this was not changed.

### Two percent fee adjustments billed a family minus 200 — **fixed (2026-09-13)**

**Severity: P1 — wrong money, on an invoice a parent receives.**

`ApplyFeeAdjustmentsAction` reduced the base by the discounts already on the
invoice **for fixed adjustments only**. Percent adjustments each computed
against the full gross, so two approved ones stacked past 100%:

| | Before | After |
|---|---|---|
| Subtotal | 1000.00 | 1000.00 |
| Scholarship 60% | 600.00 | 600.00 |
| Staff-child 60% | 600.00 | 240.00 *(60% of the 400 that is left)* |
| **Total** | **−200.00** | **160.00** |

Neither adjustment is exotic. A school that employs parents issues both.

A negative invoice is not a rounding complaint: it is a bill saying the school
owes the family money, and it feeds arrears and collections totals as a
negative, understating what the class actually owes.

The existing test used one valid percent adjustment and one fixed — the second
percent adjustment in the fixture sat outside its validity window — so the case
was never exercised.

The fix applies the same remaining-base rule to both bases, which is what the
fixed branch already assumed. `$already` counts every discount on the invoice
rather than only those the adjustment's item types would have matched, because
adjustment lines carry no `fee_item_id` and cannot be attributed back to a type;
with mixed scopes that under-discounts rather than over-discounts, which is the
direction to err in, and it is the trade the fixed branch was already making.

Two tests: the stacking arithmetic (840 / 160), and the invariant on its own —
three 90% adjustments still cannot make the total negative.

Walked in Chromium (2026-09-13), `/en/finance/invoices`: Fatima Yoosuf, with
both adjustments, **240.00** against 1500.00 for her classmates. Before the fix
her row read **−300.00**.

### Every term grade was deflated by the part of the year that had not happened — **fixed (2026-09-13)**

**Severity: P1 — wrong data (grades), on report cards parents download.**

S3_SPEC §S3.3: *"exempt excluded from averages, absent counts as 0 unless
setting says exclude."* Excluding something from an average means taking it out
of the **divisor** too. `ComputeTermGradesAction::student()` accumulated
`$usedShare` and then tested it only for zero, so an excluded exam's weight was
scored as nothing — arithmetically identical to scoring zero.

Three consequences, in increasing order of how often they happen:

1. **`is_exempt` did nothing.** A pupil exempted from an 80%-weight final who
   scored full marks on the 20% quiz was given **20%** for the term.
2. **The `exams_exclude_absent` setting did nothing.** With it on or off, an
   absent pupil got the same number.
3. **The ordinary case.** A scheme gives weight to exam types that have not
   happened yet — the default scheme puts 40 on the Final, 30 on the Midterm,
   10 quiz, 10 assignment, 5 practical, 5 oral. Publish the Final and nothing
   else, and every pupil in the class is multiplied by 0.4. **90/100 became
   36% and grade E — a fail.**

The repo's own tests recorded all three as correct, with the grade letter next
to them: `WeightSchemePersistTest` asserted `36.0`, `'E'`, and a report card
containing `>E<` for a child who scored 90.

`student()` now renormalises to the share that actually counted. When nothing is
excluded the factor is 1, which is why no ordinary fully-examined term changes.
Components carry `effective_share` alongside the scheme's `share`, and a test
pins that the breakdown still sums to the term percent — S3.4 calls that json
"per-exam breakdown for transparency", and parts that do not add up to the whole
explain a number the pupil did not get.

Walked in Chromium (2026-09-13), `/en/exams/gradebook` for Grade 5 / Quran
Memorization / Term 1, one published Final out of six weighted types:

| Pupil | Mark | Term % | Grade | Rank |
|---|---|---|---|---|
| Fatima Yoosuf | 90.00 | **90.00** | **A** | 1 |
| Hussain Shareef | 55.00 | 55.00 | C | 2 |
| Aisha Mohamed | 30.00 | 30.00 | E | 3 |

Before the fix those three read 36.00, 22.00 and 12.00 — all grade **E**.

### Two guardian↔student pivots, and the product wrote the wrong one — **fixed**

`guardian_student` is the live pivot: every notification listener (absence SMS,
behaviour SMS, exam results, report cards) and eleven People actions — the
collection policy, financial responsibility, guardian access — read it.
`student_parent` is a second pivot that **nothing in the application wrote**.

`User::schoolChildren()` read the dead one, through
`ParentGuardian::students()`. Its only caller,
`ParentHifzDashboardController`, turns an empty child list into `abort(403)` —
so **every parent the product itself had linked was refused their own child's
Hifz dashboard**. `HifzScopeService::parentChildIds()` had the same bug, so
`canAccessStudent()` refused them a second time.

It looked like it worked because `HifzDemoSeeder` was the single writer of
`student_parent`: the demo parent could open the screen and nobody else could.

Fixed by adding `ParentGuardian::children()` over `guardian_student` and
pointing both readers and the seeder at it. `students()` is marked deprecated
rather than deleted, and the table is left populated — rule 9 does not drop a
populated table in the deploy that stops using it. Removing it is a Deploy-3
cleanup item.

**Found by a positive control, not by the guard.** The cross-role test asserts
that no family sees another's child; that assertion passed while every parent
screen was 403ing, because a page nobody can load leaks nothing. The assertion
that caught it is the one requiring each caller to have seen *their own* child
somewhere — without it the whole sweep was vacuous for parents.

---

### Certificate template bodies were sanitised by something that is not a sanitiser — **fixed (2026-09-14)**

**Severity:** stored XSS with privilege escalation. Found by sweeping every
`{!! !!}` in the Blade views and asking what sanitises each one on the way in.

`SaveCertificateTemplateAction` cleaned the body with
`strip_tags($body, '<p><br><strong><em><h1><h2><h3><span>')`. That removes
disallowed **tags** and keeps every **attribute** on the ones it allows, so
`<p onmouseover="…">` survived into
`documents/course-certificate.blade.php`, which renders it with
`{!! $body_html !!}` as HTML (ADR-012 — HTML is the production output).

`catalog.certificates.*` is open to **`course_creator`**, the lowest
content-authoring role; certificates are opened by admins, students and
families. Script in one runs with the reader's session.

**The same line had already been fixed once**, in
`ValidateContentBlockDataAction` (#280), and that fix left a comment in its own
file explaining exactly this. It did not travel.
`tests/Architecture/StripTagsIsNotASanitiserTest.php` now bans `strip_tags`
with a second argument.

**And a gate for the sink already existed and was green.**
`RawHtmlRendersAreDeclaredTest` declared the certificate view as
*"system-generated: template body and QR svg"* — true of the QR, false of the
body. It was keyed by file, one reason each, and that file has two sinks of
different kinds. The gate now takes one entry per **sink**, keyed by
expression; a reason must **name the write path** rather than assert that the
value is safe; and it sweeps **React as well as Blade** — it had never been
able to see `dangerouslySetInnerHTML`, and the new UI is React. Those four
sites were checked and are sound.

### The return URL could confirm a payment nobody paid for — **fixed (2026-09-14)**

**Severity: P0 — real money.** Found by auditing CLAUDE.md rule 12 clause by
clause: *"Access to paid anything depends on BML **webhook** confirmation,
never the return URL."*

`GET payments/bml/return` carries the middleware `['web']` and nothing else —
no auth, no signature, no ownership check, every value from the query string.
It did not set the payment's status directly, and the controller even said so
(*"Finalize server-side; ignores return URL state entirely"*). But it wrote
`bml_transaction_id` from `?transactionId=`, and `finalizeByReference` then
asked BML about **that** id and confirmed the payment if the answer was
"completed".

`BmlPaymentProvider::queryStatus` returned its own argument back as the
result's `merchantReference`, so the answer always agreed with the question,
and nothing compared the two. The verification was genuinely server-side. It
verified the wrong transaction.

**The attack:**

1. Start a real checkout and abandon it — a pending payment now exists with
   `bml_transaction_id` null.
2. `GET /payments/bml/return?ref=<own ref>&transactionId=<T>`, where `T` is any
   completed BML transaction, **most easily one of the payer's own earlier
   purchases**.
3. The id is stored, BML is asked about it, BML says "completed", the payment
   is confirmed, `PaymentConfirmed` fires, the enrolment activates, a receipt
   is written and the family is notified.

Pay once, then confirm everything afterwards for free.

**Fixed** — `queryStatus` now reads BML's own merchant reference out of the
response (`localId`, exactly as the signed-webhook path already did), and
`PaymentService::finalizeByReference` refuses any result that does not name
this payment. A result naming no payment is refused too: the field used to be
whatever we passed in, so "missing" and "matching" were indistinguishable.
The transaction id from the redirect is kept as an untrusted *hint* about
which question to ask, which is all it ever was. `queryStatus` also
`rawurlencode()`s the reference before interpolating it into the API path.

**A test was encoding the bug.** `PublicPaidCheckoutTest`'s fake provider
answered `merchantReference: $merchantReference` — the question repeated back
as the answer, mirroring the real provider. A fake that always agrees cannot
express the disagreement the bug lived in, so the reconcile test passed
throughout. It now looks up the payment that actually holds the id.

**Operator note.** If BML's get-transaction response does not carry a merchant
reference field, return-URL finalisation will now decline rather than confirm,
and payments will wait for the signed webhook. That is the correct direction
under rule 12 — the webhook is the authority — but it is a behaviour change
worth watching on the first real transaction.

### Any signed-in session could take an account over permanently — **fixed (2026-09-14)**

**Severity: P1 — account takeover.** Found by auditing the
`unguarded_write_routes` baseline's own reasons, entry by entry.

`POST account/set-password` changed the current user's password **without
asking for the old one**, while `ProfileController` requires `current_password`
on the app's other password route. One door locked, the other not.

The dashboard only offers that screen to an account with no usable password.
The **route** offered it to everybody — SPEC §44/§45 again, *"do not rely only
on frontend button hiding"*, the same class as the entry already in this file
about a route that relied on a hidden button.

So session access — a stolen cookie, an unlocked shared device, an XSS
anywhere — became **permanent takeover**: the attacker sets a new password
having never known the old one, and the owner is locked out.

**Fixed** — `current_password` is required unless `force_password_change` is
set, which is the genuine "no usable password" state:
`AccountResolverService` creates OTP-only accounts with a random 40-character
hash nobody holds and sets the flag. Those accounts cannot supply a current
password and are not asked for one. The form renders the field exactly when
the validation will demand it, so the screen never becomes uncompletable.

**And the banner that offers it never rendered.** `$hasPassword` was
`! empty($user->password)`; `users.password` is NOT NULL and OTP accounts
carry that random hash, so it was **always true**. The "Set a password for
easier login" prompt was dead UI — the feature was unreachable through its own
entry point, which is presumably why nobody noticed the route was open. Both
halves came from the same root: `force_password_change` is the flag that
records the state, and neither the banner nor the route consulted it.

### The same phone number was also enough to get signed in as them — **fixed (2026-09-14)**

**Severity: P0 — session takeover.** The second door, found by auditing the
rest of the same controller after the first.

`enroll` and `continueForm` both did `Auth::login()` straight off
`session('pending_user_id')` — which `start` writes when the code is **sent**,
from a phone number in a public request body. The `hasVerifiedContact()` check
came **after** the login and returned a redirect, and **a redirect does not
undo a login**.

Demonstrated: POST `start` with a victim's number, POST `enroll`, and the
session is authenticated as them while the screen says *"Please verify your
contact first."*

Same scope as the first: only accounts whose contact is not yet verified, since
`start` short-circuits a verified one.

**Fixed** — one `verifiedPendingUser()` helper, used by both, which returns the
pending account only if this session entered its code, and **does not sign
anyone in**. Starting a session is left to each caller, after its own checks.
Fixing one door and not the other is how a door stays open, so both went
together.

### `resume` is a 24-hour magic link — **recorded, no change**

`courses/register/resume?flow=<uuid>` calls `Auth::login()` for the flow's
owner, and `RegistrationFlow::findResumable` matches on UUID alone when the
caller is anonymous. So the UUID is a bearer token that grants a full session.

**Not filed as a defect.** It is a random v4 UUID, it expires after 24 hours,
and it is never sent by SMS or email — it lives only in the returning visitor's
own URL. That is an ordinary resume-link design with a shorter life than most.

**Worth an owner's eye anyway**, because it is not written down anywhere: the
link is not single-use, it survives in browser history, and the session it
grants is a full one rather than a restricted "finish your registration" scope.
Narrowing any of those is a product decision, not a defect fix.

### A phone number was enough to claim somebody's account — **fixed (2026-09-14)**

**Severity: P0 — account takeover with no credential at all.** The open
question recorded earlier the same day, now resolved: **the attack works.**

`CourseRegistrationController::setPassword` writes a password, a name, a date
of birth and a national ID onto `session('pending_user_id')`.
`courses/register/start` is a public POST that takes a phone number from the
request body; on the returning-user branch it resolves that number to an
existing account and writes `pending_user_id` **when the code is sent**, before
anybody has entered anything. `setPassword` checked only that the value was
present.

So: POST `start` with a victim's mobile number, skip `verify` entirely, POST
`set-password`. Demonstrated end to end — password overwritten, `name` became
"Attacker X", `national_id` became "A999999". **The code went to the victim's
phone and was never needed.**

**Scope, established by running it:**

- An account whose contact is **already verified** is safe. `start`
  short-circuits it to the checkout login screen without writing the session
  keys. Now pinned by a test, because it is load-bearing.
- An account whose contact is **not yet verified** was fully claimable. That is
  every account `AccountResolverService` creates (`verified_at => null`) before
  its owner first signs in — **at go-live, every bulk-imported parent and
  student**.

**Fixed** — `verify()` records `otp_verified_user_id` for the account whose
code was actually entered, and `setPassword` and the form both refuse without
it. Compared against the user id rather than read as a boolean, so proof for
one account cannot authorise writing to another, and consumed on use so one
verification cannot authorise a second write.

**Three earlier attempts reported this as safe.** The first stored the victim's
contact unnormalised (`9995678` where the app stores `+9609995678`), so the
lookup missed and the funnel created a brand-new user — the attack "failed"
against an account that did not exist. The second could not send an OTP at all
(`LogSmsSender` had no `sendOtp`). The third had both faults at once. What
exposed all three was insisting on a **passing happy path** before believing
any refusal: until a legitimate run works, a refusal proves nothing.

### OTP login could not send a code anywhere except live-SMS production — **fixed (2026-09-14)**

**Severity: P0-class — a login path that does not work.**

`OtpService::dispatchCode` called `$this->smsGateway->sendOtp(...)`.
`SmsSenderInterface` declared only `sendSms`. `SmsGatewayService` happened to
have `sendOtp` as well, so the **live** driver worked — and `LogSmsSender`,
which implemented the interface faithfully and nothing more, did not have it.

`LogSmsSender` is the binding whenever live SMS is off: **local, staging, and
any production without `SMS_LIVE`.** So every mobile OTP threw
`Call to undefined method`, `OtpService::send` caught it, deleted the code it
had just written, and rendered *"Unable to send verification code. Please try
again."*

Verified directly: `app(SmsSenderInterface::class)` resolves to `LogSmsSender`
locally and `method_exists($it, 'sendOtp')` is **false**.

**Fixed** — `sendOtp` is declared on the interface and implemented on
`LogSmsSender`, which routes it through `sendSms` so the code lands in the log
and in `sms_receipts`. That is what a non-production environment needs: whoever
is testing a login reads the code out of the log.
`SmsSenderContractIsCompleteTest` now fails on any method called through the
interface that the interface does not declare.

**Bearing on P0 #1 (staging staff login), stated carefully.** That entry
records *password* login with seed passwords 302ing back, and this defect
breaks *OTP* login. It does not explain the recorded symptom and is **not**
being claimed as its fix. What it does mean is that OTP login on
`test.akuru.edu.mv` could not have worked either, so the next person testing
staging should retest **both** paths rather than assuming the password symptom
is the whole story.

### An exams account could read any generated document by id — **fixed (2026-09-14)**

**Severity: P1 — wrong data / privacy.** Found by asking the question
`PrivateMediaReadersAreScopedTest` asks, on the path that gate cannot see.

`exams.awards.download` bound `{award}` — the award **template** — and then
read `?document_id=` straight from the query string, ignoring the binding:

    $documentId = $request->integer('document_id');
    $file = app(ReadGeneratedDocumentAction::class)->execute($documentId);

`exams.manage` is held by teachers and exam staff. Any of them could pass any
document id and receive its contents: **another class's report cards**, anybody's
certificates, transfer certificates, ID cards — every generated document in the
application. The route parameter made it look scoped and scoped nothing.

This is #336's lesson on a second path. That fix pinned every caller of
`ReadPrivateMediaAction`; documents go through `ReadGeneratedDocumentAction`
and had no equivalent gate, so the same mistake was free to reappear — and had.

**Fixed** — the route binds the issued award (`StudentAward`, which is what
actually carries `certificate_document_id`; the old binding could never have
helped, since an `Award` is a template and holds no document) and the document
is read from that record. Five of the six callers were already doing this.
`PrivateMediaReadersAreScopedTest` now pins the document path too.

**Nothing in the frontend ever linked to this route**, which is presumably why
it lasted: it was reachable only by typing it. That also made the fix safe —
no screen could break.

### A signed-in visitor could repoint somebody else's enrolment at their own payment — **fixed (2026-09-14)**

**Severity: P2 — unauthorised mutation / disruption.** Found by sweeping for
the shape behind most of the day's findings: **an identifier taken from the
request where the owning record was available.**

`CheckoutController::start` binds `{course}` and then takes `enrollment_id`
and `student_id` from the request. `enrollment_id` was checked against the
bound course and **nothing else**, and the transaction does:

    $enrollment->update(['payment_status' => 'pending', 'payment_id' => $payment->id]);

So any signed-in visitor could pass a stranger's enrolment id on the same
course and repoint that enrolment's payment at their own — leaving somebody
else mid-checkout attached to a payment they do not control, and stuck pending
if it was abandoned. Enrolment ids are sequential integers.

`student_id` was validated as `exists:registration_students,id`, which says the
row exists and nothing about whose it is, so an enrolment could also be created
for a stranger.

**Fixed** — both are scoped to the students the payer may act for, which is the
app's own rule rather than a new one: themselves
(`registrationStudentProfile`) and their children (`guardianStudents`).

**Not a disclosure.** Nothing about the other family is returned; the cost is
disruption of their checkout, and a stranger enrolled on a course. Recorded at
P2 for that reason.

## Top five (remaining)

1. ~~**Staging staff login**~~ — **closed 2026-09-23/24 (STATUS §5fz).** The owner reset `admin@`'s password through tinker on the host and ran the (now idempotent) seeders there; all six pilot logins sign in by password on `test.akuru.edu.mv`, and the walks prove it thirty-five times over. OTP login there is still untested.
1b. ~~**Nothing merged since 2026-08 has been walked in a browser**~~ — **closed 2026-09-24 (STATUS §5fz).** Locally since 2026-09-10 (§5bu, §5bv; that walk found #234). On `test.akuru.edu.mv` since 2026-09-24: 35/35 on the sixth staging run, so the deployment question is answered as well as the code one.
2. ~~**AppShell nav IA**~~ — **accepted and implemented 2026-09-24 (STATUS §5ga).** The 109-link wrap is gone: a primary bar per role, every other screen under eleven labelled groups in a *More* menu, and nothing shown that the person could only be refused — visibility read off each route's own guard. `docs/APPSHELL_NAV_IA.md` has the map and where it lives.
3. **Parent notified column shows — on excused rows** — column exists (#86); SMS body is not in the portal.
4. ~~Shared Add-term form on every year card~~ — **fixed** (#13).
5. ~~Exam schedule form defaults wander~~ — **fixed** (#19), along with the
   report-card publish default (#21).

---

## P0 — Harm (real people, real messages, real money)

### 1. Staging staff login does not work with documented seed passwords — **fixed (2026-09-23)**

**Severity:** harm-adjacent / blocked production judgement — the intended pilot host cannot be used; public 200 hides that.

**Evidence:** Round 1 step 0; Round 2 opening; Round 3 ranked #1; archive “Staging credential smoke (2026-08-23)”. `https://test.akuru.edu.mv/en/login` 302s back to login for `admin@akuru.edu.mv` / `password`. This environment cannot SSH (`docs/STAGING.md` webhook only).

**Fixed.** The host's `admin@` had a password that was not the seeded one; the
owner reset it through tinker on 2026-09-23, then ran `UserSeeder` (which
until #441 could not run twice, so the other five pilot logins had never been
made there) and `SmokeMarkerSeeder`. On 2026-09-24 all thirty-five browser
walks passed against the host, signing in as all six logins by password
(STATUS §5fz). OTP login on the host is still untested — owner action 1's
second box. (Former P0 SMS live-bind is **fixed** — see Fixed on main #86.)

---

## P1 — Wrong data (identity, grades, documents)

### 2. Term grades render blank unless a weight scheme is actually saved

**Fixed** — Weights form posts numeric type percents (seeded from `default_weight`, sum 100) and redirects with `academic_year_id` so the year scheme resolves. See **Fixed on main**.

### 3. “Report cards” are HTML with empty grades, not PDF

**Fixed as a renderer decision** — HTML is the supported production output (ADR-012 amended). Empty cells without weights is the Weights issue (P1 #2), not a PDF bug. See **Fixed on main**.

### 4. Roster picker can still show two rows for the same identity with different numbers

**Fixed** — `identity_key` is name + DOB + national ID. Student number (including blank) and class do not distinguish. Assign still requires an explicit `student_id`. See **Fixed on main**.

### 5. Unification matcher / staging collisions — **closed by archive (2026-09-25)**

**Was:** wrong data if `--backfill` ran on a messy DB. Staging verify last recorded **red** (four collisions, 12/13 guardian users missing after `users:clear-non-admin`).

**Now:** Deploy 3 archived `registration_students` and retired the backfill and its verify command (STATUS §5gh). Nothing can re-run the matcher. The rows it never placed are kept, not guessed: they sit in `archived_registration_students` with an empty `unified_student_id`, and any enrolment that pointed at one keeps `archived_registration_student_id`.

---

## P2 — Blocked task (cannot finish the job in the product)

### 6. People → Students cannot create a child

**Fixed** — create/edit on `people.students.*` via `SaveStudentAction` + `ChangeStudentStatusAction`; guardian pivot; course-only nullables. See **Fixed on main**.

### 7. Class teacher assignment did not stick on Grade 5 B

**Fixed** — existing classes can PUT `class_teacher_id` from the class show page. Create-without-teacher then assign is covered. See **Fixed on main**.

### 8. Report card generate needs a template + queue worker

**Half fixed, half operator.** Generating with no applicable template now raises
a named validation error ("No active report card template applies to this
class.") rather than blowing up — `GenerateReportCardsAction::resolveTemplate`.
The queue half is unchanged and is **operator config**: `QUEUE_CONNECTION=database`
still leaves cards draft without a `queue:listen`/`queue:work` worker running.

### 9. Staging unify-verify / Deploy 3 / Track B

**Severity:** blocked **operator** tasks, not missing code. Do **not** execute Deploy 3 or start Track B from this list. See `STATUS.md` §3–4.

### 10. `/academics/gradebook` is 404

**Fixed** — `GET academics/gradebook` redirects to `exams.gradebook.index` (query string kept). Inertia static hrefs are asserted against registered GET routes. See **Fixed on main**.

---

## P3 — Confusion (easy to think it worked or failed)

### 11. AppShell nav is unusable as navigation — **fixed (2026-09-24)**

**Severity:** confusion / blocked. **109** wrapping links by 2026-09-23 (“50+” when this was written), duplicate “Report cards” / “Awards”, and every link shown to everybody whether or not it would refuse them. Round 3 ranked #2.

**Fixed.** The owner accepted `docs/APPSHELL_NAV_IA.md` as written; the shell now renders a server-built `nav` — a short primary bar per role and a *More* menu of eleven groups, with links the person could only be refused left out (STATUS §5ga). Staff “Report cards” and the family’s are in different groups and shown to different people. Logout unchanged (POST next to the name).

### 12. Seeder still inserts duplicate Extra year names

**Fixed** — year-creating seeders `firstOrCreate` by `name` (same uniqueness the UI already validates). Re-seed cannot insert a second Extra / Pilot / 2024-2025 row. See **Fixed on main**.

### 13. Shared Add-term form on every year card

**Fixed** — each year card owns its own `useForm` via a `YearCard` component, so
typing on one year no longer fills the others and "Add term" is unambiguous
about which year it hits. See **Fixed on main**.

### 14. Weights UI is a JSON blob of type ids → 0

**Fixed** — numeric inputs per exam type, live sum, seeded from `default_weight`. See **Fixed on main**.

### 15. Teacher grid offers `excused` / `left_early`

**`excused` fixed (2026-09-13). `left_early` is not a defect** — see below.

Filed as confusion; it was quiet. S2_SPEC §S2.4 says an excusal comes from
approving a guardian's note, which flips the matching absent rows and **links
`absence_note_id`**. The grid offered `excused` as a fourth button and the write
path took it, note or no note. Three things then followed from one mis-click:

- `RecordClassAttendanceAction::maybeNotify()` notifies on absent (and on late,
  by setting) and stays **quiet on excused** — so the family is never told the
  child is missing;
- since #17's fix the portal reads the same rule and shows such a row as **"Not
  applicable"** — the product telling the parent no message was due;
- `ListClassAttendanceAction::unexcused()` excludes excused rows, so the child
  also drops out of the chronic-absence list.

A missing child, invisible from three directions.

The rule now lives in the **writer**: `RecordClassAttendanceAction` rejects
`Excused` without an `absenceNoteId`. Every route into `class_attendance` goes
through `AttendanceWriterInterface` — `AttendanceWriterTest` pins that — so the
register grid, the daily grid, a CSV import and a future device are all covered
by one check. `AttendanceStatus::teacherSettable()` is what the two grids
render; taking the button away is the cosmetic half, and this repo has already
learned once (SPEC §44/§45) that a route relying on a hidden button is not
guarded.

Walked in Chromium (2026-09-13), logged in as `teacher@akuru.edu.mv`:

- register `/en/academics/registers/1` status options are now
  `present, absent, late, left_early`;
- `PUT` of `status: excused` straight at the route returns **422**: *"An absence
  is excused by approving the guardian's note, not by marking it excused here."*

**`left_early` stays.** It is a status S2_SPEC §S2.4 lists, no rule says it must
come from elsewhere, and a child who left early is a fact only the teacher in
the room knows. Removing it would be a product decision, not a defect fix.

### 16. Taught summary vs plan topic

**Fixed (2026-09-14)** — the second field stopped asking the question the first
one had already answered, and a copy the teacher never wrote stopped appearing
in it.

**Severity:** confusion / twice. Picking “Sun and moon letters” still invited
typing the same title (R1/R2 step 2).

Two fields, one under the other: a **Plan topic** select, and a textarea
labelled **What was taught**. Choosing a topic answers the second question as
well as the first, but the empty box below still read as a question, so it got
the same words typed into it — twice, by people who knew the form.

The form then taught the habit back. When the box was left empty
`SubmitRegisterAction` copied the topic's title into `taught_summary`, so
re-opening the register showed a summary the teacher had never written, sitting
in the box as if that were where titles go.

Both halves are gone:

- The title stays on the topic (rule 11). Nothing reads `taught_summary`
  expecting a copy — no screen displays the column outside this form — so
  dropping the copy changes no reader. Rows written before today keep theirs.
- With a topic picked, the box is labelled **“Anything the topic title leaves
  out (optional)”** and says what the register will read; a summary that only
  repeats the title is dropped rather than stored beside it. Only an exact
  repeat goes — case, spacing and a trailing full stop are forgiven, and
  anything with more in it survives whole.

With no topic picked nothing changes: the label is still *What was taught*, and
one of the two is still required.

Walked in Chromium (2026-09-14) on `/en/academics/registers/1`: picking the
topic relabelled the box and showed *“This register will read “Sun and moon
letters””*; submitting with the title typed in anyway, then reloading, gave an
empty box and the topic marked **(taught)** on the plan.

### 17. Parent notified column shows — on excused rows

**Fixed (2026-09-13)** — the boolean became three states, and reading the
sender turned up a second defect this entry did not know about.

The column rendered `guardian_notified ? 'Yes' : '—'` over
`status === 'absent' && receipt exists`, so one dash stood for four facts:
**present** (nothing is ever sent), **excused** (deliberately not sent — the
guardian excused it themselves, which is the case this entry was filed about),
**late** (sent or not, depending on the school's `notify` setting), and
**absent with no receipt** — a message that should have gone and did not.
Only the last is a problem, and it looked exactly like the other three.

The second defect: `=== 'absent'` means a **late** row whose SMS genuinely was
sent was shown to the parent as **not** sent. The school sent the message and
the portal denied it. `RecordClassAttendanceAction::maybeNotify()` notifies on
`Late` too when the setting is `absent_and_late`.

`ResolveAttendanceNotificationStateAction` now returns `notified` /
`not_sent` / `not_applicable`, and reads the **same setting the sender reads**
so the portal's answer cannot drift from what the school actually does
(rule 11). Only `not_sent` is highlighted; the other two are quiet, because
they are not problems.

Walked in Chrome (2026-09-13), four rows for one child:

| Date | Status | Column |
|---|---|---|
| 2026-09-04 | present | Not applicable |
| 2026-09-03 | excused | Not applicable |
| 2026-09-02 | absent | **Not sent** |
| 2026-09-01 | absent | Sent |

Still true, and unchanged by this: the SMS **body** is not in the portal, and
local/staging sends are log + `sms_receipts` only.

### 18. Vite HMR blank Inertia pages on this VM

**Severity:** confusion for agents. Round 2/3 used `npm run build` + no `public/hot`. Not an app defect for production `public/build`.

### 19. Exam schedule form defaults wander

**Fixed** — the term and class dropdowns are scoped to the form's own selected
year, and the year defaults to the one being viewed, else the active one. The
lists spanned every year, which is why the defaults could land on another
year's term. Changing the year resets a term or class that no longer belongs to
it. See **Fixed on main**.

---

## P4 — Cosmetic / later

### 20. Create-year description in `useForm` but no field; unlabelled date inputs; copyright 2025 on login

**Fixed** — the description field exists (the controller had validated it since
the screen shipped), every date input on the years screen is labelled, and no
`2025` copyright remains in any view. See **Fixed on main**.

### 21. Report-card publish control defaulted to Term 2 while the table is Term 1

**Fixed** — generate and publish default to the term being viewed, else the
**active** term, rather than whichever term sorts first across all years. Same
defect family as #19. See **Fixed on main**.

### 22. Blade counters (28 students / 3 teachers) are not “Grade 5 A”

**Fixed (2026-09-14) — and it was not the cosmetic entry it was filed as.**

**Evidence:** Round 2 step 1. Admin still lands on Blade dashboard (allowed, Round 3).

Filed as a presentation complaint: the supervisor dashboard shows
institute-wide counters where the tester expected class context. Auditing the
screen found the numbers were also **wrong**.

`Student::count()` and `Teacher::count()` count every row. A tile headed
**Students** included pupils who had `graduated`, `transferred` or `withdrawn`,
and applicants who had never started; one headed **Teachers** included staff
whose employment had `terminated`. "28 students" was the number of student
*records*, which is not a fact about the school. `ComposeCatalogReportsAction`
(§33) made the same call under a comment reading *"this is the roll of the
institute"* — the label and the query disagreed **in writing**.

The fix names the distinction rather than filtering everywhere, because there
are genuinely two questions:

| Question | Who asks it |
|---|---|
| `onTheRoll()` / `teaching()` | the supervisor dashboard, the §33 catalog totals |
| `everEnrolled()` / `everEmployed()` | the homepage's *students taught* — a cumulative claim, where today's roll would **undersell** every finished cohort |

The ambiguous `CountStudentsAction::execute()` is gone, so no caller can ask the
vague question by accident. The tiles are now headed **Students on the roll**
and **Teachers on staff**, since "Students" is true of either number.

**Found while auditing the same screen, and fixed with it:** the super-admin
dashboard computed **eleven** values no view reads — verified against the only
view that renders it, which reads twelve `$stats` keys and one `$metrics` key,
with no partial, include or dynamic lookup. One of them was
`getOverallAttendanceRate()`, returning a hardcoded **85.5** with
`// Placeholder` beside it: an invented figure in a variable called
`attendance_rate`, waiting for somebody to wire it up in good faith. It has
never been displayed — luck, not design — and it is now deleted rather than
left lying there. `getStudentGrowthMetrics()` went with it and was wrong in its
own right: `whereMonth` with no `whereYear` compares that month across *every*
year.

Removing them also took four cross-domain `Models\*` imports out of
`Portal\DashboardController` (rule 3): it now asks People's own counting
actions instead. Both architecture baselines shrank accordingly.

**Still an IA decision, and deliberately not taken here:** whether this screen
should be scoped to a class at all. A wrong number is a defect; the choice of
which correct number to show is not.

Walked in Chromium (2026-09-14) as `supervisor@akuru.edu.mv`, against a
database of 15 students and 3 teachers with one graduate and one terminated
teacher planted: the tiles read **14** and **2**. The admin dashboard was
walked too — every remaining tile renders, no console or server error.

### 23. `guardian_student` carries a verification gate that gates nothing

**Severity:** confusion, latent trap. Found by the 2026-09-13 §37 audit.

Migration `2026_08_25_000031_s1a7_guardian_student_policy` added
`verification_status` (default `'unverified'`), `consent_status` (default
`'unknown'`), `verified_at`, `created_by` and `notes` to `guardian_student`.
**Nothing in the codebase reads or writes any of them.** `grep` finds exactly
two hits, both inside that migration.

So every guardian↔student link in the database says `unverified` forever, while
`/portal/children` lists the child regardless. The column looks like an access
control and is not one — which is worse than no column, because a schema reader
concludes guardian links are verified-gated when they are not.

**Why this is recorded rather than fixed.** Enforcing the flag today would hide
**every** child from **every** parent, since nothing can mark a link verified —
a regression, not a fix. Building the verification workflow instead would be
inventing policy: `docs/S1_SPEC.md` defines this pivot as `relationship`,
`is_primary`, `can_pickup`, `financial_responsible` and asks only that
"guardians see their own children only", which `ListGuardianChildrenAction`
already does correctly. No spec asks for verification at all.

**The owner's call, two ways out:** wire the flag up (admin control to verify a
link + the portal filtering on it), or drop the three unread columns in a
cleanup deploy. Either is fine; leaving a control-shaped column that controls
nothing is the thing to avoid.

> **Correction (2026-09-13, same day).** The sentence "No spec asks for
> verification at all" above is **wrong**, and the paragraph it sits in is
> wrong with it. **SPEC §9 "Parent-Child Relationship" asks for every one of
> these fields by name** — "Consent status · Verification status · `verified_at`
> · `created_by` · Notes" — so migration `1A.7` was implementing §9, not adding
> speculative scaffolding. That was found by reading §9 in the §6–§9 sweep,
> one section later. `docs/S1_SPEC.md` is a phase build-spec and does not
> override the product spec; consulting only the former was the mistake.
>
> The **observation** still held: nothing wrote those columns. Two further
> things turned out to be true that this entry did not know, both now fixed:
>
> - `withPivot` declared four of the nine fields, so the other five **never
>   came back through the relation at all**. A write landed in the database and
>   read back as NULL — they were unreadable as well as unwritten.
> - §9 also names **Sponsor** among its relationship types, and the enum had
>   every one but that.
>
> **Fixed** — `RecordGuardianLinkPolicyAction`, the widened `withPivot` on both
> sides, `created_by` written at attach time, and a control on the student's
> Guardians tab. What this entry got right and the fix keeps: **verification is
> a record, not a gate.** `/portal/children` still filters on nothing but the
> guardian's own links. Turning it into an access rule remains the owner's
> call, and still needs a backfill for every link created before this.

---

### 24. Enrollment confirmation is sent by the code that confirms it, which is the one example §41 gives of what not to do

**Severity:** confusion, structural. Found by the 2026-09-13 §41 audit.

SPEC §41 "Domain Boundary Enforcement" does not leave this to interpretation —
it is the worked example the section ends on:

> Cross-domain side effects must use events/listeners.
>
> - Enrollment should dispatch an enrollment-created event.
> - Notifications should listen to that event.
> - **Enrollment code must not directly call notification implementation
>   classes.**

The codebase does this well almost everywhere else. Eight domain events exist
(`PaymentConfirmed`, `PaymentRefunded`, `InvoiceIssued`, `InvoiceReminderDue`,
`StudentMarkedAbsent`, `BehaviorRecordLogged`, `ExamResultsPublished`,
`ReportCardsPublished`) and the Notifications domain listens to five of them
through proper listeners. The pattern is understood and in use.

**Enrollment is the exception, and it is the one §41 names.** There is no
enrollment event of any kind — `EnrollmentCreated`, `EnrollmentConfirmed`,
nothing. Instead:

- `Finance/Services/Payment/PaymentService.php` builds and sends four
  notifications itself: `Mail::to(...)->send(new EnrollmentConfirmedMail(...))`,
  `AdminNewEnrollmentMail`, a confirmation SMS, and an admin notice — reaching
  into `App\Mail\*` and `App\Domains\Identity\Models\User` directly.
- `Admissions/Http/Controllers/CourseRegistrationController.php:1314` queues
  `FreeEnrollmentConfirmedMail` **from a controller**, which is rule 5 as well.

So the first item on §40's list of notifications — "Enrollment confirmation" —
is wired the one way §41 says not to wire it, and the recipient logic (verified
email contact, then unverified, then `user->email`) lives in the payment
service where nobody looking for notification behaviour would find it.

**Why this is recorded rather than fixed.** It is a real refactor with real
behavioural edges, not a tidy-up: `Mail::send` (synchronous) and `Mail::queue`
are both in use and the difference is observable; the sends sit inside payment
confirmation, so moving them changes what happens when a send throws relative
to the payment transaction; and rule 12 puts BML webhook confirmation on this
path. Doing it properly means an `EnrollmentConfirmed` event, listeners in
Notifications, and a decision about queueing and failure handling for each of
the four messages — its own slice, with its own walk.

**Not a safety issue.** The live-SMS kill-switch is unaffected: every SMS on
this path goes through `SmsSenderInterface`, which binds to `LogSmsSender`
unless `APP_ENV=production` and `SMS_LIVE` are both explicitly set.

> **Mostly fixed (2026-09-13, same day) — and it was worse than this entry
> knew.** Reading `RecordManualPaymentAction` turned up a user-visible cost the
> structural complaint above does not mention. Its own docblock promises:
>
> > the payment is created confirmed with provider "manual" and flows through
> > the **SAME PaymentConfirmed listeners** as a webhook confirmation — one
> > money→access path for every kind of money.
>
> Access kept that promise. **Telling the family did not**, because the four
> notices were not listeners at all. So **paying by card got an email and an
> SMS; handing cash over at the office got silence** — the enrollment activated
> either way and nobody told the parent. It landed on exactly the families
> least likely to be watching an account online.
>
> **Fixed** — `Finance\Listeners\SendPaymentConfirmationNotices` now handles
> `PaymentConfirmed`, and `PaymentService` no longer knows any notification
> channel exists (its `SmsSenderInterface` dependency is gone). Three further
> things came with it:
>
> - **The sends are deferred to after commit.** `PaymentConfirmed` fires
>   *inside* the payment transaction so a failed activation rolls back with the
>   money. Notifications must not share that: an SMTP timeout is not a reason
>   to un-confirm a payment, and a mail send inside a transaction held it open
>   for the length of a network call.
> - **A rule 3 violation went with it.** The admin SMS resolved recipients with
>   `Identity\Models\User::role(...)` from inside a Finance service. That is now
>   `Identity\Actions\ListAdminMobileNumbersAction`.
> - **A message defect this made visible was fixed rather than shipped.** The
>   course name came from `$payment->items`, which only legacy consolidated
>   payments have, so engine and manual payments read *"Payment received for
>   Yusuf **– .** Pending admin approval."* Nobody had seen it because those
>   payments sent no SMS at all.
>
> **What is left, and why.** §41 says *Notifications* should own the listener,
> and it still lives in Finance. Two things block the move and neither was
> worth forcing on the way past: `PaymentConfirmed` carries an Eloquent
> `Payment` (the house pattern for cross-domain events is scalars — compare
> `InvoiceIssued`), and both Mailables take a `Payment` and render from it, so a
> Notifications listener would have to import `Finance\Models\Payment` — the
> exact rule 3 violation §41 is about. Reshaping the event would touch three
> existing listeners on the money→access path. **The remaining step is to give
> the Mailables scalars and move the listener across.**
>
> > **Done (2026-09-13), and it uncovered a third defect.** The Mailables now
> > take `Finance\DTOs\PaymentNoticeData`, so
> > `Notifications\Listeners\SendPaymentConfirmationNotices` sends and Finance
> > only describes: `RaisePaymentNoticeReady` builds the DTO and raises
> > `PaymentNoticeReady` after commit. Nothing in Notifications names a Finance
> > model — only its Event and its DTO, both of which rule 3 permits. §41's
> > worked example is now arranged as written, and `PaymentConfirmed` keeps its
> > strict in-transaction money→access semantics untouched.
> >
> > Giving "which courses is this payment for?" a single owner is what exposed
> > the third defect. All three notices asked `$payment->items`, and **only the
> > legacy consolidated payments have item rows.** Engine checkout payments
> > point at the enrollment; manual payments carry `course_id`. So for both, the
> > confirmation email rendered a **Course/Status table with a heading and no
> > rows** under the words "Payment Received", and the admin email's subject read
> > "— Unknown course". That was live for **every engine checkout payer**, not
> > only the manual ones this entry is about — it simply had not been noticed,
> > because the SMS half of it only became visible when manual payments started
> > sending at all. `BuildPaymentNoticeDataAction` resolves items → payable →
> > `course_id`, and the view no longer prints a table when there is nothing to
> > put in it.
> >
> > Two DB queries also came out of a queued Blade template
> > (`admin-new-enrollment` was calling `$user->contacts()->where(...)` while
> > rendering).
> >
> > **Deliberately unchanged:** the admin SMS still fires only for payments with
> > item rows. That restriction was an accident of the old query rather than a
> > decision, but widening it starts sending admins an SMS for every engine
> > checkout, and who gets woken up is the owner's call. `hasItemisedCourses` on
> > the DTO holds the audience where it was and says what it is waiting for.
>
> **Also still open:** the free-enrollment path.
> `CourseRegistrationController:1314` still queues `FreeEnrollmentConfirmedMail`
> from a controller. That one needs the logic lifted out of a 1,300-line
> controller first (rule 5), which is its own job.
>
> > **Closed (2026-09-13).** `AnnounceFreeEnrollmentsAction` now holds the rule
> > and raises `Admissions\Events\FreeEnrollmentConfirmed` after commit;
> > `Notifications\Listeners\SendFreeEnrollmentNotices` sends. Both Mailables
> > take `Admissions\DTOs\FreeEnrollmentNoticeData`, so nothing outside
> > Admissions holds an enrollment or a user model to send a notice.
> >
> > The controller lost 64 lines and now calls one Action. **It names no
> > Mailable anywhere**, and a test asserts that, because "just one more
> > notification where the enrollment is created" is exactly how this comes
> > back.
> >
> > Which enrollments qualify was the part worth moving, not the mail: an
> > enrollment is announced when `payment_status` is `not_required`, and a paid
> > one waits for its webhook (rule 12 — never announce before confirmation).
> > That rule was sitting in a controller as an inline `array_filter`. A test
> > now pins the silence for a paid enrollment, which is the half that would
> > fail quietly.
> >
> > Two more database queries came out of a queued Blade template, the same
> > defect as the paid admin mail, and three `catch (\Throwable) {}` blocks
> > commented "non-critical" became logged warnings — a family that never
> > received their confirmation used to leave no trace at all.
> >
> > **With this, every enrollment notice in the system is a listener.** §41's
> > worked example is closed.

### 27. A halaqa kept generating work for a child who had left

**Fixed (2026-09-14) — found by audit, never filed.** Third pass of the same
sweep, and the third status column that day whose non-default values were
unreachable.

`hifz_enrollments.status` is `active` / `paused` / `completed` / `transferred`
— **no value means "left the school"** — and `HifzEnrollmentController` has
`index`, `create` and `store` and nothing else, with `store` not even accepting
a status. So an enrolment reads `active` for ever.

Withdrawing a pupil left `HifzSessionService` creating a session record for
them **every day a halaqa met**, and the dean's *active students* card counting
them indefinitely.

The school's roll does move (#25), so it is now asked where work is generated
and where people are counted. `Student::scopeOnTheRoll()` is the single
definition of "still a pupil"; `ListStudentIdsOnTheRollAction` is its bulk form;
Hifz asks through People's action rather than importing its model (rule 3).

**Left deliberately:** the enrolment row itself. See the owner item below.

See STATUS §5dp.

### 26. Employment could not be ended anywhere in the product

**Fixed (2026-09-14) — found by audit, never filed.** The teacher half of #25,
chased on the same reasoning, and larger than the cascade it was looking for.

`staff_profiles.status` and `teachers.status` are two records of the same fact
and nothing reconciled them — but the real finding is that **no screen changed
either of them.** `teachers.status` is written once at creation, always
`'active'`; and `people.staff.update` exists, is routed and validates a status,
while **no form in the application posted to it**. Found only by trying to end
an employment in a browser and looking for the control.

So every staff status was `active` for ever, which made **four**
`where('status', 'active')` filters inert: `ListActiveTeachersAction`, the
meeting-slot picker, the teacher-contact list, and the staff counter from #22.
Correct code guarding a column that could not move.

**This also corrects #22's own claim** that the Teachers tile "included staff
whose employment had ended" — true of the query, false of the data. The count
is right either way; the justification was overstated, and this is what makes
it true.

Shipped: `SyncTeacherRowStatusAction` (only `ended` deactivates; `on_leave`
deliberately does not, and that is a test); the **missing employment form** on
the staff profile page, which the controller had been feeding
`employmentTypes` and `statuses` for all along; and
`ListClassTeacherOptionsAction` split into `everyone()` for naming and
`assignable($keep)` for choosing, where `$keep` stops a `<select>` that lacks
its own current value from silently clearing an assignment on the next save.

See STATUS §5do.

### 25. A pupil who left the school stayed on the class register

**Fixed (2026-09-14) — found by audit, never filed.** It is numbered here so
the record exists, not because anybody reported it.

`students.status` and `class_student.status` record the same fact and nothing
reconciled them. `PromoteStudentsAction` moves both together at the end of a
year (S1_SPEC §121), but the mid-year path had no equivalent: marking a child
**withdrawn** in the student directory left their roster row `active`, and
`SaveStudentAction` only closes a placement when the *class* changes.

**Severity: harm, not cosmetic.** The register grid defaults every row to
**present**, so a withdrawn pupil left on it is recorded as attending lessons
they were not at. Marking them absent instead sends their guardian an absence
SMS about a school the family has left. Seventeen readers key off
`class_student.status` and none consults the pupil's standing, so the same
child stayed on the homework list, the timetable and the meeting-slot list —
and nobody could see the disagreement, because no screen shows a roster row's
pupil status beside it.

`ChangeStudentStatusAction` now raises `StudentStatusChanged` after its
transaction commits and Academics closes the placement in its own listener —
the roster is Academics' table, so a cascade inside the People action would be
People writing another domain's rows (rule 3).

The three departure statuses (`graduated`, `transferred`, `withdrawn`) are
exactly the three the promotion path already pairs with closing a placement, so
no policy is invented. **`inactive` is deliberately excluded**, and that
exclusion is one of the tests: it records a pupil who has stopped attending
without leaving, which is the case where a school needs them on the register in
order to chase it.

`left_at` takes the effective date the office gave rather than today's, and
rows already closed are not touched. **No backfill** — see STATUS §5dn.

---

### 28. A teacher cannot open the teacher review queue

**Open — needs a decision, see `OWNER_ACTIONS` item 16. Found 2026-09-15 by
walking the loop.**

`/catalog/reviews` renders a page titled **"Teacher review"** and answers six of
the thirteen abilities SPEC §36 gives the *Teacher / Instructor / Reviewer*:
view pending submissions, open student submissions, give score, give written
feedback, mark passed/failed, request resubmission. It is gated on
`courses.manage`, and the `teacher` role does not hold it — `admin`,
`headmaster`, `supervisor`, `super_admin` and `course_creator` do.

So a teacher can set written work through a `teacher_marked` activity and has
no screen on which it arrives. The nav shows them the link (it is ungated, see
#11), and it answers 403.

**Why this is not a one-line fix.** `courses.manage` is the *authoring*
permission: courses, lessons, questions, offerings, the glossary. Granting it
to teachers hands over the whole catalog to mark one essay. The narrow
alternative — a `courses.review` permission — runs into the queue being
school-wide with nothing to narrow it by: **`course_instructor` is a table with
no reader and no writer anywhere in the application**, and zero rows, so "the
submissions on my own courses" cannot be expressed today. SPEC §36's first
line, *view assigned offerings*, has the same nothing underneath it.

Either every teacher sees every pupil's work, or course-instructor assignment
gets built first. That is the owner's call and it is a disclosure decision, the
same family as item 12 in `OWNER_ACTIONS`.

The loop itself works, end to end, and is now walked and tested — see STATUS
§5dz. This is the one step of that walk that fails, and it fails on purpose.

---

### 29. An absence the family reported in the morning was still recorded as unexcused

**Fixed (2026-09-15) — found by walking the loop in the ordinary order.**

`ApproveAbsenceNoteAction` excuses the register by flipping rows it finds:
"matching absent rows → excused". That handles one order — the register is
filled, the child is marked absent, the note arrives afterwards — and it is the
order every test used.

**The ordinary order is the other one.** A family tells the school at 7am that
the child is ill, the office approves it at 8, and a teacher fills the 9am
register. The approval ran against **no attendance rows at all**, and the
absent mark written an hour later carried no note.

**Severity: harm, and there was no way back from it.**

- The guardian gets an absence SMS about the absence they had just reported.
  The walk shows it on the family's own screen: the row reads `ABSENT` and the
  notified column reads **Sent**.
- The child joins `unexcused()`, the chronic-absence list.
- **The office cannot correct it.** A teacher marking the row `excused` is
  refused by `RecordClassAttendanceAction::guardExcused` (#15, correctly — an
  excusal must carry its note), and re-approving the note throws *"This note is
  already approved."* There is no third route.

The rule now lives in the writer beside its mirror image, for the reason
`guardExcused` already gives: every route into `class_attendance` — both grids,
a CSV import, a future card reader — passes through that one writer. An absence
marked for a child who has an **approved, excusing** note for that date (and
that period, if the note names one) is recorded as excused with the note
attached. An excusal still comes only from an approved note.

`excusesAttendance()` moved onto `AbsenceNote` in the same slice: two actions
now ask the same question from opposite directions, and two copies of the E10c
type-or-boolean rule would be two answers to "is this absence excused"
(rule 11).

Five boundary tests ship with it, each asserting the SMS **still goes out**: a
note that does not excuse, a note still waiting for approval, yesterday's note,
another child's note, and a child who turned up after all. A rule that excuses
too much is worse than the defect it replaces — an excused row is silent in
three directions at once.

Walked end to end in a browser, three actors and the failing order:
`scripts/smoke/absence.mjs`, 13/13. STATUS §5ea.

---

### 30. A teacher could not see who had booked a meeting with them

**Fixed (2026-09-15) — found by walking the loop with the third actor.**

`meeting_slots.teacher_id` names the teacher, and the family is shown that name
on the slot before they book it. Both ends of a parent-teacher meeting knew
whose meeting it was, and **the teacher had nowhere to see one**:

- `/academics/meetings` — the office's screen, gated on `meetings.manage`,
  which `admin`, `headmaster`, `supervisor` and `super_admin` hold and
  `teacher` does not. **403.**
- `/teach/schedule` — course sessions, from Offerings. Nothing.
- `/portal/teacher` — the E1 teacher portal. Never mentioned meetings.

`/teach/meetings` now lists the signed-in teacher's own published slots and who
booked each one, with a CSV export. Read-only: generating, publishing and
cancelling stay with the office.

**Why this one was fixed and #28 was not.** They are the same shape — a teacher
locked out of their own work — and the difference is whether "mine" can be
expressed. Here it is a populated column on the row, so scoping is a `where`
clause and no permission is widened. For the review queue, `course_instructor`
has no rows and no writer, so somebody has to decide whether every teacher may
read every pupil's submitted work. That is a disclosure decision and it is
still item 16.

Drafts are excluded — a meeting that may never be offered is worse than
silence — and the list starts from **today** rather than now, so an 18:00
meeting is still on screen at 18:05.

Five tests, of which two are a pair: a teacher sees their own booking with the
family's name on it, and a second teacher's list is asserted **empty**, because
a page that leaks one row leaks the name on it. Walked in a browser with three
actors: `scripts/smoke/meetings.mjs`, 11/11. STATUS §5ec.

---

### 31. No learner could ever retake anything

**Fixed (2026-09-15) — found by running a smoke walk twice.**

The retake machinery was complete except for the one person it is about:

- authors set `retakes_allowed` and `retake_limit`; the authoring forms default
  to **3** for an activity and **2** for an assessment;
- `SaveActivityAttemptAction::assertRetakesAvailable` and
  `StartAssessmentAttemptAction::assertRetakesAvailable` enforce the policy;
- `nextNumber()` exists on both to number attempt two, and
  `SubmitActivityAttemptAction` creates it;
- the teacher's revision report says *"Retry the weak item when retakes remain;
  otherwise review with a teacher."*

**And no learner could start a second attempt.** Both players compute
`submitted = attempt && attempt.status !== 'in_progress'` and disable every
input and every button on it, permanently, with no control to begin again. A
pupil told by their teacher to try again had nothing to press.

Same shape as the status columns in STATUS §5dq and the same taxonomy as #25,
#26 and #27: **configured, enforced, reported on, and unreachable.** Nobody had
filed it and no test caught it, because every test asserted the server's
behaviour, which was correct all along.

`ResolveRetakeStateAction` now does the counting and both guards delegate to
it, so the player's "can I try again?" and the server's "may you?" are the same
question — a button offered and then refused is worse than no button. The
difference between the two policies is **preserved rather than unified**:
assessments have never had a `retakes_allowed` switch, and widening that would
change behaviour for existing assessments, which is a decision.

Activities need no new route — submitting already creates the next attempt.
Assessments get `POST learn/assessments/{assessment}/retake`, because an
attempt carries question snapshots that must be built when it starts.

**The browser walk caught a bug seven passing feature tests could not.** After
submitting a retake the button never came back: Inertia re-renders the same
component instance rather than remounting it, so the `retrying` flag survived
the round trip and the second go would have been the last one anybody could
take. Tests assert props; only a walk sees component state. `scripts/smoke/learn.mjs`
now runs three times consecutively without a re-seed, because it uses the fix it
found. STATUS §5ee.

---

### 32. A gate that refused and told nobody

**Fixed (2026-09-15) — found by walking the peer-review refusal.**

L7 (§12.2/§29) will not let an editor publish a **research** item until a peer
reviewer has recommended accept. The gate works: the server refuses, the item
stays `submitted`, and no review row is written.

**And the screen said nothing at all.** The editor clicks *Approve & publish*,
the POST 302s back with the validation error, and the page is unchanged — no
message, no explanation. From the editor's side the button simply does not
work, and nothing on the page suggests a peer reviewer is what is missing.

Same shape as the public layout that carried a flash and never rendered it
(STATUS §5dy): the message was set, carried, and dropped on the floor.

**Why `FormErrors` had not already caught it.** That component was added in
§5cn precisely for this family — *"28 of the 92 Inertia pages that submit a form
never referenced `errors` at all"* — but it takes `form.errors`, so the pages it
reached were the ones using `useForm`. **A bare `router.post` has no form
object**, and four controls on the library admin page use one: deciding a writer
application, deciding a payout, reviewing a submission, and assigning a
reviewer. The reviewer portal and the writer portal's submit-for-review are the
same.

The three Library pages now render `usePage().props.errors`. Assigning a
reviewer by an email nobody has, reviewing an item in the wrong state, or
publishing research with no accept on file all say so now.

**The wider gap is real and not closed here.** Roughly twenty other pages use
`router.post`/`put`/`delete` without referencing `errors` at all. They are
listed by `grep -rln 'router\.post' resources/js/Pages/` minus those mentioning
`errors`. Each needs the same one-line addition, but a refusal that nobody has
demonstrated is a guess, and this slice fixes the one with a proven gate behind
it. STATUS §5ef.

### 33. A refunded family could never enrol again

**Fixed (2026-09-15) — found by walking §1f's price override past a refund.**

`course_enrollments` has carried a unique key on
`(student_id, course_id, IFNULL(term_id, 0))` since the table was made. Both
enrolment Actions decide who is already enrolled by a **different** rule —
`whereNotIn('status', ['rejected', 'cancelled'])` — and insert a new row when
that finds nothing.

The guard's generosity is deliberate and right: somebody refunded, withdrawn or
rejected should be able to come back. The key does not share the opinion —
`cancelled` was not even among the statuses the original migration declared —
and neither does the soft-delete column, which keeps the row and the key with
it (SPEC §29). So the insert that follows that decision hits a duplicate key:

```
SQLSTATE[23000]: Integrity constraint violation: 1062
Duplicate entry '1-12-0' for key 'course_enrollments_student_course_term_unique'
```

**Three ordinary paths reach it**, each a white error screen:

- a family is refunded, the listener cancels the enrolment, they enrol again;
- the office removes a club member and adds them back next term;
- an application is rejected and the student applies again.

**The same shape as #25, #26, #27 and #31** — configured, enforced, reported on,
and unreachable — except that here the two halves are code and schema rather
than screen and server. Each reads as correct alone: the guard as generosity,
the key as hygiene.

`CreateOrReviveEnrollmentAction` now owns identity on the database's own key
(rule 11), and both Actions go through it: no row → create; a rejected,
cancelled or soft-deleted row → revive it; anything else → hand back what is
there untouched. A revived enrolment keeps its `progress_percentage`, because
`student_lesson_progress` is keyed by student and lesson and survives a
cancellation. No migration, so rule 9 is not engaged. ADR-035; STATUS §5ei.

**Why the tests did not have it.** `SelfLearningEnrollmentTest` has enrolled
twice in a row since Phase 4 — it never cancelled in between, because nothing
in a test suite refunds anybody first.

### 34. Nobody could record anything — the site blocked its own microphone

**Fixed (2026-09-15) — found by automating §1d, which had been written off as
needing a person with a mic.**

`SecurityHeaders` sent:

```
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

`()` is an **empty allowlist**, not a default — it denies every origin, this one
included, and no browser setting can override a response header. So
`getUserMedia({audio: true})` threw `NotAllowedError` on `/learn/pronounce` for
**every student, in every browser, on every deployment**.

That is the entire student surface of Arabic B (SPEC §51). The recorder, the
teacher's review queue, the training dataset, the export manifest and the model
shelf all sit downstream of a student recording one sound, and not one of them
could ever have received an attempt.

**And the error told them the wrong thing.** A policy block throws the same
`NotAllowedError` a visitor's own refusal throws, so it was classified as
"denied" and the student read *"Allow microphone access for this site and try
again"* — advice aimed at a setting that was never the problem, and which cannot
work.

**Why it survived.** It arrived with the **E8 student-pick-up slice**, which has
nothing to do with audio; closing camera and microphone reads as plain hygiene
in a diff about collecting children. And **no test named any of the security
headers**, so the value could change without a single failure. The existing
pronunciation feature tests post a file directly and never touch `getUserMedia`,
so they were green throughout.

Fixed to `microphone=(self)` — same-origin may *ask*, the browser still prompts,
the visitor may still refuse. Geolocation and camera stay shut. `recordingSupport()`
now reads `document.permissionsPolicy`/`featurePolicy` and reports a policy block
as its own reason, so the student is told it is the site's configuration and to
tell the school. Headers now have tests. ADR-036; STATUS §5ej.

**The sixth of this shape** — configured, enforced, reported on, and unreachable
(#25, #26, #27, #31, #33). This one is the largest: a whole SPEC section's
student-facing half, switched off by a one-line header in an unrelated slice.

### 35. The family portal scrolled sideways on a phone

**Fixed (2026-09-15) — found by automating §1g at a real phone profile.**

`/portal/home` overflowed its viewport by **123px** at 393px wide, in English
and in Dhivehi, for both the student and the parent account. A `flex` row
holding six portal links and an Export CSV button carried **no `flex-wrap`**,
so it could not break, ran to 492px, and pushed the whole page left-right under
the thumb.

**Why it survived.** The row *outside* it already wrapped, so the markup reads
as responsive at a glance; the unwrappable unit is one level in. And horizontal
overflow is invisible on a desktop — the window is simply wider than the
content, so nothing looks wrong to anyone who is not on a phone.

This is the screen a parent opens most. Fixed with one `flex-wrap`; a sweep of
37 screens across three roles at phone width found no other offender, and
`scripts/smoke/mobile.mjs` now measures twenty family-facing screens every run,
in both directions. STATUS §5em.

---

## Gates that behave well (recorded 2026-09-15)

Walked deliberately, because #32 showed a gate can refuse in total silence and
the only way to know which kind you have is to try it. Three refusals were
exercised in the Library track and **all three tell the person what happened**:

- **Payouts closed.** With `LIBRARY_PAYOUTS_ENABLED` off the writer portal does
  not offer a Request-payout button at all — it explains instead: *"Payouts open
  soon — earnings keep accruing and stay yours."* Told **before** pressing
  something, which is better than `OPERATOR_CHECKLIST` §1c asks for.
- **Empty wallet.** A reader buying a priced item with no balance is refused
  with *"Insufficient wallet balance"* and nothing half-completes.
- **Research without a peer review.** Now refuses *and says so* — that one was
  #32, and it is the reason the other two were checked rather than assumed.

Recorded here so the next person does not re-walk them, and so the contrast
with #32 is on the record: same codebase, same week, gates that explain and a
gate that did not.

---

## Explicitly not defects

- **Payroll off** — `PAYROLL_ENABLED=false` and settings `payroll.enabled` — by design (S5.6).
- ~~**Hifz frozen**~~ — the rule 7 freeze **ended with F5** (ADR-029, 2026-09-12): §2b is complete, the Qur'an dataset belongs to `Courses\Components\Quran`, and the four Blade controllers that read it are deleted. What remains of the Blade app (hub, five dashboards, programmes, enrolments, milestones, reports) touches no dataset model and is ordinary code again.
- **Qur’an dual-write off** — `QURAN_HALAQA_DUAL_WRITE` default false; A.4 is dual-write only.
- **BML Pay now untested** — sandbox, not a product lie by itself; Rule 12 still requires webhook confirmation.
- **UNVERIFIED slices** (S2.1 rooms, S2.4–S2.5, S2.9–S2.10, S3.5, S3.7, S4.4–S4.5, S5.*, 1A.2–2.5, Arabic A, Qur’an A) — absence of a walk is not a recorded functional bug.
- **SMS log-bind outside production** — intended (#86). Live HTTP only if `APP_ENV=production` **and** `SMS_LIVE` is explicit true.
- **`cursor/pilot-rewalk-063c`** — not merged; superseded by #79–#84 on `main` plus Round 3 notes **#93**. No open PR. Product overlap of picker / logout / seed contacts / class-teacher / periods / generate-today.

---

## Fixed on main (2026-08-26, PRs #86–#92, plus later)

These were open at the status audit (`c21630a`) and in Round 1/2 ranked lists. Round 3 re-walked them on a local merge; they are now on `main`. Kept here so the audit does not silently drop the record.

| Was | Fix | Evidence |
|---|---|---|
| People → Students search/CSV only; no create | **#95** `SaveStudentAction` + status via `ChangeStudentStatusAction`; guardian pivot; course-only nullables | `StudentDirectoryCrudTest`; browser: add child → roster picker |
| Term % blank; Weights JSON blob of zeros did not persist a scheme | **#96** numeric type percents from `default_weight`; redirect keeps `academic_year_id` | `GradingFoundationsTest` HTTP store; `WeightSchemePersistTest`; browser: Fatima 28.00 / E / rank 15 on HTML report card |
| Report cards HTML not PDF; ADR-012 still named Browsershot | **#97** ADR-012: HTML is the supported production output; PDF is a future binding swap | `ReportCardsTest`; report-cards page cites ADR-012 |
| SMS live Dhiraagu/HTTP in every environment | **#86** `LogSmsSender` unless `LiveSms::allowed()` | `NotificationsServiceProvider`; `SmsSafetyTest`; Round 3 Parent notified column |
| `DatabaseSeeder` not a school (0 students / 0 `teachers` / 0 years) | **#87** `PilotRehearsalSeeder` + `EnsureTeacherRowAction` | `SeededSchoolTest`; Round 3 `migrate:fresh --seed` |
| Teacher/parent Blade landing hid the loop (parent **Admin Dashboard**) | **#88** teacher → Today; parent **Parent Dashboard** | `RoleLandingTest`; Round 3 steps 2–3 |
| Term grades silent blank / UI called HTML a PDF | **#89** missing-weights banner; Download HTML | `TermGradesTest`; Round 3 step 5 |
| Fill grid names only (wrong-child) | **#90** Number + DOB; picker `identity_key` omits class | `ClassRegisterTest`, `ClassRosterPickerTest`; Round 3 step 2 |
| Roster picker two rows when numbers differ (PIL-01 vs blank) | **#99** `identity_key` omits student number; blank is not distinguishing; admin must choose explicitly | `ClassRosterPickerTest`; browser: Grade 5 A search Fatima, amber banner |
| Generate “Created 0”; duplicate year/class 500; invoice drafts-only + hardcoded dates | **#91** flash copy; unique year/class + form `errors.name`; list all statuses; term period action | `YearClassUniquenessTest`, `InvoiceGenerationTest`; Round 3 steps 1 and 6 |
| DoD = tests only | **#92** “walked in a browser” in `CLAUDE.md` / `.cursorrules` | docs |
| `/academics/gradebook` 404 | **#100** redirect to `exams.gradebook.index`; Inertia static GET href scan | `HardcodedInertiaPathsTest`; browser: `/en/academics/gradebook` lands on Gradebook |
| Grade 5 B class teacher stayed None; no update path | **#101** `AssignClassTeacherAction` + PUT on class show | `ClassTeacherAssignmentTest`; browser: Grade 5 B save teacher, reload |
| Seeder duplicate Extra year names | **#101** `firstOrCreate` by year name in seeders | `AcademicYearSeederIdempotencyTest` |
| 1A glossary tables missing | **#102** term bank + lesson attach + player definitions | `GlossaryTest`; browser: fatha on catalog, outline attach, player click |
| Event/elective registration unused | **#103** `EnforceSeatLimitAction` shared with 1B.2; waitlist / parent confirm / second round | `EventRegistrationTest`; browser walk on `/academics/events` and `/portal/events` |
| Class quizzes/assignments unused by engine | **#104** `assessments:verify-legacy-migration`; attach to class XOR course | `LegacyAssessmentMigrationTest`; class show Engine assessments + CSV |
| School exams and engine assessments in separate views | **#105** `GradeItemContract` + tagged providers; gradebook CSV includes item scores | `UnifiedGradebookTest`; browser: Grade 5 A exam + Letters Quiz + Recitation homework |
| Course certificates missing | **#106** templates, issue, public ULID verify (face only, no auth) | `CourseCertificateTest`; browser: AKU-2026-C9CAKP Fatima Yoosuf |
| Completion/performance reports missing | **#107** staff completions + portal performance CSV | `CourseCompletionReportTest`; browser: Unification representative course roster |

Round 1 vs Round 2 “still on the list after Round 2” for SMS, seeder, landings, fill-grid, generate/year/class/invoices is **obsolete** for those items. Round 3 remaining ranked list (`docs/PILOT_REHEARSAL.md`) is the current teacher-blocking set, minus uniqueness-as-500 (fixed) plus the form-visibility/seeder-dupe nuance.
