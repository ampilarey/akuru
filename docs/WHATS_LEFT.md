# What is left — one list (2026-10-12)

Everything still to do on the platform, in one place, as of `main` after
the name tags plan, BH1 and the print-draft clean-up (STATUS §5ru–§5rx).
Each line points at the document that holds its detail. That document stays
the source of truth; this page is the index. Update it in the PR that
closes or adds a line.

**Every planned slice is merged:**
- the course engine;
- Arabic and Qur'an;
- the Library (L1–L7) and research and articles (R1–R5);
- the website (W1–W3) and EduPage parity (E1–E22);
- the Bookstore (B0–B11), commerce parity (P1–P8) and lending (L1–L4);
- sign-in and workspaces (ID1–ID5);
- the trilingual sweep (C21);
- name tags (NT0–NT7);
- BH1 (C22).

## 1. Agent-buildable — small defects (KNOWN_ISSUES, all P4)

| # | What | Where |
|---|---|---|
| K1 | Nothing closes or cancels a **substitution request**: a covered lesson stays *assigned*, one no longer needed stays open until deleted. | KNOWN_ISSUES, TS1 (STATUS §5rh) |
| K2 | A teacher's **absence cannot be removed** from its page: `absences.destroy` exists, no button calls it. | KNOWN_ISSUES |
| K3 | A **promotion campaign ended before it starts** reads its window backwards on the list and in the CSV. A wording choice: *ended before it started*, or keep its end at its start. | KNOWN_ISSUES, PC1 (STATUS §5qy) |

## 2. The owner's — server, credentials, settings (OWNER_ACTIONS)

| # | What | Item |
|---|---|---|
| O1 | **BML keys on the server.** No webhook secret is needed (2026-09-30); one real payment went through on 2026-10-03. The `payment-test` course should be archived or deleted. | OWNER_ACTIONS 2 |
| O2 | Confirm a generated **report card reaches Ready** (the queue worker runs). | OWNER_ACTIONS 3 |
| O3 | **Branch protection** on `main` — "keep this for later" (2026-10-03). | OWNER_ACTIONS 6 |
| O4 | **Walk staging**: `node scripts/smoke/all.mjs` against `test.akuru.edu.mv`, the summary into STATUS. | OWNER_ACTIONS 7 |
| O5 | **The seven Library policy pages**: seed once, read, edit. | OWNER_ACTIONS 17 |
| O6 | **The Vendor Agreement**, then invite Fitrah. | OWNER_ACTIONS 18 |
| O7 | **Bookstore rewards** — the numbers, and when to switch them on. | OWNER_ACTIONS 20 |
| O8 | **Referral credit** — the amounts, and when to switch it on. | OWNER_ACTIONS 21 |
| O9 | ~~**Chrome on the host**~~ — **done 2026-10-13**: a home-folder Chrome behind a launcher, PDF report cards available. | OWNER_ACTIONS 22 |
| O10 | The office's own **email and phone for purchase notices**. | OWNER_ACTIONS 23 |
| O11 | **SMS live** on production, then rotate the exposed partial SMS key. | OWNER_ACTIONS 24 |
| O12 | **Verify Fitrah's ID card**, and any writer approved before P2. | OWNER_ACTIONS 25 |
| O13 | **The print desk**: Chrome is in place; enter the real price list, papers and finishing, then switch it on and place one test order. | OWNER_ACTIONS 26, NAME_TAGS_PLAN §14 |
| O14 | ~~**Ghostscript**~~ — **done 2026-10-12**: `/bin/gs` (9.27). | OWNER_ACTIONS 27 |
| O15 | **`/en/catalog/courses` answered 404** on production (2026-10-03), not reproduced. Check for a leftover `bootstrap/cache/routes-v7.php` on the host. | KNOWN_ISSUES |

## 3. Decisions and reviews only the owner can give

| # | What | Where |
|---|---|---|
| D1 | **Writer payouts** stay off until the tax and accounting treatment is confirmed. | BACKLOG A9 |
| D2 | **Native review of the Dhivehi and Arabic** phrase books — first passes throughout. Among them, the menu word `ސަލާމް` for *Absences*, *Absence notes* and *Review notes*. | BACKLOG A10, C21 |
| D3 | **Retire or translate the old Blade screens**: analytics (41 strings), the Qur'an progress pages (49), e-learning (35), the old layouts (17). An IA decision first. | BACKLOG C21 |
| D4 | **Linked separate logins** (SIGN_IN_PLAN D7, "later, as its own track"), if wanted. | SIGN_IN_PLAN |
| D5 | **Sensitive-note readers**: kept as is (2026-09-25). Revisit only if the office asks. | BACKLOG C4 |

## 4. Waiting on a condition (do not build before it)

| # | What | Condition |
|---|---|---|
| W1 | **Qur'an A.4b**: offering-session reads switch to `offering_halaqa_session_links`. | dual-write confirmed on the host (BACKLOG C3) |
| W2 | **Drop the archived legacy student tables** (Deploy 3 cleanup). | a stable period after Deploy 3 (BACKLOG C5) |
| W3 | **Rule 9 in full** — three-deploy migrations and the waits. | the first real student, payment or Hifz user (ADR-021) |

## 5. Later, on request (not planned)

- **Print products beyond name tags**: other sheet sizes (A3), cards,
  stickers by the roll, photo prints; vendors other than Akuru printing;
  school bulk orders through quotes and credit with a *for a class* mode
  (NAME_TAGS_PLAN §11).
- **Library**:
  - OCR and page images for scanned PDFs (B1). Ghostscript, behind
    `PdfRasterizerInterface` since NT5, would serve the rasterising.
  - Two-column and glyph-reversed PDFs (B2).
  - Private highlights, dropped by decision (B7, OWNER_ACTIONS 19).
  - The not-MVP list (B13).
- Everything else raised and parked is in `docs/BACKLOG.md` §C.
