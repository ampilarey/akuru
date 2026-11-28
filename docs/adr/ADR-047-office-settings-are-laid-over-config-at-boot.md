# ADR-047: Office settings are laid over config at boot

## Context

The admin audit (2026-11-27, STATUS §5ut) found business settings — a
loan's length, a commission, the wallet alert — that could be changed only
in `.env` on the server. Dozens of readers call `config('lending.max_days')`
and the like directly. Moving each reader to a settings lookup would touch
every domain in one slice and leave two ways to read a value.

## Decision

- `config/office-knobs.php` lists, per product, the settings the office may
  change: the config key each sets, its type and bounds, and the product's
  gate.
- `Settings\Support\OfficeKnobs::apply()` runs at boot and sets each stored
  value over its config key. Readers do not change.
- The deploy's value (`.env`, or the config file's default) stays the
  default and is shown beside the field.
- A stored value is checked against its bounds when read as well as when
  saved; one out of bounds is ignored.
- Stored values are rows in `settings` under `knob.<product>.<name>`, read
  once per five minutes from the cache and forgotten on save. A save also
  asks queue workers to restart.
- Secrets, server paths and anything a wrong value could break the site
  with stay in `.env` and are never listed.

## Consequences

- A product gets a settings page by adding rows to one config file and
  phrases to the `knobs` book.
- One cached query is added to each boot.
- `config:cache` holds the deploy's values; the office's still win, because
  they are applied after the cached config loads.
- A setting changed in `.env` after the office has saved its own has no
  effect until the office's is changed; the page shows both, so this is
  visible.
