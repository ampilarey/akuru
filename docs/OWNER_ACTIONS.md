# What only you can do

Everything here is blocked on server access, a credential, or a decision — none
of it can be done from an agent session. It is collected from `KNOWN_ISSUES.md`
(the "Decisions only the owner can make" section), `STATUS.md` and the
top-five list, which is why it has been easy to lose: **the same blocker was
written in three places and finished in none.**

Written 2026-09-14. The agent-buildable backlog is empty; what follows is the
whole of the remaining go-live path.

**How to use this:** work Part 1 top to bottom — each item unblocks the next.
Part 2 can be decided in any order, including "not yet". Record each outcome in
`STATUS.md`, because a gate whose evidence is not recorded has not run.

---

## Part 1 — Blocked on the server (in order)

### 1. Make staging staff login work  ← everything else waits on this

**The standing P0.** `https://test.akuru.edu.mv/en/login` 302s back to login for
`admin@akuru.edu.mv` / `password`. No SSH from an agent session, so this has
never been diagnosed on the host.

**Test both paths, not one.** Until today only the password symptom was
recorded. `OtpService` called a method the SMS interface did not declare, so
**OTP login could not have worked on staging either** — fixed in #366, but the
fix has never run there.

- [ ] Password login with the documented seed credentials
      (`docs/AUTHENTICATION_GUIDE.md`)
- [ ] OTP login — request a code, read it from the log if live SMS is off,
      enter it

**If password login fails and OTP works,** the fault is the seeded password
hash or the account state, not the login code. **If both fail,** look at
sessions/cookies on that host first.

**Why it blocks everything:** no judgement about the deployment is possible
until somebody can get in. Item 7 is the real prize and this is its door.

---

### 2. Set `BML_WEBHOOK_SECRET` — no payment confirms without it

The webhook **fails closed** by design: with no secret configured and no
explicit opt-out, it refuses every callback. That is the safe direction and it
means money does not move until you do this.

- [ ] Set `BML_WEBHOOK_SECRET` on the host
- [ ] **Confirm with BML** that they sign HMAC-`sha256` over the **raw body**
      under the `X-BML-Signature` header

The second line matters more than it looks. The implementation's assumption
about their scheme has never been checked against BML's own documentation. If
they sign something else — a canonical string, a different header, a different
digest — the signature check will reject every genuine callback and every
payment will sit pending.

**The chain below the secret is now proven** (STATUS §5dx): a correctly signed
callback, on a host where unsigned callbacks are refused, confirms the payment
**and** lets the student into the course they paid for. Before this, the
signature and the enrolment were tested separately and the join between them
was not.

That does **not** make the scheme above correct — if BML signs something else,
that test passes and your host still rejects every genuine callback. It means
the part after the signature check is sound, so the second checkbox above is
the one carrying the risk.

