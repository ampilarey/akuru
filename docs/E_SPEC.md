# Phase E — Public website (E1–E3 = W1–W3)

Website domain. Engine core stays subject-ignorant. Hifz frozen. New Website files import other domains via Actions/Contracts only.

## E1 — W1 conversion layer

### W1.1 — Urgency from existing data

Merged #112.

### W1.2 — Trust above the fold

Homepage hero reads Settings group `trust_settings` (merged #113).

### W1.3 — Outcome-led course pages

Merged #114.

### W1.4 — Mobile CTA + lead capture

Merged #115.

### W1.5 — SEO + sharing

Merged #116.

### W1.6 — Funnel measurement

Merged #117.

## E2 — W2 daily content

### W2.1 — Quran translations

Merged #118.

### W2.2 — Daily content store

Merged #119.

### W2.3 — Public display

Merged #121.

### W2.4 — Subscriptions & delivery

- `daily_content_subscriptions` (`user_id`, `channel` sms|email|push, `content_types` json, `language` en|dv, `send_time` default 06:00, `status` active|paused, unique user+channel) plus unsubscribe token/reason. `daily_content_deliveries` unique (subscription, send_date). No `academic_year_id` (website operational log, same exemption as `leads` / `daily_contents`).
- Opt-in only from `/daily/subscribe` (auth). Guests cannot opt in. User A cannot pause user B. Creating a user does not auto-subscribe.
- `daily-content:deliver` every 15 minutes `Indian/Maldives`: one message per channel per day; compose **today’s published** items only (not fallback); skip silently with **no** delivery row when nothing is published so a later run can still send; SMS via `SmsSenderInterface` short text + permalink + STOP (never full Arabic); email via Laravel Mail; **push ignored** (schema ready).
- Unsubscribe: public token GET pauses immediately; public STOP/UNSUBSCRIBE keyword on verified mobile pauses SMS immediately and is logged.
- Identity Actions only (`ReadVerifiedUserContactsAction`, `FindUserIdByVerifiedMobileAction`). Website does not import Identity/Notifications/Hifz/Courses models or `SmsGatewayService`.
- Admin `/admin/public-site/daily-subscriptions` + CSV under `daily_content.manage`. No new Spatie permission. No AppShell link.

Merged #124.

### W2.5 — Research & publications

Merged #126.

## E3 — W3 prayer times (this slice)

`docs/W3_SPEC.md`. ADR-004.

- New `PrayerTimes` domain + `PrayerTimesServiceProvider`. Tables: `prayer_categories`, `prayer_islands`, `prayer_times` (366-day leap table, minutes-since-midnight), `prayer_recipient_groups`, `prayer_broadcasts`, `prayer_broadcast_recipients`. No `academic_year_id` (rule 10 exemption, ADR-004).
- `prayer:import` from operator-supplied `salat.db` (fails unless every category has 366 rows). Local/staging seeder is a **synthetic** 366-day fixture (Malé, Hulhumalé +2, Hithadhoo) — Bake&Grill `salat.db` is not in the repo.
- Resolver: leap-year day index (`dayOfYear+1` from day 60 in non-leap years), island offset, `HH:MM` `Indian/Maldives`, versioned cache (never cache null), Haversine nearest-island.
- `PrayerTimeProviderInterface` — Website and Portal consume only the contract. `IslamicCalendarService::getPrayerTimes()` / `getCurrentPrayerTime()` marked `@deprecated`. Hijri still from `IslamicCalendarService`.
- Public Blade `/prayer-times` + `GET /api/v1/prayer-times` + homepage widget. Admin Blade `/admin/prayer-times/*` (`prayer.manage`). Spec said Inertia; public site is still Blade. No AppShell link (wrap stays 83).
- SMS: preview → confirm → queue. Daily / range (split when times change) / change-only. Consent `prayer_reminders` via People Actions with **strings** (PrayerTimes must not import `ConsentType`). STOP via `HonorPrayerUnsubscribeKeywordAction`. `SmsSenderInterface` only — no live SMS.
- Scheduler: `prayer:run-daily` every 15 min, `prayer:run-change-only` 20:00, `Indian/Maldives`.

**Phase E complete** when this PR merges. Next is F1 (Hifz → engine). Do not start F in the Phase E report turn.
