# W3 Spec — Prayer Times Engine + Prayer-Time SMS Broadcast

**Phase:** W3 (after Phase 0 — like W2; no S1 academic backbone required for the data engine; **broadcast** needs S1 `consents` + People/Identity contact contracts)
**Domains:** PrayerTimes (primary), Notifications (SMS send), Settings (defaults), Website (public page/widget consumer), Portal (admin dashboard prayer box)
**Blueprint:** replicate the proven **Bake&Grill** prayer-times model — same schema shape, `salat.db` import, leap-year rule, versioned cache, Haversine nearest-island. Do not reinvent.
**Principle:** real Maldives island data replaces `IslamicCalendarService::getPrayerTimes()` hardcoded placeholders; SMS is opt-in, previewed, audited, and **never** hits a live gateway in tests.

All schema below is **shipped** (PR #128). Operator supplies `salat.db`; local/staging seed is a synthetic 366-day fixture.

---

## Slice W3.1 — Data tables + import

### `prayer_categories` *(PLANNED)*

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Matches `salat.db` Category id |
| `created_at`, `updated_at` | timestamps | |

One row per prayer-time zone/category in the source dataset (typically one per atoll group). No trilingual labels required at category level — islands carry display names.

### `prayer_islands` *(PLANNED)*

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Matches source Island id |
| `category_id` | FK → `prayer_categories.id` | Zone for timetable lookup |
| `atoll` | string | Thaana atoll name (admin-editable seed) |
| `atoll_latin` | string | Latin transliteration |
| `name` | string | Island name (Thaana) |
| `name_latin` | string | Latin transliteration |
| `offset_minutes` | integer, default 0 | Applied after category lookup |
| `latitude` | decimal(10,7) | For Haversine nearest-island |
| `longitude` | decimal(10,7) | |
| `is_active` | boolean, default true | Inactive islands hidden from pickers |
| `created_at`, `updated_at` | timestamps | |

Ship the **full Maldives island dataset** (admin-editable seeds) so SMS messages can name the location. Akuru is single-institute: default home island is **Malé** (Settings — see W3.4).

### `prayer_times` *(PLANNED)*

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `category_id` | FK → `prayer_categories.id` | |
| `day_of_year` | smallint, 1–366 | Leap-year calendar index in source |
| `fajr` | integer | Minutes since midnight |
| `sunrise` | integer | |
| `dhuhr` | integer | |
| `asr` | integer | |
| `maghrib` | integer | |
| `isha` | integer | |
| `created_at`, `updated_at` | timestamps | |
| **unique** | `(category_id, day_of_year)` | |

All six times stored as **integer minutes-since-midnight** (Bake&Grill model). Display layer converts to `HH:MM` in **Indian/Maldives** (`Asia/Male`, UTC+5, no DST).

### Import: `php artisan prayer:import`

- **Source:** `salat.db` SQLite file (Bake&Grill dataset path configurable; not committed to repo — operator supplies file).
- Reads `Category`, `Island`, `PrayerTimes` tables from SQLite; upserts into MySQL equivalents above.
- **Validation (import MUST fail):** every `category_id` must have exactly **366** rows in `prayer_times`. Abort with a clear error listing short categories.
- After successful import: bump cache version (W3.2) and log row counts.
- Seeder (`PrayerTimesDatabaseSeeder`) may call the same import logic for local/staging seeds — examples are admin-editable, never hardcoded enums.

### Rules

1. Timezone for all prayer resolution: `config('app.timezone')` = `Indian/Maldives`.
2. No prayer calculation library in v1 — timetable data only.
3. Import is idempotent (safe re-run).

---

## Slice W3.2 — Resolver, leap-year rule, caching

### `PrayerTimeResolver` (domain service)

**Lookup:** `category_id` + `day_of_year` → base six times → add island `offset_minutes` to each → convert minutes → `HH:MM` strings + Carbon instances for consumers.

### Leap-year rule *(CRITICAL — Bake&Grill)*

Source `prayer_times` is a **366-row leap-year table**. For **non-leap** Gregorian years:

- Use PHP `dayOfYear` as-is for days 1–59 (Jan 1 – Feb 28).
- From **day 60 onward** (Mar 1+), use **`dayOfYear + 1`** when indexing `prayer_times`.

Without this offset, March–December times read one day early in non-leap years.

### Caching

- Cache key prefix: `prayer_times:v{version}:` where `{version}` is an integer in Settings (`prayer_times_cache_version`), bumped on import or `prayer:cache-clear`.
- Cached value: resolved times DTO/array for `(island_id, date)`.
- **Never cache a null lookup** — miss goes to DB; if still null, return explicit “unavailable” without writing cache.
- Version bump invalidates all prayer caches **atomically** without `Cache::flush()`.

### Nearest island (Haversine)

`FindNearestIslandAction(lat, lng)` → active island with minimum great-circle distance. Used by “use my location” on the public page and as fallback when geolocation is allowed.

### Rules

1. Resolver never throws for unknown island — returns structured error for UI.
2. Offset applies equally to all six times (including sunrise).
3. Unit tests must cover leap vs non-leap boundary (Feb 28, Mar 1, Dec 31).

---

## Slice W3.3 — Domain contract + cross-domain access

### `PrayerTimeProviderInterface` *(PrayerTimes/Contracts)*

```text
resolveForIsland(int $islandId, Carbon $date): PrayerTimesDTO
  // six named times + island label (en/dv/ar where available) + date

listIslands(?bool $activeOnly = true): Collection<IslandDTO>

findNearestIsland(float $latitude, float $longitude): IslandDTO

compareRange(int $islandId, Carbon $from, Carbon $to): PrayerRangeComparisonDTO
  // used by broadcast mode 2 — see W3.6
```

**Boundary rule:** Website, Portal, Notifications jobs, and W2 widgets consume **only** this contract — never `PrayerTimes\Models\*`, never `IslamicCalendarService::getPrayerTimes()`.

Container binding in `PrayerTimesServiceProvider`. Architecture test: no cross-domain import of PrayerTimes models.

### Deprecate hardcoded prayer times

| Keep | Deprecate / replace |
|---|---|
| `IslamicCalendarService::gregorianToHijri()`, `getCurrentIslamicDate()`, Hijri month helpers, `getSpecialIslamicDays()` | `IslamicCalendarService::getPrayerTimes()` |
| Hijri offset setting (moon-sighting ±1 day) stays on calendar service or Settings | `getCurrentPrayerTime()` — reimplement atop `PrayerTimeProviderInterface` (or move to PrayerTimes domain helper) |

**Admin dashboard prayer box** (`Portal` dashboards): switch to `PrayerTimeProviderInterface` with Settings default island (Malé).

---

## Slice W3.4 — Settings, public page, JSON API

### Settings *(PLANNED keys — admin-editable)*

| Key | Default | Purpose |
|---|---|---|
| `prayer.default_island_id` | Malé island row | Home page + dashboard default |
| `prayer_times_cache_version` | 1 | Cache invalidation bump |
| `prayer.public_page_enabled` | true | Feature toggle |

### Public UI (Website — Inertia + React)

- **`/prayer-times`** — trilingual (EN/DV/AR), RTL-safe: island picker (searchable), today’s six times, Hijri + Gregorian date (Hijri via `IslamicCalendarService`, not duplicated).
- **“Use my location”** — browser geolocation → `findNearestIsland` → show times (permission denied → default island).
- **JSON API** — `GET /api/v1/prayer-times?island_id=&date=` (and `lat`/`lng` optional); stable DTO shape for mobile/PWA later.

### Rules

1. Labels for prayers (Fajr, Sunrise, Dhuhr, Asr, Maghrib, Isha) are translatable strings — not hardcoded in views.
2. CSV export on admin island listing (WORKING_RULES convention).

---

## Slice W3.5 — Recipients, groups, consent

### Recipients — single source of truth

- Resolve contacts **only** via People/Identity **contracts** (guardians, staff, students with phone on file, admission contacts — whatever the platform exposes as `ContactRef` / phone-bearing DTO).
- **Never** maintain a parallel prayer-only address book.

### `prayer_recipient_groups` *(PLANNED)*

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name_en`, `name_dv`, `name_ar` | string | Admin-editable group label |
| `description` | text nullable | |
| `member_refs` | json | Serialized contact refs (contract shape) — snapshot refreshed on group edit |
| `created_by` | FK users | |
| `is_active` | boolean | |
| `created_at`, `updated_at` | timestamps | |

Broadcast may use `recipient_group_id` **or** ad-hoc `recipient_refs` json on the broadcast row (mutually exclusive; ad-hoc copied to `prayer_broadcast_recipients` at send time).

### Consent (S1 `consents` table)

Extend `consent_type` with **`prayer_reminders`** (preferred) — distinct from `marketing_messages` so prayer SMS is not bundled with marketing opt-in.

| Rule | Detail |
|---|---|
| Gate | No SMS unless `prayer_reminders` granted and `revoked_at` null |
| Minors | `granted_by` = guardian user (S1 pattern) |
| STOP / opt-out | Honor keyword; write new consent row `granted=false`; stop future sends immediately |
| Default | **No recipient is messaged unless explicitly selected and consented** |

---

## Slice W3.6 — Prayer-time SMS broadcast

Feature lives in **PrayerTimes** (orchestration, preview, audit); **sends via** `Notifications\Contracts\SmsSenderInterface` only — never the concrete SMS gateway (WORKING_RULES §4).

### `prayer_broadcasts` *(PLANNED)*

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `mode` | enum: `daily`, `range`, `change_only` | See modes below |
| `island_id` | FK → `prayer_islands.id` | Location named in message |
| `date_from` | date nullable | Range / change-only anchor |
| `date_to` | date nullable | Range end |
| `scheduled_at` | datetime nullable | Daily mode: scheduler fire time |
| `status` | enum: `draft`, `previewed`, `queued`, `sending`, `completed`, `failed`, `cancelled` | |
| `created_by` | FK users | |
| `message_template` | json | Per-language template + merge fields |
| `recipient_group_id` | FK nullable | |
| `recipient_refs` | json nullable | Ad-hoc selection snapshot |
| `language` | enum: `en`, `dv`, `ar` | Primary SMS language per recipient policy |
| `sent_count` | integer default 0 | |
| `failed_count` | integer default 0 | |
| `estimated_cost` | decimal(10,2) nullable | Laari or MVR per ADR — preview fills this |
| `preview_snapshot` | json nullable | Frozen preview payload at confirm time |
| `idempotency_key` | string unique nullable | Scheduler re-run guard |
| `created_at`, `updated_at` | timestamps | |

**Append-only audit:** rows are never hard-deleted; cancel = status change. Corrections = new broadcast row.

**Rule 10 exemption:** `prayer_broadcasts` and `prayer_broadcast_recipients` are **operational notification logs**, not academic records — they do **not** carry `academic_year_id` (confirmed in ADR-004).

### `prayer_broadcast_recipients` *(PLANNED)*

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `prayer_broadcast_id` | FK | |
| `contact_ref` | json | People/Identity contract snapshot (type, id, phone hash) |
| `phone` | string | E.164 at send time |
| `status` | enum: `pending`, `sent`, `failed`, `skipped` | `skipped` = no consent / duplicate idempotency |
| `message_body` | text nullable | Exact text sent (audit) |
| `cost` | decimal(10,2) nullable | |
| `sent_at` | datetime nullable | |
| `error` | text nullable | |
| `created_at`, `updated_at` | timestamps | |

### Three send modes

#### 1. Daily reminder (`daily`)

- Laravel scheduler + **queued job** at admin-set `scheduled_at` (e.g. 05:30 Maldives).
- Recipients: selected groups and/or ad-hoc list (all consent-filtered).
- Message includes: all **six** times (Fajr … Isha), island/location name, Hijri + Gregorian date.
- Trilingual templates — admin picks primary language per broadcast; preview shows all three.

#### 2. Date-range broadcast (`range`)

- Admin picks `date_from`–`date_to` (e.g. 23–27 June) + island.
- **`ComparePrayerTimesAcrossRangeAction`** (via `PrayerTimeProviderInterface::compareRange`):
  - If times are **identical** (minute-level) for every day in range → **one SMS**: e.g. `23–27 Jun: Fajr 04:48, …`
  - If times **change** within range → admin UI **warns** and offers **split per change-block** (auto-suggest contiguous sub-ranges with identical times).
- No silent merge across changing days.

#### 3. Change-only reminder (`change_only`)

- Scheduled job compares **tomorrow** vs **today** for the configured island.
- Send **only** when any prayer time differs at minute resolution.
- Suppresses duplicate daily messages when the timetable is unchanged.

### Sending rules

| Rule | Detail |
|---|---|
| Queue | `SendPrayerBroadcastJob` batched; rate-limited per gateway policy |
| Idempotency | `idempotency_key` = `{mode}:{date}:{island_id}:{recipient_hash}` (daily) or broadcast id — scheduler re-run must not double-send |
| SMS channel | `SmsSenderInterface` only |
| Cost | Preview calculates `estimated_cost`; store actuals per recipient row |

---

## Slice W3.7 — SMS safety (non-negotiable DoD)

> **These items are release blockers for any W3 broadcast code.**

### Test isolation (closes repo-wide gap)

1. **`testing` environment:** bind `SmsSenderInterface` to a **fake/null implementation** that records messages in memory — never calls Dhiraagu/gateway HTTP.
2. **Test base:** `Http::preventStrayRequests()` in `tests/TestCase.php` (or Pest `beforeEach`) so no test accidentally hits external APIs.
3. Architecture/contract test: PrayerTimes domain code has **zero** imports of concrete `SmsGatewayService`.

### Mandatory dry-run / preview

Before **every** send:

- Show recipient count (after consent filter), excluded count with reasons.
- Show **exact** rendered message per language (EN/DV/AR).
- Show **estimated cost** (recipients × tariff setting).
- **No send without explicit admin confirm** — status must pass through `previewed` → confirm → `queued`.

`PreviewPrayerBroadcastAction` writes `preview_snapshot` on the broadcast row.

### Audit

Full log per broadcast: who created, who confirmed, when, island, date range, template, each recipient, body, cost, result. Admin CSV export.

### Default to nothing

Empty recipient selection → preview shows zero sends; confirm button disabled. No “send to all” shortcut without group + consent review.

---

## Slice W3.8 — Admin UI

Inertia + React under Settings or dedicated PrayerTimes admin (permission `prayer.manage`):

| Screen | Purpose |
|---|---|
| Islands | List/search, CSV export, import status, cache version |
| Import | Upload/trigger `prayer:import`, show last result |
| Recipient groups | CRUD, member picker via People/Identity contact search |
| Broadcasts | Create wizard (mode → island → dates → recipients → preview → confirm) |
| Broadcast log | Read-only audit, filter by date/status/mode |

RTL + trilingual labels throughout. Maker distinct from confirmer optional but recommended for bulk range sends.

---

## Actions (summary)

| Action | Slice |
|---|---|
| `ImportPrayerTimesFromSalatDbAction` | W3.1 |
| `BumpPrayerTimesCacheVersionAction` | W3.2 |
| `ResolvePrayerTimesForIslandAction` | W3.2 |
| `FindNearestIslandAction` | W3.2 |
| `ComparePrayerTimesAcrossRangeAction` | W3.6 |
| `BuildPrayerSmsMessageAction` | W3.6 |
| `FilterConsentedRecipientsAction` | W3.5 |
| `PreviewPrayerBroadcastAction` | W3.7 |
| `ConfirmPrayerBroadcastAction` | W3.7 |
| `SendPrayerBroadcastJob` (queued) | W3.6 |
| `RunDailyPrayerReminderJob` / `RunChangeOnlyPrayerReminderJob` | W3.6 |

---

## Tests (CI gates)

1. Import fails when any category ≠ 366 rows; succeeds on valid `salat.db` fixture.
2. Leap-year: Mar 1 non-leap uses `dayOfYear + 1`; leap year does not.
3. Island offset applied correctly; minutes → `HH:MM` in Maldives TZ.
4. Cache: version bump misses old keys; null lookup not cached.
5. Haversine: nearest island golden coordinates test (Malé vs nearby).
6. `PrayerTimeProviderInterface` contract test — Website/Portal resolve via container binding only.
7. Range compare: unchanged range → single block; changing range → multiple blocks suggested.
8. Change-only: send when tomorrow differs; skip when identical.
9. Consent: non-consented contact excluded; STOP revokes future sends.
10. **SMS safety:** fake sender bound in tests; `Http::preventStrayRequests()`; send job never calls real gateway (assert fake inbox only).
11. Idempotency: duplicate scheduler tick does not double-send.
12. Preview required: cannot queue from `draft` without `previewed` snapshot.

---

## Definition of Done

- [ ] `prayer:import` loads full Maldives dataset; every category has 366 rows; staging shows correct times for Malé today vs Bake&Grill reference.
- [ ] Public `/prayer-times` + JSON API work with island picker and geolocation; admin dashboard prayer box uses `PrayerTimeProviderInterface` (hardcoded `getPrayerTimes()` unused).
- [ ] Hijri date on prayer UI still from `IslamicCalendarService` (unchanged).
- [ ] Daily, range, and change-only broadcasts: preview → confirm → queued send; audit log complete; consents honored.
- [ ] **SMS safety DoD met:** fake `SmsSenderInterface` in `testing`, `Http::preventStrayRequests()`, zero stray gateway calls in CI.
- [ ] ADR-004 recorded; `docs/ROADMAP.md` + `STATUS.md` updated after implementation slices.

**Out of scope:** prayer-time *calculation* libraries; multi-country zones; push/email prayer reminders (SMS only in W3); W2 daily-content SMS (separate feature, same `SmsSenderInterface`).

**W2 coordination:** W2 §W2.3 prayer widget should consume `PrayerTimeProviderInterface` from this domain when W3 ships — supersedes the interim CSV/calculation approach in `docs/W2_SPEC.md`.
