# ADR-046: A Library bundle is a purchase per work, split by list price

## Context

LIBRARY_PLAN §19 asks for bundles: an Arabic Beginner Bundle, a Qur'an
Reading Starter Pack, a Research Article Pack. It was parked as "not MVP
unless trivial" (BACKLOG B4). On 2026-10-21 the owner asked for everything
that can be completed, deciding by best practice.

A bundle sells several writers' works at one price. The writer-earning
code (§16, L6), the refund clawback (§43.7) and My Library all work per
purchase of one work.

## Decision

- **One purchase per work.** Buying a bundle makes one `library_purchases`
  row per work, each carrying `library_bundle_id`. Its amount is the work's
  share of the bundle price, by list price; the last work takes the
  rounding. Each writer then earns on their own share through the existing
  earning action, with the bundle's saving shared in proportion. No new
  earning rule.
- **One payment.**
  - **By card:** one BML payment with `payable_type` `library_bundle`.
    Every purchase waits for the webhook (§43.5, rule 12). A full refund
    takes back every grant and every earning; a partial refund keeps access,
    as for a single work.
  - **By wallet:** one debit, all in one transaction.
- **The bundle is the offer.** No discount code or campaign applies on top,
  so nothing stacks.
- **What a bundle may hold:** 2 to 20 paid, published works, at a price above
  nothing and below what they cost apart.
- **Works already owned:** a reader who owns every work cannot buy it. A
  reader who owns some is told so, and the bundle is sold whole.

## Consequences

- Sales reports, My Library, the writer's earnings and the refund listener
  all keep working per work. Bundle reports group by `library_bundle_id`.
- A bundle changed after sales keeps the past purchases as they were.
- "Buy 3, get 1" is a bundle of four at the price of three. No separate
  rule is needed.
