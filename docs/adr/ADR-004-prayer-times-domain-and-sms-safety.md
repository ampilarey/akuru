# ADR-004: PrayerTimes domain + salat.db data source + prayer-SMS consent/safety

## Context

Akuru today shows **hardcoded** prayer times via `App\Support\Services\IslamicCalendarService::getPrayerTimes()` (fixed Malé-ish placeholders). Admin dashboards and future public widgets need **real Maldives per-island timetables**. The proven **Bake&Grill** implementation already solved this with `salat.db` → category/island/366-day tables, leap-year indexing, island offsets, and versioned cache — reinventing would add risk.

Prayer times are **not** in the Phase 0 domain skeleton. A dedicated domain keeps the engine and Website CMS from owning timetable logic. Separately, operators want **SMS broadcasts** of daily or range prayer schedules — high impact but high risk if tests hit the live SMS gateway (a known repo-wide gap: `SmsSenderInterface` exists but tests do not universally bind a fake).

Broadcast recipients must come from **People/Identity** contacts (single source of truth) and honor S1 **`consents`** — prayer reminders are not generic marketing.

`prayer_broadcast*` tables are operational notification logs, not student academic records — they should not carry `academic_year_id` (WORKING_RULES §10 exemption).

## Decision

1. **New `Domains/PrayerTimes`** owning:
   - Data tables: `prayer_categories`, `prayer_islands`, `prayer_times` (PLANNED schema per `docs/W3_SPEC.md`).
   - Import: `php artisan prayer:import` from `salat.db`; import **fails** if any category ≠ 366 rows.
   - Resolver with Bake&Grill **leap-year rule** (add 1 to `dayOfYear` from day 60 in non-leap years).
   - Versioned cache prefix (`prayer_times_cache_version` in Settings); never cache null lookups.
   - Haversine nearest-island lookup.
   - Public contract **`PrayerTimeProviderInterface`** — all cross-domain consumers (Website, Portal, W2 widget) use only this interface.

2. **Deprecate** `IslamicCalendarService::getPrayerTimes()` and prayer-box reads of it; **keep** Hijri conversion helpers unchanged. Default island = Malé via Settings (`prayer.default_island_id`).

3. **Prayer-time SMS broadcast** orchestrated in PrayerTimes; delivery via **`Notifications\Contracts\SmsSenderInterface`** only. Modes: daily scheduled, date-range (with compare/split helper), change-only. Tables: `prayer_broadcasts`, `prayer_broadcast_recipients` — append-only audit.

4. **Consent:** add `prayer_reminders` to S1 `consents.consent_type` when W3 ships; no send without explicit selection + consent; honor STOP/opt-out.

5. **SMS safety (non-negotiable):** in `testing`, bind fake `SmsSenderInterface`; add `Http::preventStrayRequests()` to the test base; mandatory dry-run preview before every send.

6. **Timezone:** `Indian/Maldives` everywhere for prayer resolution and scheduler jobs.

## Consequences

- **Easier:** Accurate local prayer times; one blueprint to maintain; clear domain boundary; W2/W3/Portal share one provider; SMS audits and preview reduce operator error.
- **New work:** `PrayerTimesServiceProvider`, migrations, import command, public page + API, broadcast admin UI, scheduler jobs, S1 consent enum migration, test-base hardening (benefits all SMS features).
- **W2:** `docs/W2_SPEC.md` §W2.3 interim CSV/calculation note is superseded by W3 — widget calls `PrayerTimeProviderInterface`.
- **Website domain map:** prayer *data* moves from “Website owns prayer times” to PrayerTimes domain; Website remains a consumer.
- **Rule 10:** `prayer_broadcasts` / `prayer_broadcast_recipients` explicitly **exempt** from `academic_year_id` — operational comms logs, not academic backbone data.
- **salat.db:** operator-supplied artifact; license/provenance documented when import ships (not in repo).
