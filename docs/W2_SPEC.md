# W2 Spec — Islamic Daily Content Engine

**Phase:** W2 (after Phase 0 — needs domain structure + notification contracts; PWA push arrives with 1B, SMS/email work immediately)
**Domains:** Website (primary), Notifications, Media, Hifz (read-only Quran source tables)
**Principle:** one curated engine for ayah / hadith / saying / reminder — not four features. Content integrity rules are non-negotiable.

---

## Slice W2.1 — Quran translations (foundation)

**New `quran_translations`:** `id, quran_ayah_id FK (existing table — §2b rule: never duplicate Quran sources), language enum(en, dv, ar_tafsir), text, source_name (e.g. translation edition), source_note nullable, verified_by nullable, timestamps, unique(ayah, language, source_name)`

Import command for a licensed/public-domain Dhivehi + English translation set (admin chooses edition; record license in ADR-009). Read-only contract `QuranTextProviderInterface` (surah/ayah lookup + translation) exposed by the Hifz/Quran domain — W2 and future engine components consume it; nobody touches the tables directly.

## Slice W2.2 — Daily content store

**New `daily_contents`:** `id, content_type enum(ayah, hadith, saying, reminder), publish_date date unique-per-type, status enum(draft, scheduled, published, archived),`
- ayah: `quran_ayah_id FK nullable` (translations auto-attach via W2.1)
- hadith: `hadith_text_ar, hadith_text_en, hadith_text_dv, hadith_collection (Bukhari, Muslim, …), hadith_number, hadith_grading (sahih, hasan, …), grading_source`
- saying/reminder: `text_en, text_dv, text_ar nullable, attribution (who said it / source)`
- shared: `theme_tag nullable (ramadan, parenting, knowledge…), notes_internal, created_by, approved_by nullable, timestamps`

**Integrity rules (validated, not advisory):**
1. Hadith rows REQUIRE collection + number + grading + grading_source — cannot publish without all four.
2. All Islamic content requires `approved_by` (a designated scholarly-review role, permission `daily_content.approve`) before status can become scheduled/published. Maker–checker: creator ≠ approver.
3. No auto-generation, no scraping — admin curation only (rule lives in WORKING_RULES too).

Admin UI: calendar view (month grid, one cell per day per type), create/edit with live preview in all three languages, approval queue, theme batches (e.g. prepare 30 Ramadan reminders), archive search.

## Slice W2.3 — Public display

- **Homepage widget:** today's content (type rotation or all types stacked — Settings), trilingual with RTL, graceful fallback to most recent published if today empty.
- **Archive pages:** /daily/[type] with date browse + theme filter; per-item permalink page (SEO: Article schema, no Quran-text indexing concerns — verify robots policy in ADR-009).
- **Share cards:** `GenerateShareCardAction` renders a 1080×1080 image per item (template per type: ayah card with Arabic + translation, hadith card with source line ALWAYS on the image — attribution travels with the share) via the image pipeline; WhatsApp/Twitter share buttons + OG image. Cards pre-rendered on publish (queued), stored via Media.
- **Prayer times + Hijri widget:** `PrayerTimesProviderInterface` — first implementation: Maldives official timetable data (admin-importable CSV per island/atoll zone, setting selects zone) rather than calculation library, falling back to calculation (configurable method) if no table loaded. Hijri date via calendar conversion with admin offset setting (moon-sighting adjustment, ±1 day).

## Slice W2.4 — Subscriptions & delivery

**New `daily_content_subscriptions`:** `id, user_id FK, channel enum(sms, email, push), content_types json, language enum(en, dv), send_time time default 06:00, status enum(active, paused), timestamps, unique(user, channel)`

Daily scheduled job: for each due subscription, send today's published items of subscribed types via the channel contracts (`SmsSenderInterface` etc.). Rules: one message per channel per day (types combined); skip silently if nothing published (never send placeholders); SMS uses short text + link for ayah/hadith (full Arabic in SMS is unreliable — link to permalink); unsubscribe link/keyword honored immediately (and logged). Push channel activates when 1B's service worker ships — schema ready now. Opt-in only from Portal/website — never auto-subscribe (consent rule).

Metrics: subscriber counts per channel/type, delivery failures surfaced to admin.

## Slice W2.5 — Research & publications (light)

Extend existing `posts`/articles: add `post_type enum(article, news, research)`, `authors` (json of instructor/staff refs or external names), `pdf_document_id FK nullable` (Media), `abstract nullable`, `citation_note nullable`. Research listing page with author + year filters; author pages reuse instructor profiles. **Boundary:** this is the free front door only — paid/peer-reviewed publishing belongs to the L-track (L1/L7); when L1 ships, research posts can migrate or link into the Library catalog (decision deferred to L1, noted in STATUS).

## Tests (CI gates)
1. Hadith integrity: publish blocked without collection/number/grading/source; maker–checker enforced.
2. Ayah content pulls correct text + translations via the provider contract (golden ayah test, RTL snapshot).
3. Scheduler: publishes on date, widget fallback when empty, no duplicate sends, nothing sent on empty day, unsubscribe immediate.
4. Share card golden render (ayah + hadith templates, Thaana font verified) with attribution present.
5. Prayer-times zone selection + Hijri offset behavior.
6. Subscription permission: self-manage only; opt-in required.

## DoD
- [ ] A month of content scheduled via calendar UI; today's ayah (with Dhivehi + English meaning), a graded hadith, and a saying render on the homepage in all three languages; share card posts cleanly to WhatsApp; an SMS subscriber receives the 6 a.m. message; prayer times + Hijri date show for the configured zone.
- [ ] Approval workflow exercised by two different users (maker–checker proven).
- [ ] ADR-009 (translation edition + license, robots policy, Hijri source) recorded; STATUS.md updated.

**Out of scope:** paid content, writer submissions (L-track), push delivery before 1B, auto-generated content of any kind, fiqh Q&A.
