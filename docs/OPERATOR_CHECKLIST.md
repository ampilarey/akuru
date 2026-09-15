# Operator Checklist — post-build close-out

All codeable phases are merged as of 2026-08-28 (Phases 0–4, Library L1–L7,
Arabic B, Qur'an B, Phase 5 scaffold). Everything below is work only an
operator can do: browser walks with a real login, decisions, flags, devices,
and gated deploys. Work top-to-bottom; record outcomes in STATUS.md as you
go (a gate whose evidence is not recorded has not run).

All walks happen on https://test.akuru.edu.mv (synthetic data only — safe to
create/approve/refund freely). You need one account per role: admin,
a plain user (writer applicant), a second plain user (reviewer), a teacher
with a `teachers` row, and a parent/student pair for portal checks.

---

## 0. Run the automated walks first

**Do this before anything below.** Seventeen scripted walks drive real browsers
through the loops that matter — a stranger enrolling, a student taking a
lesson, a teacher marking work, a family reporting an absence, a child being
collected, a parent booking a meeting, somebody becoming a writer and getting
published, a student recording a sound for a teacher to judge, and the whole
thing at phone width — and they take about thirteen minutes against a host,
unattended.

```
php artisan db:seed --class=SmokeMarkerSeeder     # plants the markers they read
node scripts/smoke/all.mjs                        # every walk, one summary
```

Point it at the deployment with `SMOKE_BASE_URL`:

```
SMOKE_BASE_URL=https://test.akuru.edu.mv node scripts/smoke/all.mjs
```

It exits non-zero if any walk fails and prints the full output of the failures
only, so a green run is one screen and a red one explains itself.

**Thirteen of the seventeen write data** — they submit absence notes, request
that children be collected, book meetings, enrol people and hand in recordings
for a teacher to judge. Against a host whose name does not look synthetic the
runner refuses to start and tells you how to override. `--read` runs only the
four that look without touching anything (`page-errors`, `sweep`, `own-data`,
`mobile`), which is the safe choice against anything with real families on it.

`node scripts/smoke/all.mjs learn review` runs named walks; `--write` runs the
thirteen that change data.

**What a clean run does and does not prove.** It proves the deploy script, the
built assets and the seeded database on that host behave like the local ones —
which is the comparison `OWNER_ACTIONS` item 7 exists to make and that nobody
has been able to make yet. It does **not** replace section 1: **§1a, §1b and §1c are now
automated** by the `library`, `peer-review` and `earnings` walks — applying,
approving, the role grant, drafting, a changes-requested round trip,
publication, the research gate refusing and then being satisfied, a wallet sale
reaching the writer at the 70/30 split, and the payouts gate explaining itself.
**§1f is automated too, all four lines** — a manual payment activating an
enrolment with no gateway involved, a refund to wallet, both of its
consequences, and an offering price override that a family can see, including
`0` behaving as free. **§1d is automated too** — recording through a synthetic
microphone, the teacher’s verdict, the training sample, the export manifest and
both role guards. **§1e is automated too**, and building the way in was what
it took — see below. **§1g is partly automated** — the PWA preconditions, RTL
and Thaana, and twenty family screens checked for sideways scroll at phone
width; a real voice through a real microphone and the install prompt itself
stay a person's. Only the device work (§4) is fully by hand now.
A walk that goes green first time deserves more suspicion than one that does
not (STATUS §5eb).

---

## 1. Browser walks (definition of done — §57)

CI is green everywhere, but the DoD requires each surface walked by a person
in a browser. Tick each line only when the full loop worked by hand.

### 1a. Library writer portal (L5)

**Automated as of 2026-09-15** — `scripts/smoke/library.mjs` walks every line
below, 14/14, including the role grant and the changes-requested round trip.
Run section 0 and these are covered; tick them from its output rather than by
hand. They stay written out because a walk proves the path works, not that the
screens read well to a person, and because the walk does not cover §1b or §1c.

- [ ] As a plain user, open `/write` → apply as writer (agreement checkbox
      required).
- [ ] As admin, open `/admin/library` → Applications queue → approve.
      Expect: user gains the `writer` role; a writer profile exists.
