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

1. **Walk the app on `test.akuru.edu.mv`.** The core daily loop is now walked
   **locally** and works end to end (STATUS §5bu/§5bv) — that half is done, and
   it found a real defect. What remains is the *deployment*: nothing has been
   executed on the staging host, whose staff login is itself the standing P0
   below. A local walk cannot tell you the deploy script, the built assets, or
   the seeded database on that host are sound.
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

1. **Staging staff login** — seed passwords 302 back to login; no SSH from this environment. Blocks any judgement that `test.akuru.edu.mv` is a school.
1b. ~~**Nothing merged since 2026-08 has been walked in a browser**~~ — **partly closed 2026-09-10 (STATUS §5bu, §5bv).** The core daily loop is now walked **locally**, end to end, in Chromium: a teacher generates a register, fills it, marks a pupil absent and submits; the absence lands on the admin absence list; the homework and the absence both reach the family portal. That walk found a real defect (nested translation lines editable in neither language, fixed in #234). **Still open:** nothing has been walked on `test.akuru.edu.mv` itself, which is a separate question about the deployment rather than the code. See decision 1 above.
2. **AppShell nav IA** — **proposed, awaiting decision.** **105** wrapping `<Link href=` in `AppShell.jsx` — 83 when this line was written, 74 at the IA proposal, plus Glossary, admin Events, portal Event signup, Certificates, Completions, Performance, Home, Meetings, Overview. It grows with every slice that adds a screen, which is itself the argument. C3 extends `/catalog/reviews` (already linked). D1 adds Home. D2 adds Meetings. D3 adds Overview. Proposal in `docs/APPSHELL_NAV_IA.md` (PR #98): grouped by role and frequency. **Do not implement** until Accept / Accept with edits / Reject. The wrap is still live.
3. **Parent notified column shows — on excused rows** — column exists (#86); SMS body is not in the portal.
4. ~~Shared Add-term form on every year card~~ — **fixed** (#13).
5. ~~Exam schedule form defaults wander~~ — **fixed** (#19), along with the
   report-card publish default (#21).

---

## P0 — Harm (real people, real messages, real money)

### 1. Staging staff login does not work with documented seed passwords

**Severity:** harm-adjacent / blocked production judgement — the intended pilot host cannot be used; public 200 hides that.

**Evidence:** Round 1 step 0; Round 2 opening; Round 3 ranked #1; archive “Staging credential smoke (2026-08-23)”. `https://test.akuru.edu.mv/en/login` 302s back to login for `admin@akuru.edu.mv` / `password`. This environment cannot SSH (`docs/STAGING.md` webhook only).

**Still open.** (Former P0 SMS live-bind is **fixed** — see Fixed on main #86.)

---

## P1 — Wrong data (identity, grades, documents)

### 2. Term grades render blank unless a weight scheme is actually saved

**Fixed** — Weights form posts numeric type percents (seeded from `default_weight`, sum 100) and redirects with `academic_year_id` so the year scheme resolves. See **Fixed on main**.

### 3. “Report cards” are HTML with empty grades, not PDF

**Fixed as a renderer decision** — HTML is the supported production output (ADR-012 amended). Empty cells without weights is the Weights issue (P1 #2), not a PDF bug. See **Fixed on main**.

### 4. Roster picker can still show two rows for the same identity with different numbers

**Fixed** — `identity_key` is name + DOB + national ID. Student number (including blank) and class do not distinguish. Assign still requires an explicit `student_id`. See **Fixed on main**.

### 5. Unification matcher / staging collisions (historical, still a staging gate)

**Severity:** wrong data if `--backfill` is run on a messy DB. Staging verify last recorded **red** (four collisions, 12/13 guardian users missing after `users:clear-non-admin`).

**Evidence:** `STATUS_ARCHIVE.md` staging 2026-08-25 and TRACK A. Representative local gate green (ADR-021) is **not** staging green.

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

### 11. AppShell nav is unusable as navigation

**Severity:** confusion / blocked. **105** wrapping links as of 2026-09-12 (“50+” when this was written), duplicate “Report cards” / “Awards”. Logout **is** present (POST next to the name). `GET /logout` remains 405. Round 3 ranked #2. **Proposed, awaiting decision** — `docs/APPSHELL_NAV_IA.md` (PR #98). Shell unchanged until the owner confirms.

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
