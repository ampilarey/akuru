# Akuru Knowledge Library — Complete Project Plan

> Integration note: read together with `docs/ROADMAP.md` §9 (L-track), which integrates this plan into the platform and **overrides it on four points**: (1) wallet/gift cards/discounts are the platform-wide `Domains/Commerce`, not library-scoped tables; (2) writer is a role on the unified People record, not a separate identity; (3) course teaching content lives in engine lessons — the Library holds standalone publications, with "course-included" access granted via enrollment events; (4) the eight suggested domains collapse to `Library` + shared `Commerce`, reusing Finance/Media/Notifications/Identity.

## 1. Project Name

**Akuru Knowledge Library** (chosen). Alternatives considered: Akuru Research Library, Akuru Digital Library, Akuru Publications, Akuru Books & Articles, Akuru Reading Platform, Akuru Islamic & Arabic Knowledge Library.

The name is broad enough for: books, articles, research papers, course materials, free reading resources, paid publications, writer-submitted content, future journals, future subscriptions, gift cards and promotions.

## 2. Main Goal

A complete reading, publishing, and digital sales platform inside the Akuru Institute website, allowing:

- Users to read free books and articles, and buy paid ones.
- Protected online reading with progress, bookmarks, notes, and highlights.
- Writers to register, apply for writer access, and upload books/articles/research papers.
- Admin/editor review and approval before publishing.
- Writers to see sales, views, earnings, and payout status; Akuru earns commission.
- Admin-created gift cards, discounts, coupons, promotions, campaigns.
- Wallet balance and gift card credit as payment.
- Students to access selected books/articles through course enrollment.

## 3. Important Protection Principle

Never promise "nobody can copy or download anything 100%" — screenshots, screen recording, phone cameras, manual typing, and OCR cannot be stopped. The correct promise: **"Protected online reading. Downloading and copying are restricted."** The system makes copying difficult, not impossible.

## 4. Platform Type

Not a simple PDF upload page — a publishing platform with connected modules: Public Library, Protected Online Reader, Reader Dashboard, Writer Portal, Editorial Review System, Admin Publishing Dashboard, Payment & Access System, Writer Sales & Payout System, Promotions & Discount System, Gift Card & Wallet System, Analytics & Reports.

## 5. Main Content Types

- **5.1 Books:** Arabic learning, Qur'an learning, Islamic studies, Dhivehi educational, children's learning, teacher manuals, student textbooks, course books, short ebooks.
- **5.2 Articles:** short educational, Arabic grammar, Qur'an memorization tips, Islamic reminders, parenting/education, student guidance, teacher-written lessons.
- **5.3 Research Articles:** academic papers — Islamic, Arabic language, education, Qur'an studies, Shariah, Dhivehi Islamic research.
- **5.4 Course Materials:** class notes, paid course handouts, free resources, teacher uploads, exam prep notes, worksheets.
- **5.5 Future:** audiobooks, audio lessons, video lectures, downloadable worksheets for free content, journal issues/volumes, conference papers, institutional library access.

## 6. Access Types (one per item)

1. **Free Public** — no login (reminders, intro articles, marketing, OER).
2. **Free Login Required** — registered users (student resources, trackable content).
3. **Paid One-Time Purchase** — buy once, access granted.
4. **Course Included** — access via enrollment in an Akuru course (class notes, required reading, textbooks).
5. **Manual Access Grant** — admin-granted (scholarships, teachers, reviewers, testing, special cases).
6. **Gift Card / Wallet Access** — bought with gift card or wallet credit.
7. **Subscription Access** — LATER PHASE only; not in first version.

## 7. User Roles