- [ ] As the writer, `/write` → create a draft item (article), edit it,
      submit for review.
- [ ] As admin → Submissions queue → request changes with a comment.
      Expect: writer sees the comment in their trail and can edit again.
- [ ] Resubmit → approve. Expect: item published; visible on the public
      library at `/library`.

### 1b. Research peer review (L7)

**Automated as of 2026-09-15** — `scripts/smoke/peer-review.mjs` walks every
line below, 12/12, including the refusal and the matching success that proves
the gate can be satisfied.

- [ ] As the writer, create a **research** item with a citations block →
      submit.
- [ ] As admin, try to approve immediately. Expect: refusal — "needs a peer
      reviewer accept".
- [ ] Assign a reviewer by email (second plain user) from the Submissions
      queue. Expect: that user gains the `reviewer` role.
- [ ] As the reviewer, open `/review` → recommend **revise** with a comment.
      Expect: comment appears in the writer's trail (reviewer identity not
      shown to writer).
- [ ] Re-review → **accept**; as admin approve. Expect: published; citations
      render on the public item page.

### 1c. Writer earnings & payouts (L6)

**Automated as of 2026-09-15** — `scripts/smoke/earnings.mjs` walks every line
below, 13/13, buying with the **wallet** (no card payment can confirm anywhere
while `BML_WEBHOOK_SECRET` is unset) and proving the split by measuring the
writer's pending balance before and after: `280 → 350`, exactly +70 on a 100
sale.

- [ ] Buy a priced library item as a reader (BML sandbox or wallet).
      Expect: a writer earning appears on the writer's `/write` earnings
      card with the right split (default 70/30 unless the item or profile
      overrides).
- [ ] As the writer, save bank details, then request a payout.
      Expect (flag off): a clear "payouts not yet enabled" gate message —
      see §2a below. Do NOT enable the flag just to test; the request path
      is covered by Pest.
- [ ] As admin, check `/admin/library` payouts queue renders and the
      earnings CSV downloads.

### 1d. Pronunciation practice (Arabic B — AI off)

**Automated as of 2026-09-15** — `scripts/smoke/pronounce.mjs` walks every line
below, 17/17, including both role guards. It had been left for a person on the
grounds that it "needs a mic"; it does not. Chromium can hand `getUserMedia` a
synthetic audio stream (`--use-fake-device-for-media-stream`) and grant the
permission without asking, so the page calls the real `MediaRecorder` and posts
a real `.webm` the server cannot tell from a person's.

**Walking it found that nobody could record at all.** `Permissions-Policy:
microphone=()` is an empty allowlist — it denies every origin, this one
included — so the Record button threw on every browser for every student, and
the message told them to change a browser setting that cannot override a
response header. Fixed to `microphone=(self)`; see KNOWN_ISSUES #34 and
STATUS §5ej. A person should still confirm a **real voice through a real
microphone** records audibly, which is §1g's line, not this one.

- [ ] As a student user, open `/learn/pronounce` → allow mic → record a
      letter/haraka attempt → submit. Expect: confirmation; no AI feedback
      (flag is off).
- [ ] As a teacher, open `/teach/pronunciation`. Expect: the attempt is in
      the queue; give a verdict with the verified letter+haraka.
- [ ] As admin, open `/admin/pronunciation`. Expect: the verdict shows as a
      pending training sample; approve it; dataset stats count it; export
      writes a manifest; the model shelf renders (empty is fine — no model
      yet).
- [ ] Confirm role guards: the teacher page 403s for a plain user; the admin
      page 403s without `pronunciation.manage`.

### 1e. Qur'an recitation queue (Qur'an B — AI off)

**Automated as of 2026-09-15** — `scripts/smoke/recite.mjs`, 13/13.

**This line could not be walked at all until now**, and not because of a bug.
`SubmitRecitationAction` had existed since F3 — validated, tested, engine-keyed
— and was reachable from six test files and from nothing a student could press.
So `quran_recitation_submissions` was always empty and the queue this line asks
you to open had nothing in it to review. F4 recorded the deferral honestly
("needs private media upload + authenticated streaming"); both shipped
afterwards and nothing went back for it. SPEC §52.9's manual recording mode now
has its route, and the walk covers the whole loop: record, replay, submit, the
teacher hears it through the authorizing audio route, marks a mistake and an
outcome, and the student sees the verdict. STATUS §5el.

