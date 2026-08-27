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

### W2.4 — Subscriptions & delivery (this slice)

- `daily_content_subscriptions` (`user_id`, `channel` sms|email|push, `content_types` json, `language` en|dv, `send_time` default 06:00, `status` active|paused, unique user+channel) plus unsubscribe token/reason. `daily_content_deliveries` unique (subscription, send_date). No `academic_year_id` (website operational log, same exemption as `leads` / `daily_contents`).
- Opt-in only from `/daily/subscribe` (auth). Guests cannot opt in. User A cannot pause user B. Creating a user does not auto-subscribe.
- `daily-content:deliver` every 15 minutes `Indian/Maldives`: one message per channel per day; compose **today’s published** items only (not fallback); skip silently with **no** delivery row when nothing is published so a later run can still send; SMS via `SmsSenderInterface` short text + permalink + STOP (never full Arabic); email via Laravel Mail; **push ignored** (schema ready).
- Unsubscribe: public token GET pauses immediately; public STOP/UNSUBSCRIBE keyword on verified mobile pauses SMS immediately and is logged.
- Identity Actions only (`ReadVerifiedUserContactsAction`, `FindUserIdByVerifiedMobileAction`). Website does not import Identity/Notifications/Hifz/Courses models or `SmsGatewayService`.
- Admin `/admin/public-site/daily-subscriptions` + CSV under `daily_content.manage`. No new Spatie permission. No AppShell link.

### W2.5 (next)

Research posts: extend `posts` with `post_type`, authors, abstract (`docs/W2_SPEC.md`).

## E3 — W3 prayer times (next phase)

`docs/W3_SPEC.md`. `PrayerTimeProviderInterface`. No live SMS.