- **7.1 Guest:** browse, read free public, view details/author profiles/previews, register. Cannot read paid, save progress, bookmark, note, or buy without account.
- **7.2 Reader/Student:** read free, buy paid, use gift cards/wallet/discount codes, continue reading, progress, bookmarks, private notes, private highlights, purchases, invoices, completed list, recommendations.
- **7.3 Parent:** buy books for children, assign to child account, buy gift cards for child, view child's reading progress (optional), pay course-included materials, view child's library.
- **7.4 Writer/Author:** apply, profile, upload books/articles/research, drafts, submit for review, see status, receive editor comments, upload revisions, see sales/earnings/payout status, view/read analytics, request payout, request writer-funded promotion (optional). Cannot: publish directly, change commission rules, see other writers' sales or private reader notes, withdraw without admin approval.
- **7.5 Editor:** review submissions, request changes, reject, edit metadata, approve normal articles (if permitted), assign reviewers for research, prepare for publishing.
- **7.6 Reviewer** (research): review assigned submissions, comments, recommend accept/revise/reject, upload review files, sees only assigned submissions.
- **7.7 Finance/Admin:** sales, BML status, refunds, wallet transactions, gift card usage, discounts/promotions usage, writer earnings, payout reports, mark payouts paid, export reports.
- **7.8 Admin/Publisher:** manage all content, writers, applications, submissions, prices, commission, gift cards, discount codes, campaigns, featuring, categories, reports, purchases, manual access, refund rules, wallet credit, publishing settings.
- **7.9 Super Admin:** everything.

## 8. Main Public Pages

- **8.1 Library Home:** featured books/articles, latest research, free resources, paid books, popular, new releases, promotions, discounted, gift card promo, categories, authors, continue reading (logged in).
- **8.2 Books page filters:** free/paid, discounted, language, category, author, newest, most read, most purchased, difficulty, price range.
- **8.3 Articles page filters:** free/paid, discounted, category, author, language, reading time, latest, popular.
- **8.4 Research page extra filters:** research area, author, keywords, published date, peer-reviewed, open access, paid/free, discounted.
- **8.5 Promotions page:** Ramadan offers, back-to-school, new writer launches, free article campaigns, bundles, gift card bonus campaigns.
- **8.6 Gift Card page:** buy, choose amount, recipient name/email/mobile, gift message, BML payment, code delivery, redeem into Akuru wallet.
- **8.7 Author page:** name, photo, bio, qualifications, published works, featured, totals, social links (optional).
- **8.8 Content detail page:** cover, title, subtitle, author, category, language, description, abstract, price, discount price, free/paid label, page count/reading time, publication date, preview, buy, read (if access), wishlist, gift card/wallet at checkout, related content, table of contents (books), copyright notice. Research adds: abstract, keywords, citation format, references, DOI field (future), review status.

## 9. Protected Reader Plan (most important module)

Never expose a PDF URL directly. Original files in private storage; reading happens inside Akuru's own protected reader.

- **9.1 Reader features:** continue from last position, progress %, page/chapter navigation, bookmarks, private notes, private highlights, in-book search (if allowed), font size, Arabic RTL, Dhivehi Thaana, English LTR, light/sepia/dark modes, mobile-friendly, full-screen, mark as completed, reading history, reading time (optional).
- **9.2 Copy/download protection:** no direct PDF URL, no public storage, no browser PDF viewer, no download button, private storage only, signed temporary page access, expiring page tokens, dynamic per-page watermark (name/mobile/email/date/time), disable right-click/text selection/print on protected content, detect rapid page opening, detect multi-device/session abuse, limit simultaneous sessions, log suspicious activity, block hotlinking. (Reduces copying; cannot stop screenshots/cameras.)
- **9.3 Watermark example:** `Asif Moosa Ibrahim | 7820288 | 11 June 2026 | Akuru Institute` — visible enough to discourage sharing, light enough to read through.
- **9.4 Preview system:** selected free preview pages; admin sets preview percentage; writer can suggest; admin final; previews also protected/watermarked.

## 10. Reader Dashboard

- **My Library:** purchased books/articles/research, free saved, course-included, gifted, recently opened.
- **Continue Reading:** title, last page/chapter, %, continue button, last read date.
- **Reading Progress:** started / in progress / completed, last read, total time (optional), %.
- **Notes & Bookmarks:** all bookmarks/notes/highlights, filter by item, jump to page. Private rule: writers never see private reader notes.
- **Purchases & Payments:** history, BML/gift card/wallet payments, discounts used, invoices, refund status.
- **Wallet & Gift Cards:** balance, redemptions, store credit, refund credit, promotion credit, spending history.

## 11. Writer Portal