- [ ] As a teacher, open the recitation review queue. Expect: byte-identical
      to the pre-AI flow — no AI column, submissions review normally.
      (The AI opinion column only appears when the flag in §2b is on.)

### 1f. Money surfaces (Phase 4 close-out)

**Automated as of 2026-09-15** — `scripts/smoke/money.mjs` walks all four
lines, 21/21: a manual payment activating an enrolment **with no gateway
involved**, a refund to wallet, and both consequences measured rather than
assumed — the enrolment reads *Cancelled / Refunded* and the family's wallet
goes `500 → 750`. The price override is set through the admin form and read off
the family's own catalog row, and `0` is proved free twice over — no price on
the listing, and an enrolment that opens with no payment behind it. That last
step is what found #33 (a refunded family could never enrol again).

- [ ] Admin payments screen: refund a confirmed payment to **wallet**;
      expect enrollment cancelled, discount released, wallet credited,
      `refunded` filter shows it.
- [ ] Enrollment page: record a **manual** payment; expect activation
      without BML.
- [ ] Payments CSV export downloads with refunded totals.
- [ ] Offering **price override**: set one on an offering; public course
      listing shows the override; `0` behaves as free.

### 1g. Mobile shell smoke (Phase 5 — browser part only)

**Partly automated as of 2026-09-15** — `scripts/smoke/mobile.mjs`, 11/11, runs
the app at a real phone profile (Pixel 5, 393px) and checks what a person on a
phone notices first: the manifest is served and its icons load, the service
worker reaches `activated`, the precached offline page is really there, Dhivehi
renders right-to-left in Thaana with fonts resolved, the recorder is reachable
and tappable, and **twenty family-facing screens do not scroll sideways** — in
both directions, because RTL overflows the other way.

**That last check found a real one.** `/portal/home` — the screen parents open
most — overflowed by **123px** on a 393px phone, in both languages: a `flex`
row of six links and a CSV button with no `flex-wrap`, so it could not break
and pushed the whole page sideways under the thumb. Fixed; a sweep of 37
screens across three roles found no others. STATUS §5em.

**Two lines stay a person's job, and no walk should claim them:** whether a
**real voice through a real microphone** records audibly, and whether the
install prompt actually appears (browser engagement heuristics, not something
to assert).

- [ ] Open test.akuru.edu.mv on a phone browser: RTL/Thaana rendering, the
      pronunciation recorder works with the phone mic, PWA install prompt
      appears. (Native shell walk is §4.)

---

## 2. Feature-flag decisions

### 2a. `LIBRARY_PAYOUTS_ENABLED` (Library §9.4)

Off until the payout tax/withholding treatment is decided (business + MIRA
question, not code). When decided:

- [ ] Record the decision (rate, who withholds, invoice/receipt format) in
      STATUS.md and as an ADR if it changes money flow.
- [ ] Set the env var on the target deployment; walk §1c's payout request
      end-to-end (request → admin marks paid → earnings move to `paid`).

### 2b. `AI_PRONUNCIATION_ENABLED` (SPEC §51.17)

Off until BOTH:

- [ ] A model is trained from **real approved samples** (`ai/pronunciation/
      README.md` — export manifest → `train.py` → register + activate the
      version on the model shelf). Synthetic/test samples don't count.
- [ ] §51.17 consent handling is confirmed (students/guardians consent to
      audio being used for training; privacy backlog item).

Then flip the flag on test only, and walk §1d expecting inline AI feedback
and confident-correct attempts downgrading to spot-check, and §1e expecting
the AI opinion column beside submissions.

---

## 3. F5 — Hifz retirement (ADR-025 gate) — **CLOSED, nothing to do here**

**This section is done and is kept only so nobody re-opens it.** It described a
gate that was met and a deletion slice that has already shipped; working
top-to-bottom through this document, an operator would otherwise spend a day on
finished work — and the last line would have them *request a PR that merged on
2026-09-12*.