**Watch on the first real transaction** (#362): return-URL finalisation now
refuses a provider result that does not name the payment it is answering about.
If BML's get-transaction response carries no merchant reference, finalisation
will decline rather than confirm and payments will wait for the webhook. That
is correct under rule 12 — the webhook is the authority — but you should know
it is the new behaviour.

---

### 3. Run a queue worker

`QUEUE_CONNECTION=database` with nothing consuming the queue leaves report
cards permanently **draft** — the generate button appears to do nothing.

- [ ] `php artisan queue:work` (or `queue:listen`) running under a supervisor
- [ ] Confirm a generated report card reaches **Ready**

---

### 4. Verify the permissions table on the host

`RoleSeeder` was corrected, but a seeder fix cannot tell you what a database
already contains.

- [ ] `permissions` holds all **34** dotted names
- [ ] The six seeder-only roles exist

A missing permission does not error — it silently denies, which looks like a
broken screen rather than a misconfiguration.

---

### 5. Rotate the super-admin password

The seeded credentials are committed in `docs/AUTHENTICATION_GUIDE.md`. That is
fine for a synthetic host and not fine for anything else.

- [ ] Rotate before any real person has an account

---

### 6. Apply branch protection

`docs/BRANCH_PROTECTION.md` has the settings. Structurally impossible from an
agent session, and the merge discipline in CLAUDE.md assumes it is on.

- [ ] Required CI check before merge, no direct pushes to `main`

---

### 7. Walk the app on staging  ← the actual goal

The core daily loop is walked **locally**, end to end, and that walk found a
real defect. What has never been exercised is the **deployment**: the deploy
script, the built assets, the seeded database on that host.

`docs/OPERATOR_CHECKLIST.md` is the walk script, and its **section 0 is now one
command**:

```
php artisan db:seed --class=SmokeMarkerSeeder
SMOKE_BASE_URL=https://test.akuru.edu.mv node scripts/smoke/all.mjs
```

- [ ] Run it and record the summary in STATUS.md.

**This used to name one script.** Ten had accumulated and nine of them were
referenced nowhere an operator would look, so the item that gates the whole
go-live path was pointing at a tenth of the evidence available. `all.mjs` runs
the lot, exits non-zero if any fails, and prints the full output of failures
only.

What they cover: every screen loaded as six roles (265 routes, **zero runtime
or server errors** locally), each screen showing a planted row, a family seeing
their own child and nobody else's, a person creating records through the forms,
a stranger enrolling themselves, a student taking a lesson, a teacher marking
work a machine cannot mark, a family reporting an absence, a child being
collected, and a parent booking a meeting. A different answer on staging is a
deployment fault, and that is exactly the comparison nobody has been able to
make.

**Seven of the ten write data.** The runner refuses to start against a host
whose name does not look synthetic; `--read` runs the three that only look.
`test.akuru.edu.mv` is recognised as synthetic and runs everything.

---

## Part 2 — Decisions (any order, "not yet" is a legible answer)

### 8. AppShell navigation

~90 links across **eleven rows**, roughly the top quarter of a 1200px viewport,
on every page. Proposal in `docs/APPSHELL_NAV_IA.md`; the shell is untouched
until you choose.

**Accept / accept with edits / reject.**

### 9. Bidi alignment on Dhivehi and Arabic screens

An English sentence on an RTL page renders its full stop at the front
(`.No exams still in marks entry`). One CSS rule fixes it — but the same rule
left-aligns English text, and with ~87% of the UI still English that means
nearly every line on every RTL screen.

**Correct punctuation and left alignment, or wrong punctuation and right
alignment**, until translation catches up. Screenshots of both were captured
during the walk — decide from those, not from this description.

### 10. Deploy 3 cleanup

**Confirm or reject** `docs/migrations/s11-deploy-3-cleanup-proposal.md`.

### 11. E18 — what scans at the gate

The hardware decision. The column carries a `source` so a card reader is a
binding rather than a rewrite.

### 12. E19 — who may read a sensitive note

Health and welfare notes are readable by `super_admin` and `headmaster` only.
`admin` is **deliberately excluded**, because `RoleSeeder`'s blanket
`Permission::all()` would otherwise have granted every admin account access by
accident. Widening it is one line in a migration; narrowing it later is a
disclosure.

### 13. `guardian_student` verification flag

SPEC §9 asks for it and it is now written on attach — but nothing **reads** it,
so every link says `unverified` while `/portal/children` lists the child
regardless.

**Wire it up** (an admin control to verify a link, plus the portal filtering on
it, plus a backfill for every existing link) **or drop the columns.** Enforcing
it today without a backfill would hide every child from every parent.

### 14. The `resume` magic link — build it or delete it

**Rewritten the same day it was written.** This item used to ask you to accept
or narrow three security properties of `courses/register/resume?flow=<uuid>`:
that it is not single-use, that it survives in browser history, and that the
session it grants is full rather than scoped. That was a decision request about
a feature that **does not function**.

`registration_flows` has two readers and **no writer anywhere in the
application**. Nothing creates a flow, so the route always answers *"No active
registration found. Please start again from a course page."* There is nothing
to resume and nothing to secure.

The real question is which way to close the gap:

- **Build it** — persist the registration journey so a family can come back to
  a half-finished enrolment. Then the three properties above become live
  questions and this item comes back as it was written.
- **Delete it** — drop the route, the model and the table. It is the honest
  option if nobody wants resume, and it removes a security surface that exists
  only on paper.

Either way, `payments/return-missing` no longer offers the link (STATUS §5dr),
because it was sending families who may have just paid to a dead end. It now
tells them the webhook will confirm the payment without them, which is true.

### 15. What a hifz enrolment should say when a pupil leaves

`hifz_enrollments.status` is `active` / `paused` / `completed` / `transferred`.
**None of those means "left the Institute"**, and there is no screen that sets
any of them: the enrolment controller can create but not update.

Since #27 the consequences are contained — a pupil who leaves stops generating
halaqa work and stops being counted — but the row still reads `active`, which
is wrong on any report that reads it directly.

**Pick one:** reuse `transferred`, which today most naturally means moved to
another halaqa rather than gone from the school; **or** add a fifth value,
which is a one-line widening of a DB enum (additive, safe) plus the screen that
sets it. Either way it needs a way to end an enrolment, which does not exist.

Left undecided rather than guessed, because naming it wrongly is worse than the
gap: a report that says `transferred` when the family emigrated is a sentence
somebody will act on.

### 16. Who marks the work — and whose work they can see

**The one step of the review walk that fails.** `/catalog/reviews` is titled
"Teacher review" and answers six of the thirteen abilities SPEC §36 gives a
teacher: view pending submissions, open student submissions, give score, give
written feedback, mark passed/failed, request resubmission.

**The `teacher` role cannot open it.** It is gated on `courses.manage`, which
`admin`, `headmaster`, `supervisor`, `super_admin` and `course_creator` hold
and teachers do not. A teacher can set written work and has nowhere to receive
it; the nav offers them the link and it answers 403.

The loop itself is sound — a student hands work in, a marker scores it, the
student sees the mark and the sentence — and that is now walked in a browser
and pinned by a test (STATUS §5dz). Only the door is wrong.

**Pick one:**

- **Every teacher marks everything.** Add a `courses.review` permission, grant
  it to `teacher` alongside the five roles that already have `courses.manage`,
  and gate the three review routes on it. One migration, one afternoon. It
  means any teacher can read any pupil's submitted work, school-wide.
- **Teachers mark their own courses first.** Narrower and honest, but it cannot
  be built today: `course_instructor` is a table with **no reader and no writer
  anywhere in the application** and zero rows, so "my courses" has nothing
  behind it. SPEC §36's first line, *view assigned offerings*, is empty for the
  same reason. This option is the assignment screen plus the scoped query, and
  it is the bigger job.

Not guessed, because widening a permission is a disclosure and narrowing it
later does not un-disclose anything — the same reasoning as item 12.

**Do not** solve it by granting `courses.manage` to teachers. That is the
authoring permission: courses, lessons, questions, offerings, glossary.

---

## What is *not* on this list

The agent-buildable backlog is empty. Of the 28 numbered defects in
`KNOWN_ISSUES.md`, **twenty-one are fixed**, two are explicitly not defects
(`left_early`, Vite HMR), and the remainder are the decisions above — including
#28, added 2026-09-15, which is item 16. The
EduPage parity track was verified row by row on 2026-09-14: **all 22 rows have
their code**, and a test now pins that so the plan cannot drift into claiming
otherwise for an eighteenth time.

Both of the small code items are done: the taught-summary / plan-topic
confusion (#16) and the dashboard counters (#22), both on 2026-09-14.

**Read "the backlog is empty" carefully.** It means the *known* list is clear,
not that the code is. Both of those entries were filed as judgement calls about
intended behaviour and turned out to be defects on inspection, and #22 in
particular was filed as cosmetic while the numbers on the screen were wrong.
Every audit run this week has found something in code that had passing tests.

**Proof, the same day:** with the list empty, an audit turned up **#25** — a
pupil marked withdrawn stayed on the class register, where the grid defaults
every row to *present*, so they were recorded as attending lessons they were
not at and their guardian could be texted about absences from a school the
family had left. Nobody had filed it, and no test caught it.

Then **#26**, from following the same reasoning to staff: **no screen in the
product could end somebody's employment.** The update route existed and
validated a status; no form posted to it, and the teacher row's status was
written once at creation and never again. Four "still employed" filters were
guarding a column that could not move. That one was found by trying to do it in
a browser and looking for the button — not by reading the code, which looks
correct.

And then **#27**, the same shape a third time: a hifz enrolment could not end
either, so a withdrawn pupil kept being given halaqa work every day and kept
being counted. Three status columns in one afternoon whose non-default values
no code path could reach. That is now a pattern rather than three accidents, so
there is a test for it (`StatusValuesAreWritableTest`), and **it found sixteen
more values across nine other tables that nothing can write** — recorded in
STATUS §5dq rather than fixed, because several are a screen's worth of work and
some are decisions.

Two are worth your eye now, whatever you decide about the rest.

**An invoice cannot be cancelled.** It can be drafted, issued and paid, and
there is no way back.

**The Hifz dean dashboard's "Absent Today" card is permanently 0.** It reads
`hifz_session_records.attendance_status`, which nothing writes any more: F5
(ADR-029) moved the live halaqa register to the Qur'an component, which records
attendance somewhere else. Two other readers have the same problem. The fix is
the Qur'an **A.4b** switch, which is already waiting on you to confirm the
dual-write — so this is one more reason to do that, not a new job.

Item 15 below is the piece of the same pattern that only you can settle.

The right reading is: there is nothing left that somebody has already written
down — which is the point at which item 7 above, walking the real deployment,
becomes the only way to find the next thing.
