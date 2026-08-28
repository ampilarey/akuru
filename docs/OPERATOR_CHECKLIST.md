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

## 1. Browser walks (definition of done — §57)

CI is green everywhere, but the DoD requires each surface walked by a person
in a browser. Tick each line only when the full loop worked by hand.

### 1a. Library writer portal (L5)

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

- [ ] As a teacher, open the recitation review queue. Expect: byte-identical
      to the pre-AI flow — no AI column, submissions review normally.
      (The AI opinion column only appears when the flag in §2b is on.)

### 1f. Money surfaces (Phase 4 close-out)

- [ ] Admin payments screen: refund a confirmed payment to **wallet**;
      expect enrollment cancelled, discount released, wallet credited,
      `refunded` filter shows it.
- [ ] Enrollment page: record a **manual** payment; expect activation
      without BML.
- [ ] Payments CSV export downloads with refunded totals.
- [ ] Offering **price override**: set one on an offering; public course
      listing shows the override; `0` behaves as free.

### 1g. Mobile shell smoke (Phase 5 — browser part only)

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

## 3. F5 — Hifz retirement (ADR-025 gate)

The old Hifz module is frozen and ready to delete once the engine-backed
flow is verified equivalent:

- [ ] Run the ADR-025 verification walks on the seeded representative
      dataset (teacher assigns → student submits → teacher reviews →
      progress/milestones match the legacy screens).
- [ ] Capture the verify-structure script output in STATUS.md.
- [ ] Then request the deletion slice — it is one PR (namespace removal,
      route cleanup), pre-scoped, no behavior change.

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
      `enrollment_pending_payload` read path is a safety net only. Run the
      verification query recorded in STATUS §5h; when it returns zero
      pending-payload payments, schedule the cleanup migration (rule 9:
      its own deploy, after the switch has been stable).
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
