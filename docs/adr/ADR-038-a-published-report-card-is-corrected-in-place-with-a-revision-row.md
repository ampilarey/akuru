# ADR-038: A published report card is corrected in place, with a revision row

**Date:** 2026-09-22 · **Status:** accepted · **Phase:** S3.6 (finishing) · **Amends:** nothing; makes S3.6's last sentence true

## Context

`docs/S3_SPEC.md` S3.6 ends: *"Regeneration allowed until published; after,
new version with audit."* What shipped was the first half. `GenerateReportCardsAction`
skipped published cards and `renderOne()` threw *"Published report cards
cannot be regenerated"*, and nothing recorded that the second half had been
left out. The S3 audit (STATUS §5ex) listed it as D3.

The case it exists for is ordinary: a mark is corrected after the cards went
out (an exam is unlocked with a reason — S3.2 already audits that), the term
grade recomputes, and the card a family downloaded now disagrees with the
gradebook. Today the only way out is to delete the row by hand.

Three shapes were possible:

1. **Refuse, and require an "unpublish" step first.** Takes the card off the
   portal while it re-renders, so a family clicking the link in that window
   gets a 404 for a card they were told was published.
2. **A new `report_cards` row per version.** `report_cards` is unique per
   student and term on purpose (one card per child per term is the thing
   everything else joins to), and every reader — portal, overview, CSV,
   transcript — would have to learn "the latest one".
3. **Replace the document in place and record the replacement.** The card
   row stays one per student and term and stays published; the document it
   points at changes; a row elsewhere says what it replaced, who asked and why.

## Decision

Shape 3.

1. **A published card is regenerated only with a reason.** `GenerateReportCardsAction::execute()`
   takes a `$reason`; without one, published cards are skipped as before.
   `renderOne()` refuses a published card with no reason. The screen offers
   *"Also regenerate published cards"* with a reason input that the request
   requires when the box is ticked.
2. **The card stays published throughout.** Its `document_id` moves to the new
   render, `generated_at` is updated, `status` and `published_at` are not
   touched. The portal serves the corrected version at the same link from the
   moment the render lands; there is no gap.
3. **`report_card_revisions` is the audit.** One row per regeneration of a
   published card: `superseded_document_id`, the new `document_id`, `actor_id`,
   `reason`, and the backbone (`academic_year_id`, `term_id`, rule 10). It is
   append-only: the application never edits or deletes a row. The superseded
   document is kept in Media, so the version a family downloaded earlier can
   still be read back by id.
4. **The screen shows it.** Each card lists how many revisions it has and the
   last reason. The revision count and reason ride `ListReportCardsAction`,
   so the CSV and any later reader get them for free.
5. **No re-notification.** Publishing already sends the "report cards are
   ready" notice; a correction is not a second publication. If a school wants
   to tell families about a correction, that is a notice to write, not an
   event to fire — it can be added later without changing anything here.

## Consequences

- S3.6's last sentence is implemented as written, and the walk (`exams.mjs`)
  exercises it after publishing.
- A card's history is `revisions` in order; the current document is always
  `report_cards.document_id`. Nothing that reads cards had to change.
- Draft and ready cards regenerate as before, with no revision row — the
  audit is for what families have already seen.
- The one thing this does not do is tell families. That is deliberate (5)
  and cheap to add.
