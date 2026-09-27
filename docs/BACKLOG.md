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
| B5 | §8.2–§8.4 | ~~**Remaining filters**: difficulty, reading time, popular-by-period, research's peer-reviewed / open-access filters.~~ **Built 2026-09-27** (STATUS §5in): difficulty (a new column the writer and the office set), the reading-time band, *popular this week / month* from the reading events, peer-reviewed (a reviewer has reported) and open access (free public research). | On the public shelf and in its CSV. |
| B6 | §8.7 | ~~**Author page extras**: featured works, social links.~~ **Built 2026-09-27** (STATUS §5im): up to three own published works pinned first, a website and six social addresses in a link row, edited on the writer's author-page form. | `featured_item_ids` and `social_links` on `writer_profiles`. |
| B7 | §9.1 | **Private highlights, ~~in-book search~~, per-session reading time.** In-book search **built 2026-09-27** (STATUS §5iq): a search box in the reader over the pages this reader may open, hits as page links with a plain-text snippet. | Highlights need a range model; per-session time needs the reader to report it. |
| B8 | §10 | ~~**Parent's view of a child's library** (reading progress of a linked child).~~ **Built 2026-09-27** (STATUS §5io): *Library* on each row of *My children* opens the verified child's reading progress and purchases, with a CSV; bookmarks and notes stay the child's own. | `/portal/children/{student}/library`. |
| B9 | §11.1 | **Writer application extras**: photo, previous publications, ID document at application time. | Portrait is on the author page already; publications and ID are new fields. |
| B10 | §15.2 | **Optional expiry on purchased gift cards**; admin deactivation and fraud logs beyond what L4 has. | `expires_at` exists on cards; the purchase flow leaves it null by design. |
| B11 | §41 | **Notifications not built**: new content published (to readers), continue-reading reminder, discount used, payment issue, large wallet adjustment, suspicious activity to admins, copyright complaint. **Email/SMS channels** for library notifications (in-app only today). | `NotifyLibraryUserAction` is the one door; each is a call site. Channels need the queue worker running (OWNER_ACTIONS item 3). |
| B12 | §42 | ~~**Admin settings screen** for the commerce knobs (min payout, commission, gift card range, preview limit, feature flags).~~ **Built 2026-09-27** (STATUS §5ip): `/admin/library/settings` — refund window, writer's share, minimum payout, gift-card range, the research review gate and the payouts switch — stored in Settings and read by every knob reader through one resolver, config as the default. The preview limit stays per item (the office sets it on each); the reading-abuse thresholds stay in config on purpose. | `ResolveLibrarySettingAction`. |
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
| C7 | **Akuru Bookstore** (named 2026-09-26; owner's brief, 2026-09-25): a multi-vendor shop for printed books and educational materials, invited vendors only at first, every vendor's items in the one shop, a page per vendor, subdomain later. **Planned in `docs/BOOKSHOP_PLAN.md`** (v2.1 audited: slices B0–B9, decisions §13, audit §14; first vendor kit `docs/vendors/FITRAH.md`). Parked inside it for "later, on request" (B9): public vendor onboarding, custom hosts, cash on delivery, newsletter section, abandoned-cart reminders, bulk quotes for schools, USD pricing, a search service, analytics funnel. B0 to B8 built (STATUS §5gu–§5hf); B9 requested 2026-09-26 and being built as sub-slices — B9a public vendor onboarding built (§5hg); B9b cash on delivery built (§5hh); B9c newsletter section and cart reminders built (§5hi); B9d bulk quotes for schools built (§5hj); B9e the shop funnel and search contract built (§5hk); B9f own domains and the shop subdomain built (§5hl; its dollar prices removed at the owner's word, §5hm); B10b Bookstore admins (§5hn); B10c shops' own CSS (§5ho, ADR-039); B10d theme gallery (§5hp). Paid themes (money between shops) not built — an owner decision. **Audited 2026-09-26 (plan §15)**: every B9 item built; the seven small plan items the audit found unbuilt (the e-book link, a gift message, the returns rate, the shop-on/off switch, GST collected, alt text, gallery zoom) were **built the same day as B11** (STATUS §5hr). Left from the plan: the per-filter listing cache (§15 no. 15, fine at this catalogue size). Owner actions left: DNS/cPanel for any subdomain or shop domain, a Meilisearch server if wanted. | Owner's brief in conversation; `docs/BOOKSHOP_PLAN.md`. |
| C8 | ~~**A role and activation screen on `/admin/users`** (admin-panel audit, `docs/ADMIN_PANEL.md` finding 5, 2026-09-26). No screen assigns or removes a role or reactivates a deactivated account; roles come from seeders, `bookshop:grant-manager` or tinker. One slice: assign/remove roles with the super-admin protections `DeleteUserAccountAction` has (never self, never the last super admin), reactivate, CSV.~~ **Built 2026-09-27** (ADR-040 slice 4, STATUS §5ig): *Roles & access* on every row of `/admin/users`, with the protections named. | `docs/ADMIN_PANEL.md` §3. |
| C10 | ~~**The role matrix tidy-up** (parked 2026-09-27 with ADR-040 slice 2, STATUS §5ie). The educational admin's set is now a decision; three things around it were left as found. (1) `headmaster` and `supervisor` still hold `prayer.manage`, `daily_content.manage` and `daily_content.approve` in `RoleSeeder`, though since slice 2 no route admits them to those screens — dead grants, harmless, confusing. (2) The Hifz module treats `admin` as a dean by role (`User::isHifzDean()`, `isAdminLevel()` in `HifzHubController` and `HifzScopeService`), so an educational admin lands on the dean's Hifz dashboard with read-only permissions; the owner's decision (Hifz is education, the dean's) says that role check should drop `admin`, which is a Hifz behaviour change with its own tests. (3) `headmaster`, `teacher`, `student` and `parent` are still seeder-only (KNOWN_ISSUES 11). One slice: prune the grants, move the four roles into a migration, and settle the Hifz dean check.~~ **Built 2026-09-27** (STATUS §5ij): the dead grants pruned, every school role by migration and synced from `RoleGrants`, the Hifz dean check without `admin`. | `RoleGrants`, `RoleSeeder`, KNOWN_ISSUES 11. |
| C9 | **The admin panel in three languages, and off Blade** (audit findings 9–10). Since STATUS §5ia the two Blade dashboards (`/dashboard/numbers`, `/dashboard/supervisor`) are no longer anybody's landing — `/admin` leads with their headline numbers — so the port can retire them once the rest of their content (recent enrolments, system health, prayer times) has a home on the hub or elsewhere. All 24 Blade admin screens and the six Inertia admin pages outside the Bookstore are hardcoded English; `TranslationParityTest` cannot see strings that were never keyed. Port one screen at a time to Inertia with `trans()` keys (the Bookstore's `t` pattern), the office ones first. The layout audit (`docs/ADMIN_PANEL.md` §5, L7–L10) adds why the port matters beyond language: the Blade nav has no language switcher, renders flashes per screen and loads its font from a third party — one Inertia shell retires all of it. Since STATUS §5id the Blade nav renders the one navigation map (workspace, bar, groups, switcher), so its links no longer drift from the Inertia shell; what remains of the port is the look and the language switcher. Not urgent: the office reads English and families never see these screens. | STATUS §5n deferred the same; `docs/ADMIN_PANEL.md` §3. |

## How to use this file

- Building something here: link the PR and strike the row, or move the
  detail to STATUS.
- Parking something new: add a row with the date and the reason, in the
  same PR.
- The owner decides the order. Nothing here is urgent by itself; A1 and C6
  are the two that affect money and mail on a live host.
