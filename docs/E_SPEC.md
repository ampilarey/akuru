# Phase E — Public website (E1–E3 = W1–W3)

Website domain. Engine core stays subject-ignorant. Hifz frozen. New Website files import other domains via Actions/Contracts only.

## E1 — W1 conversion layer

### W1.1 — Urgency from existing data (this slice)

Course cards + course detail, from columns that already exist:

- **Seats left:** `seats − occupying enrollments` (`pending` + `active`, matching checkout `isFull()`). Thresholds in Settings group `conversion` (defaults: hide above 20, exact “N seats left” at ≤10, “Limited seats” 11–20, “Full — join waiting list” at 0). `seats` null → show nothing.
- **Deadline:** `enrollment_deadline` date + “X days left” when within 14 days. Expired **open** courses are hidden from Open Courses / default public listing.
- **Early bird:** `courses.meta.early_bird_*` (no new columns). Shown only when active, dated, and cheaper than `fee`.
- **Waiting list:** public POST `/courses/{course}/waitlist` → `contact_inquiries` with `meta.source=waiting_list` and `course_id` (table was missing despite the model; additive).

Never invent scarcity. Pest covers null / limited / exact / full / expired / early-bird / waitlist 422 when not full. Browser walk: homepage Limited seats + 7 seats left + null-seats silent; waitlist inquiry stored.

### W1.2 — Trust above the fold

Homepage hero reads Settings group `trust_settings` (merged #113).

### W1.3 — Outcome-led course pages

Merged #114.

### W1.4 — Mobile CTA + lead capture (this slice)

- Sticky mobile course bar: price + **Register** + WhatsApp icon (existing bar; not a second bar).
- **Ask on WhatsApp** `https://wa.me/<digits>?text=<course title>`. Per-course `courses.whatsapp_number`, else Settings `conversion.whatsapp_number`, else existing contact `viber`. Blank at every layer omits the button.
- **Get full syllabus** mini-form (name + mobile, email optional) when `courses.syllabus_media_file_id` points at a **public** `media_files` row. Stores `leads` (`source=syllabus`) then flashes the PDF URL.
- Waiting-list posts also dual-write `leads` (`source=waiting_list`).
- Admin Blade listing `/admin/public-site/leads` + CSV. Website-owned until Phase 0 Admissions move.

Engine subject-ignorant. Hifz untouched. New Website files do not import Courses Models.

### W1.5–W1.6 (next)

JSON-LD/OG/hreflang/sitemap, funnel events.

## E2 — W2 daily content (next phase)

Ayah/hadith/reminder engine reusing `surahs` / `quran_ayahs`. No parallel Quran tables.

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
