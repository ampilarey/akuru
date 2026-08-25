# ADR-020: Halaqa dual-write is deploy 1 only

## Context

Rule 9 requires three deploys for live-data moves: additive +
backfill/dual-write, then switch reads, then cleanup. Qur’an A.3 mapped
ids without writing either side.

## Decision

A.4 ships dual-write only. `QURAN_HALAQA_DUAL_WRITE` defaults false.
Catalog sync mirrors unmapped Hifz sessions and active enrollments onto
the linked offering and sets `offering_halaqa_links.dual_write`. When
the flag is on, new Hifz sessions created through
`HifzSessionService` also mirror, but a mirror failure never blocks
the Hifz write. Hifz dashboards and scoring stay as they are. Engine
reads are not switched. Hifz tables are not dropped.

## Consequences

Operators can enable and verify the copy path without cutting Hifz
over. Switch and cleanup remain later deploys.
