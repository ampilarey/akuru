# Backlog — parked items and ideas (kept for later)

The owner's standing instruction (2026-09-25): *"Keep all new ideas and
parked items documented. Later we will build or fix."* This is that list.
It holds what was deliberately **not** built or **not** decided, with why
and where it came from, so nothing is lost between sessions. It is not a
defect list (`KNOWN_ISSUES.md`), not the owner's decision queue
(`OWNER_ACTIONS.md`), and not status (`STATUS.md`); it points at those
where they hold the detail.

Update it in the same PR that parks something, and strike an entry in the
PR that builds it.

## A. Parked by the owner

| # | Item | Parked when | What is waiting |
|---|---|---|---|
| A1 | **BML webhook secret** (OWNER_ACTIONS item 2). Production `.env` has none of `BML_WEBHOOK_SECRET`, `BML_WEBHOOK_ALLOW_UNSIGNED`, `BML_BASE_URL`; the webhook fails closed without a secret. The owner reports card payments work, which is possible if the callback secret is set under another name. | 2026-09-25, "Keep bml for later" | Run on production: `grep -E "^(BML_\|PAYMENTS?_)" .env \| sed 's/=\(.\{4\}\).*/=\1…/'` and paste the key names (values stay masked). Then either set `BML_WEBHOOK_SECRET` or confirm `config/bml.php` reads the name in use. |
| A2 | **Branch protection on `main`** (OWNER_ACTIONS item 6, `docs/BRANCH_PROTECTION.md`). Merge gates are discipline, not mechanism. | deferred earlier | A repo admin applies the rule set; ADR-027's read-the-conclusion-back stays load-bearing until then. |
| A3 | **The `resume` magic link** (OWNER_ACTIONS item 14): build (recommended: short-lived, single-use, resumes the registration form only) or delete the route, model and table. | 2026-09-25, "this also later" | The word "build" or "delete", when the owner returns to it. |
| A4 | **Hifz enrolment when a pupil leaves** (OWNER_ACTIONS item 15): reuse `transferred` or add a fifth status, plus a screen that ends an enrolment. | 2026-09-25, parked with item 14 | The owner's choice of word; then one slice. |
| A5 | **Who marks the work and whose work they see** (OWNER_ACTIONS item 16, KNOWN_ISSUES #28). | not yet presented | Present when the owner returns to the decision queue. |
| A6 | **Read the seven library policy pages** (OWNER_ACTIONS item 17): first drafts seeded 2026-09-25. | new | Read and edit in the page editor; remove the "draft" line when satisfied. |
| A7 | **Permissions table on the host** (OWNER_ACTIONS item 4) is unverified. | earlier | Run the check in item 4 on production once. |
| A8 | **OTP login on staging** has not been walked. | earlier | One sign-in by phone on `test.akuru.edu.mv` with the log SMS sender. |
| A9 | **Writer payouts** stay off (`LIBRARY_PAYOUTS_ENABLED=false`, ROADMAP §9.4) until the tax/accounting treatment is confirmed. Earnings accrue meanwhile. | L6 | The owner's answer on withholding and receipts; then flip the flag. |
| A10 | **Dhivehi and Arabic strings** for the whole Library and commerce surface are a first pass pending native review (every `resources/lang/{dv,ar}/public.php` block marked "pending native review"). | L1 onward | A native reader corrects them in the translations screen. |

## B. Library — not built, by plan section

Everything below was audited against the code on 2026-09-25 (STATUS
§5gl–§5gr). Built work is in LIBRARY_PLAN's inline notes; this is the rest.

| # | Plan § | Idea | Note |
|---|---|---|---|
| B1 | §36 | **Page images / OCR for PDFs.** The reader extracts text; a scanned PDF yields no pages and the uploader is told to paste the text. | Needs a rasteriser (Imagick, poppler, ghostscript or mutool) or an OCR service on the host. Behind `PdfPageTextExtractor`, so a swap, not a rewrite. |
| B2 | §36 | **Two-column PDFs** read across, not down; producers that write RTL text glyph-reversed come out reversed within words. | Column detection by x-gap clustering; RTL heuristics. The HTML body always wins, so the office has a workaround. |
| B3 | §36 | **Rich text editor** for articles (the body is HTML in a textarea). | TipTap or similar in `Write.jsx` and `Admin.jsx`; sanitiser already in place. |
| B4 | §8.1, §8.5, §18, §19 | **Promotions and campaigns**: promotions page, "discounted" filter, bundles, Ramadan/back-to-school offers, gift card bonus campaigns. | `promotion_campaigns` table exists (§35.10); no writer, no UI. |
| B5 | §8.2–§8.4 | **Remaining filters**: difficulty, reading time, popular-by-period, research's peer-reviewed / open-access filters. | Small, once the data exists (difficulty is not a column yet). |
| B6 | §8.7 | **Author page extras**: featured works, social links. | Two columns on `writer_profiles` and the form. |
| B7 | §9.1 | **Private highlights, in-book search, per-session reading time.** | Highlights need a range model; search is a LIKE over `library_item_pages` scoped to the reader's access. |
| B8 | §10 | **Parent's view of a child's library** (reading progress of a linked child). | `VerifiedGuardianLink` gives the children; a portal page reads `library_reading_progress` for them. |
| B9 | §11.1 | **Writer application extras**: photo, previous publications, ID document at application time. | Portrait is on the author page already; publications and ID are new fields. |
| B10 | §15.2 | **Optional expiry on purchased gift cards**; admin deactivation and fraud logs beyond what L4 has. | `expires_at` exists on cards; the purchase flow leaves it null by design. |
| B11 | §41 | **Notifications not built**: new content published (to readers), continue-reading reminder, discount used, payment issue, large wallet adjustment, suspicious activity to admins, copyright complaint. **Email/SMS channels** for library notifications (in-app only today). | `NotifyLibraryUserAction` is the one door; each is a call site. Channels need the queue worker running (OWNER_ACTIONS item 3). |
| B12 | §42 | **Admin settings screen** for the commerce knobs (min payout, commission, gift card range, preview limit, feature flags). | All are `config/library.php` + env today; a settings page would use the existing `SettingsRepositoryInterface`. |
| B13 | §38 | **Explicitly not MVP**: DOI, journal issues/volumes, subscriptions, offline reading, native app, DRM, AI plagiarism checks, audiobooks, multi-institution licensing, loyalty points, wallet-to-wallet transfer. | Recorded so nobody asks twice. |
| B14 | §29 | **Analytics** beyond sales and reading alerts (most-read by period, conversion, search terms). | Reading events and purchases are recorded; only reports are missing. |

## C. Ideas raised during the work, outside the Library

| # | Idea | Where it came from |
|---|---|---|
| C1 | **Retire the surviving Hifz Blade screens** (hub, five dashboards, programmes, enrolments, milestones, reports) with an Inertia parity pass. The freeze expired with §2b; retiring is a separate IA decision. | CLAUDE.md rule 7. |
| C2 | **Report cards as PDF** (ADR-012: HTML is the supported output; PDF is a future renderer binding). | KNOWN_ISSUES "Fixed on main" table. |
| C3 | **Qur'an A.4b**: switch offering-session reads to `offering_halaqa_session_links` after dual-write is confirmed; keep `QURAN_HALAQA_DUAL_WRITE` off until then. | STATUS closing notes. |
| C4 | **E19 sensitive-note readers**: `admin` is deliberately excluded; widening is a one-line migration, narrowing later is a disclosure. Decided "keep" 2026-09-25; revisit only if the office asks. | OWNER_ACTIONS item 12. |
| C5 | **Deploy 3 cleanup proposal** (`docs/migrations/s11-deploy-3-cleanup-proposal.md`): the archived legacy student tables can be dropped after a stable period. | STATUS §5gh. |
| C6 | **A queue worker on production** (OWNER_ACTIONS item 3): queued mail (gift card codes, enrolment notices) waits without one. | Every queued Mailable. |
| C7 | **A Shop for printed books and educational materials** (owner's question, 2026-09-25). Not the Library: physical goods have stock, quantity and fulfilment, not access grants. Proposed as a small Shop in the Commerce domain — products (optionally linked to a library item for "also in print"), orders with collect-at-Institute or delivery, the same webhook-confirmed BML payable pattern, wallet and discount codes as payment, an office queue (stock, mark ready, mark collected) with CSV and in-app notices, Shop in the public header and My orders for buyers. Three slices: products and public shop; checkout and fulfilment; office queue and reports. First version leaves out rate-based shipping, returns and external stock sync. | Owner's question in conversation; ROADMAP domain map (Commerce is the shared commerce domain). |

## How to use this file

- Building something here: link the PR and strike the row, or move the
  detail to STATUS.
- Parking something new: add a row with the date and the reason, in the
  same PR.
- The owner decides the order. Nothing here is urgent by itself; A1 and C6
  are the two that affect money and mail on a live host.