- **11.1 Application:** full name, mobile, email, photo, bio, qualifications, expertise, previous publications, bank details (optional first), ID document (optional), agreement to publishing terms, copyright declaration. Admin approval required before upload/publish.
- **11.2 Dashboard:** submissions totals, drafts, under review, published, rejected, sales, earnings, pending/available/paid payout, views/reads, completion rate, best sellers, active promotions affecting writer content, latest editor comments.
- **11.3 Upload Book:** title, subtitle, language, category, description, TOC, author, co-authors, cover, manuscript file, suggested price, free/paid suggestion, preview page suggestion, keywords, copyright declaration, AI-use declaration (optional), submit for review.
- **11.4 Upload Article:** title, summary, main content, cover, category, language, tags, suggested price/free, references (optional), submit.
- **11.5 Upload Research:** title, abstract, keywords, author details, co-authors, affiliation, field, manuscript, references, originality declaration, conflict-of-interest declaration, ethics declaration (if needed), suggested reviewer (optional), submit for editorial review.
- **11.6 Writer promotion requests** (NOT MVP): writer-funded discount requests with admin approval; earnings follow the funding rule.

## 12. Editorial Workflow

- **12.1 Normal:** draft → submit → editor quality check → changes requested → revision → editor recommends → admin sets price/commission → admin publishes → live.
- **12.2 Research:** submit → editor basic check (immediate reject if unsuitable) → assign reviewer → reviewer comments → writer revises → re-check → editor accept/reject recommendation → admin publishes.
- **12.3 Statuses:** Draft, Submitted, Initial checking, Under review, Changes requested, Revised submitted, Accepted, Rejected, Scheduled, Published, Unpublished, Archived.

## 13. Admin Dashboard

- **13.1 Content:** add directly, review submissions, edit metadata, covers, replace manuscript, type/language/category/tags, free/paid, price, preview pages, publish/unpublish, feature, archive.
- **13.2 Writers:** approve/reject applications, suspend, verify, edit profiles, view submissions/sales, set commission, payout method, mark payouts paid.
- **13.3 Reviews:** assign reviewer, deadlines, view comments, request revision, accept/reject, history.
- **13.4 Sales:** totals, by date/content/writer/category, BML success/failed/pending, wallet/gift card payments, discount usage, refunds, net revenue, writer share, Akuru commission.
- **13.5 Payouts:** earnings, payout period, minimum payout, statements, pending/paid, payment reference upload, export.
- **13.6 Promotions:** gift cards, wallet credit, discount codes, free access coupons, campaigns, bundles, student offers, writer-specific, category promotions, reports.
- **13.7 Reports:** most read/sold, most active readers, completion rates, writer performance, category performance, monthly sales, payment reconciliation, **gift card liabilities, wallet balance liabilities**, discount usage, promotion performance, suspicious access activity.

## 14. Payment Plan

Use Akuru's existing BML integration.

- **14.1 Normal flow:** open paid item → Buy → pending order → BML payment → **webhook confirms** → access granted → read in protected reader → writer sale record → commission calculated. RULE: access depends on the webhook, never only the return URL.
- **14.2 Checkout with discounts/gift card/wallet:** show original price → promotion discount → discount code field → gift card/wallet option → final amount; validate code; apply balance; pay remainder by BML; webhook confirms; access granted; writer earning per the commission/funding rule.
- **14.3 Access grant record:** user ID, content ID, purchase ID, access type, start date, end date (nullable), status active.

## 15. Gift Card, Wallet, Discounts, Promotions ("Commerce" per ROADMAP)

Includes: gift cards, wallet/store credit, discount codes, free access coupons, campaigns, bundles, admin manual credit, refund credit.

- **15.1 Gift card types:** fixed amount, custom amount, admin-issued, promotional, student reward; writer-/category-specific later. Examples: MVR 100 / 250 / 500 / 1000.
- **15.2 Gift card features:** unique code, balance, optional expiry, recipient name/email/mobile, message, purchase/redemption dates, partial use, admin deactivate, usage history, fraud logs.
- **15.3 Purchase flow:** select amount → recipient details → message → BML → webhook → generate code → deliver → recipient redeems → amount credited to recipient's Akuru Wallet.
- **15.4 CRITICAL RULE:** discount codes can NEVER be used to buy gift cards (abuse: 50% code buys MVR 1000 card for 500, spends 1000). Gift cards purchased only via real payment (BML) unless admin-issued.

## 16. Akuru Wallet / Store Credit

