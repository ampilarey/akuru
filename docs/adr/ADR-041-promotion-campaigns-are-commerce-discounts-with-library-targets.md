# ADR-041: Promotion campaigns are Commerce discounts with Library-named targets

**Date:** 2026-09-27
**Status:** Accepted
**Slice:** LIBRARY_PLAN B4 (§18, §8.5, §35.10), STATUS §5ix

## Context

The Library plan asks for promotion campaigns: a Ramadan offer, a
back-to-school offer, a new writer's launch week — a discount with a
window and a name, applied by itself to what it covers, shown on the
shelf, and reported on. The platform already had one discount system in
Commerce (`discount_codes`, `discount_redemptions`; rule 11) and a
writer-earnings model that reads a purchase's discount and who funded it
through `ResolveRedemptionFundingSourceAction`.

Three questions had to be answered before writing anything:

1. Where does a campaign live — Commerce (the one discount system) or the
   Library (the only place that knows what "a category" or "a writer" is)?
2. How does a purchase remember that a campaign was applied, so that the
   writer's earning, the webhook confirmation and a refund all behave as
   they do for a code?
3. What happens when a reader types a discount code on an item that a
   campaign already covers?

## Decision

1. **Campaigns are Commerce data.** `promotion_campaigns` and
   `promotion_campaign_targets` live in Commerce beside the codes. A
   target is a string type and an id — `all`, `library_item`,
   `library_category`, `writer_profile`, the morph-map aliases (ADR-005).
   Commerce stores and lists them and never asks what they mean;
   `ResolveLibraryItemPromotionAction` in the Library is where
   `library_category` becomes "the item's category". Rule 3 holds: neither
   domain imports the other's models, and the Library reaches Commerce
   through its Actions only.

2. **A campaign's use is a `discount_redemptions` row.** The table gains a
   nullable `promotion_campaign_id` beside a now-nullable
   `discount_code_id`; exactly one is set. Recording (`forCampaign`),
   confirming by the webhook, releasing on abandonment and on refund, the
   funding-source lookup and therefore the writer's earning all work
   unchanged, because they key on the purchase, not on which kind of
   discount it was. The office's performance report is a count and a sum
   over those rows.

3. **No stacking.** With no code typed, a live campaign covering the item
   applies by itself, at the price the page showed; several campaigns
   covering one item give the reader the biggest saving. A typed code is
   the reader's choice to use *instead*, on the full price. A campaign is
   never edited once started — end it and start another — so a redemption's
   terms stay what they were.

## Addendum, 2026-09-27 (B4b): a bonus on gift cards is not a discount

The plan's "gift card bonus buy-500-get-50" (§18) is the one campaign
shape that is not a price reduction, and §15.4 / rule 12 forbid reducing
what a gift card costs. So a campaign that names the `gift_card` target
**adds value to the card the buyer pays full price for**: the payment is
for the amount typed, the card is issued for the amount plus the bonus,
the bonus is a `bonus` row on the card's own ledger, and the Institute
funds it (`funding_source` is recorded; nothing else is owed). The use is
still a `discount_redemptions` row against the campaign — the office's
count of uses and value given is one report — and the `all` target never
reaches gift cards: "everything paid in the library" is items. The
stored-value liability rises by the bonus, as it should; that is the
campaign's cost, and the liability report is where it shows.

## Consequences

- One discount ledger, one funding model, one refund path. A future
  Bookstore campaign is a new target type and a resolver of its own, not
  a second system.
- The Library's public surface gains a *Discounted* filter, a
  `?campaign=` filter, a strip of live offers on the shelf, an offers page
  and the struck-through price; the office gains `/admin/library/promotions`
  with a CSV.
- Not decided here, left for the plan's later items: bundles (§19), gift
  card bonus campaigns, a banner image per campaign, and code-required
  campaigns (a code with a window is already a discount code).