- [x] **ADR-025 verification walks** — run. The F5 gate walk found eight
      buttons that did nothing, all fixed in the same slice (STATUS, "Eight
      buttons that did nothing, found by the F5 gate walk").
- [x] **`halaqa:verify-structure` captured in STATUS** — see "ADR-025 gate
      condition 2". The capture is evidence rather than decoration: the gate
      **fails before the backfill and passes after it**.
- [x] **The deletion slice shipped** — 2026-09-12, **ADR-029**, which
      supersedes ADR-025's gated status. The Qur'an dataset and its readers
      moved to `Courses\Components\Quran`, mushaf editorial was ported to
      Inertia rather than lost, and the four Blade controllers that read the
      dataset are gone.

**Re-verified 2026-09-15 on current `main`**, because the original capture is
three days and several slices old:

```
$ php artisan db:seed --class=HifzDemoSeeder
$ php artisan halaqa:verify-structure          # before backfill
programs=1 unmapped=1 enrollments=0 unlinked=0 sessions=0 unmirrored=0 attendance_expected=0 missing=0
halaqa:verify-structure FAILED — do not treat engine structure as authoritative for Hifz.

$ php artisan halaqa:backfill-structure
programs=1 mapped=1 sessions_mirrored=5 enrollments_linked=1 attendance_written=5

$ php artisan halaqa:verify-structure          # after backfill
programs=1 unmapped=0 enrollments=1 unlinked=0 sessions=5 unmirrored=0 attendance_expected=5 missing=0
halaqa:verify-structure OK — Hifz structure fully represented on the engine.
```

Identical to the original capture, and still non-vacuous — it fails first.

**`halaqa:verify-mirror` is green but says nothing**, and should not be quoted
as evidence: it reports `links=0` because `QURAN_HALAQA_DUAL_WRITE` is off by
design, so "every link is mirrored" is true over no links.

**What is actually left is a product decision, not a gate.** The Blade
screens that survive — hub, five dashboards, programmes, enrolments,
milestones, reports — touch no dataset model. Retiring them is an IA decision
with its own parity work (CLAUDE.md rule 1), and rule 7's freeze has expired by
its own terms.

---

## 4. Phase 5 — device work (SPEC §50)

Needs a machine with Android Studio / Xcode (see `docs/MOBILE.md`):

- [ ] `npm install && npx cap add android` (and `ios` on macOS), `npm run
      cap:sync`, open and run on a real device.
- [ ] Walk the §50 device checklist in MOBILE.md — mic permission for
      pronunciation, BML return-URL landing back inside the shell, offline
      page, RTL/Thaana fonts.
- [ ] Record results in STATUS.md, then signing keys and store listings.
- [ ] Push notifications stay future: needs FCM/APNs keys + a token
      endpoint before wiring.

---

## 5. Deploy gates & housekeeping

- [ ] **Payload-cleanup deploy** (Phase 4): the legacy
      `enrollment_pending_payload` read path is a safety net only. It now has
      a command rather than a line of SQL to copy out of STATUS:

      ```
      php artisan payments:verify-payload-drain
      ```

      Run it **on the deployment you are cleaning up**, not locally. It exits
      non-zero while any payment could still arrive at the webhook and need
      the payload, and lists the rows holding it up. When it is green,
      schedule the cleanup migration (rule 9: its own deploy).

      **Read the warning if it prints one.** A database that never had a
      pre-P4.2 payment answers exactly like one that has drained, so the
      command says which of the two zeros it found — *"proves nothing about
      any other deployment"* means the gate has not really run. As of
      2026-09-15 every environment is in that state, because no real payment
      exists anywhere yet (ADR-021).
- [ ] **Public checkout UX swap**: the enroll-first checkout is live behind
      the existing public flow; swapping the public entry UX is a product
      decision — walk it on test first.
- [ ] **Branch protection**: confirm `docs/BRANCH_PROTECTION.md` is applied
      on `main` (required CI check, no direct pushes, no bot self-merge).
- [ ] Delete the leftover `ci-control-main` branch on GitHub (diagnostic
      control for the L7 CI incident; its PR #149 is closed — the proxy
      could not delete the remote branch).
- [ ] BML production config stays untouched until first real use; rule 9
      reactivates in full at the first real student/payment (ADR-021).