Balance sources: redeemed gift cards, refund credit, admin manual credit, promotion rewards, loyalty (later), course compensation credit. Spendable on books, articles, research, course materials, future courses (if allowed).

- **16.1 Features:** balance, credit/debit history, redemptions, purchases, refunds, admin adjustments, optional expiry for promotional credit.
- **16.2 RULE:** wallet is **payment, not discount** — writer earning is calculated from the full sale value when wallet is used. Exception: free promotional credit from Akuru — admin chooses whether it affects writer earnings.

## 17. Discount Codes

Examples: `AKURU10` (10%), `RAMADAN25` (25%), `NEWUSER50` (MVR 50 off), `ARABIC100` (MVR 100 off Arabic books), `FREEARTICLE`.

- **17.1 Types:** percentage, fixed, free item, category, writer, first purchase, student, bulk, course-student, new user.
- **17.2 Conditions admin can set:** start/end dates, usage limit, per-user limit, minimum purchase, maximum discount, applicable content/category/writer, new-users-only, Akuru-students-only, specific-course-students, combinable yes/no, usable with wallet yes/no, **who funds the discount**, whether writer commission is affected.

## 18. Promotion Campaigns

Examples: Ramadan (20% off Islamic books, free selected articles, gift card bonus buy-500-get-50), Back-to-School (beginner Arabic discounts, 3-books-for-250 bundle), New Writer Launch (first week 30% off), Akuru Student Offer (free/discounted course materials), Hifz Campaign (free memorization-tip articles, discounted Qur'an reading books).

Campaign features: name, description, start/end, banner, applicable items/categories/writers, percentage/fixed, funding source, homepage featuring, auto-apply or code-required, performance report.

## 19. Bundle Offers

Later phase — Arabic Beginner Bundle, Qur'an Reading Starter Pack, Teacher Resource Bundle, Research Article Pack, buy-3-get-1. Not MVP unless trivial.

## 20. Free Access Coupons

Admin codes (e.g. `FREE-QURAN-ARTICLE`, `TEACHER-FREE-ACCESS`, `STUDENT-GIFT`) for selected students, review copies, promotions, course students, competition winners. Carefully tracked.

## 21. Writer Commission Rules (discount funding)

- **Model A — Shared (DEFAULT):** discount reduces both shares. (100 − 20% → customer pays 80; writer 70% = 56; Akuru 30% = 24.)
- **Model B — Akuru-funded:** writer earns from original price (writer 70 of 100; Akuru receives 10). For marketing/student-support/Ramadan/new-user campaigns.
- **Model C — Writer-funded:** writer absorbs the discount; Akuru takes agreed commission per configured rule. For writer self-promotions.
- Admin chooses per code/campaign; default shared.

## 22. Gift Card vs Discount Code

| Item | Purpose | Affects writer earning? |
|---|---|---|
| Gift card | Payment method | Usually no |
| Wallet credit | Payment method | Usually no |
| Discount code | Price reduction | Yes, unless Akuru-funded |
| Free access coupon | Marketing/access tool | Per admin rule |

## 23. Commission Model

Start: **Writer 70% / Akuru 30%.** Admin can change global, per-writer, per-content. If gateway fees exist, recommended: calculate commission from net after payment fee when fee data is available.

## 24. Refund Rules

Admin defines: refund allowed/window, earnings locked until window ends, manual approval, wallet-to-cash policy, gift card refundability. Recommended: earnings payable only after refund window; redeemed gift cards non-refundable; promotional credit never refundable to cash; refunds to wallet or original method per admin decision.

## 25. Pricing Plan

Supported: free, fixed price, discount price, coupon, course-included, manual free, gift card payment, wallet payment. Future: monthly/yearly subscription, bundles, institution package, family package.

## 26. Language Support

English, Dhivehi/Thaana, Arabic. Requirements: Arabic RTL, Dhivehi RTL, English LTR, mixed-language content, Arabic/Thaana font selection, Arabic & Dhivehi search, multilingual metadata (optional).

## 27. Starting Categories

**Arabic:** Grammar, Reading, Writing, Speaking, Vocabulary, Beginners. **Qur'an:** Reading, Hifz/Memorization, Tajweed, Tafsir, Kids. **Islamic Studies:** Aqeedah, Fiqh, Seerah, Hadith, Manners, Family & Parenting. **Research:** Islamic, Arabic Language, Education, Qur'an Studies, Dhivehi Islamic. **Akuru Materials:** Course Notes, Teacher Resources, Student Handouts, Exam Prep, Worksheets.

## 28. Search & Discovery

Search: title, author, description, abstract, tags, category, language, content type. Filters: free/paid, discounted, type, language, category, author, newest, popular, most read, most purchased, price asc/desc, recently updated, difficulty.

## 29. Analytics

- **Reader:** last page, progress, completed, bookmarks, notes count, reading time (optional), device/session. Private notes never exposed to writers.
- **Writer:** views, readers, purchases, earnings, completion rate, best content, monthly sales, free vs paid reads, promotion impact, discounted vs normal sales. Aggregated only — no personal reading details.
- **Admin:** platform sales, active readers, writers, content, pending submissions, top categories/writers, suspicious activity, payment reports, gift card usage, wallet liability, promotion/discount performance, refunds.

## 30. Security & Anti-Abuse

- **30.1 Files:** private storage, never expose original URLs, signed temporary links only, protect converted pages, permission-checked access, log every session.
- **30.2 Accounts:** OTP/email verification, strong passwords for writers/admin, RBAC, session control, device/session limits, suspicious login detection.
- **30.3 Content abuse detection:** rapid page opening, too many devices, failed access attempts, shared-account behavior, abnormal patterns. Admin can suspend, block, revoke access, investigate logs.
- **30.4 Gift card/discount abuse detection:** repeated failed redemptions, many cards per user, suspicious transfers (if ever allowed), multi-account discount patterns, code abuse, refund-after-reading abuse, discount-on-gift-card attempts. RULE: no wallet-to-wallet transfer in MVP.

## 31. Copyright & Legal

Writer must agree: owns content/has permission; Akuru may publish; no copyright violation; Akuru may remove on complaint; payment/commission terms accepted; writer responsible for originality; agrees to promotion/discount/payout/refund rules. Required pages: Publishing Terms, Reader Terms, Gift Card Terms, Wallet Terms, Refund Policy, Copyright Policy, Privacy Policy, Writer Agreement, Promotion Policy.

## 32. Content Quality Rules

Minimum standards: clear title, good cover, correct category, proper language, no harmful content, no plagiarism, no copyright issues, proper formatting, clear author identity. **Islamic content: internal scholarly approval process before publishing.**

## 33. Technical Architecture

Build as a module inside the Laravel monolith. (Per ROADMAP §9.1 correction: domains collapse to `Library` + shared `Commerce`, reusing Finance/Media/Notifications/Identity/People.) Components: public library frontend, protected reader, reader dashboard, writer dashboard, admin dashboard, payment integration, file processing service, promotion engine, gift card service, wallet service, notifications, analytics.

## 34. Suggested Database Tables

(Commerce tables lose the `library_` prefix and move platform-wide per ROADMAP §9.1.)

- **Core content:** `library_items`, `library_item_versions`, `library_categories`, `library_tags`, `library_item_tag`, `library_item_authors` (link to People).
- **Writer:** `writer_profiles`, `writer_applications`, `writer_bank_details`, `writer_agreements`.
- **Submissions:** `library_submissions`, `library_submission_files`, `library_submission_comments`, `library_submission_status_history`.
- **Review:** `library_review_assignments`, `library_reviews`, `library_review_comments`.
- **Reader:** `library_access_grants`, `library_reading_progress`, `library_bookmarks`, `library_notes`, `library_highlights`, `library_reader_sessions`.
- **Payments/Sales:** `library_orders`, `library_order_items`, `library_purchases`, `library_sales`, `writer_earnings`, `writer_payouts`, `library_refunds`.
- **Commerce (platform-wide):** `gift_cards`, `gift_card_transactions`, `wallets`, `wallet_transactions`, `discount_codes`, `discount_redemptions`, `promotion_campaigns`, `campaign_items`, `order_discounts`, `free_access_coupons`, `coupon_redemptions`.
- **Protection/Logs:** `library_page_views`, `library_watermark_logs`, `library_suspicious_activity_logs`, `library_download_attempts`, `promotion_abuse_logs`.

## 35. Main Table Details

- **35.1 `library_items`:** id, title, slug, subtitle, description, abstract, content_type (book/article/research/course_material), access_type (free_public/free_login/paid/course/manual), price, currency, language, category_id, cover_image, status, published_at, created_by, approved_by, writer_id, page_count, reading_time, preview_enabled, preview_pages, commission_type, commission_value, timestamps.
- **35.2 `writer_profiles`:** id, user_id, display_name, bio, photo, qualifications, expertise, status, approved_at, approved_by, default_commission, payout_method, timestamps.
- **35.3 `library_reading_progress`:** id, user_id, library_item_id, current_page, current_chapter, progress_percent, last_read_at, completed_at, total_reading_seconds, timestamps.
- **35.4 `library_access_grants`:** id, user_id, library_item_id, access_type, source_type (purchase/course/admin/free/gift_card/wallet/coupon), source_id, starts_at, ends_at, status, timestamps.
- **35.5 `writer_earnings`:** id, writer_id, library_item_id, sale_id, gross_amount, discount_amount, discount_funding_source, wallet_amount, bml_amount, platform_commission, writer_amount, status (pending/available/paid/cancelled/refunded), available_at, paid_at, timestamps.
- **35.6 `gift_cards`:** id, **code_hash** (store hashed; show plain code once), original_amount, balance_amount, currency, purchaser_user_id, recipient name/email/mobile, message, status (active/redeemed/partially_used/empty/expired/deactivated), expires_at, created_by, timestamps.
- **35.7 `wallets`:** id, user_id, balance, currency, status, timestamps.
- **35.8 `wallet_transactions`:** id, wallet_id, user_id, type (credit/debit), source_type (gift_card/refund/admin/promotion/purchase), source_id, amount, balance_before, balance_after, description, expires_at, created_at. **Append-only.**
- **35.9 `discount_codes`:** id, code, name, discount_type (percentage/fixed/free_access), discount_value, max_discount_amount, starts_at, ends_at, usage_limit, per_user_limit, minimum_order_amount, applies_to_type (all/category/item/writer/course_students/new_users), discount_funding_source (shared/akuru/writer), can_combine, can_use_with_wallet, status, timestamps.
- **35.10 `promotion_campaigns`:** id, name, slug, description, banner_image, starts_at, ends_at, promotion_type, discount_type, discount_value, funding_source, auto_apply, status, timestamps.

## 36. File Processing (Phase 1 handling)

PDF: upload original to private storage → convert pages to protected page images or secure HTML → store privately → reader loads one page/chapter at a time with permission check → dynamic user watermark → original never exposed. Articles: structured HTML/JSON; protected reader if paid, normal web view if free public. Cover image upload; rich text editor for articles.

## 37. Recommended MVP

**Public:** library home, books/articles/research listings, categories, search, detail page, free preview. **Reader:** login-gated content, protected reader, progress, continue reading, bookmarks. **Payment:** BML for paid content, webhook access grant, purchase history, basic invoice. **Writer:** application, dashboard, upload book/article, submit for review, status, basic sales. **Admin:** approve writers, review submissions, publish/unpublish, price, commission, sales, earnings. **Promotions MVP:** basic discount codes, manual free access, simple gift card, wallet balance, basic redemption.

## 38. NOT in MVP

Full DOI, journal issue/volume system, complex peer review, subscriptions, offline paid reading, native app, advanced DRM, AI plagiarism checking, audiobooks, multi-institution licensing, complex bundles, loyalty points, wallet-to-wallet transfer, writer promotions without admin approval.

## 39. Phase-by-Phase (maps to ROADMAP L-phases)

1. **Foundation (L1):** item model, categories, tags, authors, public pages, detail page, admin upload, free reading, basic search.
2. **Protected Reader (L2):** reader, private storage, PDF-to-page conversion, watermark, no direct download, progress, continue, bookmarks.
3. **Paid Content (L3):** paid setup, BML flow, webhook, access grants, purchase history, invoice, sales report.
4. **Commerce (L4 — ships before writer phases per ROADMAP):** gift card purchase/redemption, wallet + history, discount codes, campaigns, manual credit, free-access coupons, reports, discount commission rules.
5. **Writer Portal (L5):** application, approval, profile, uploads, drafts, review submission, statuses, comments, revisions.
6. **Writer Sales & Payouts (L6):** sales dashboard, commission calc, earnings status, payout reports, admin payout management, monthly statements.
7. **Research Workflow (L7):** research metadata, reviewer role, assignment, comments, revision loop, accept/reject, citation display.
8. **Advanced (post-L7):** subscriptions, advanced coupons, bundles, institution access, DOI, ORCID, plagiarism checking, audio, PWA improvements, Capacitor app, offline free content, loyalty.

## 40. UI Sections

- **Reader menu:** Library, Books, Articles, Research, Authors, Promotions, Gift Cards, My Library, Continue Reading, My Wallet, Purchases.
- **Writer menu:** Dashboard, My Submissions, New Book/Article/Research, Sales, Earnings, Payouts, Profile, Promotion Requests (later).
- **Admin menu:** Library Dashboard, All Content, Submissions, Writers, Reviews, Categories, Sales, Earnings, Payouts, Gift Cards, Wallet, Discount Codes, Campaigns, Coupons, Reports, Settings.

## 41. Notifications

- **Reader:** purchase success, access granted, gift card received/redeemed, wallet credited, discount used, new content published, continue-reading reminder (optional).
- **Writer:** application decision, submission received, changes requested, approved, published, new sale, discount applied to content, payout processed.
- **Admin:** new writer application, new submission, payment issue, gift card purchase, large wallet adjustment, suspicious activity, copyright complaint.

## 42. Admin Settings

Default commission, minimum payout, allowed file types, max upload size, preview page limit, watermark text, session/device limit, refund policy, writer approval required, research review required, paid content on/off, free-content login on/off, gift cards on/off, wallet on/off, discount codes on/off, max gift card amount, gift card expiry rule, wallet credit expiry, discount combinability, **discounts-on-gift-cards (recommended: OFF)**, default funding source.

## 43. Important Business Rules (the 20)

1. Writer cannot publish directly. 2. Admin must approve writer. 3. Admin must approve content. 4. Paid content unreadable before payment. 5. Access depends on BML webhook, not return URL. 6. Original PDF never public. 7. Earnings not payable while refunds possible. 8. Reader notes private. 9. Writers see aggregates only. 10. Admin can revoke access in special cases. 11. Keep payment and access logs. 12. Every paid content page watermarked. 13. Gift cards are payment, not discount. 14. Wallet is payment, not discount. 15. Discount codes reduce price. 16. Discounts never apply to gift card purchases. 17. Admin chooses funding source: shared/Akuru/writer. 18. Promotional credit not refundable to cash. 19. Gift card codes stored securely (hashed). 20. Wallet transactions never deleted — reversal transactions only.

## 44. Launch Strategy

1. Akuru-owned content only (free Arabic/Qur'an articles, paid course notes, small books). 2. Enable paid purchases (protected reader + BML checkout). 3. Invite trusted writers only (Akuru teachers, scholars, Arabic teachers, education specialists). 4. Add gift cards + basic discounts. 5. Open writer applications to public (only after approval workflow proven). 6. Add research workflow last.

## 45. Best Practical Decision

Build inside Akuru as a module, not a separate website: same users, student/parent accounts, BML, admin panel, brand; easy course connections, student reading materials, course-related book sales, cross-Akuru wallet/gift cards. Structure cleanly: **Akuru LMS + Akuru Knowledge Library + Commerce (Promotions & Wallet)**.

## 46. Final MVP Scope

Public library pages; admin upload; free + paid content; BML payment access; protected web reader; reading progress; bookmarks; writer application; writer upload portal; admin review/approval; writer sales dashboard; admin commission report; basic discount codes; admin manual free access; simple gift card; Akuru wallet balance; basic promotion reporting.

## 47. Final Summary

A proper publishing, reading, and digital commerce platform — not a file upload system. Students and the public read; writers publish after approval; Akuru sells protected educational content; writers earn; admin controls quality, price, and publishing; users track progress; gift cards and wallet work platform-wide; campaigns grow sales. First version: **Library + Protected Reader + BML Payment + Writer Portal + Admin Approval + Sales Dashboard + Basic Discounts + Gift Cards + Wallet.** Later: peer review, journals, DOI, subscriptions, bundles, mobile app, audiobooks, institutional access, loyalty.
