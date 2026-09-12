# ADR-030: Bank-statement import — the format is configuration, and matching only ever suggests

Date: 2026-09-12
Status: Accepted
Phase: ROADMAP §S4 backlog ("bank-statement import + auto-matching")

## Context

§S4 listed bank-statement import as backlog and never specified it. Building it
raised one blocking unknown and one design question that money rules answer.

**The unknown: nobody has seen a real BML statement export.** Not its column
names, not its date convention, not whether amounts arrive in one signed column
or a credit/debit pair. A parser written against a guessed layout would be a
guess dressed up as an implementation, and the guess would surface either as a
validation error on the day somebody uploads a genuine file, or — far worse —
as a silently mis-parsed amount or a date read as the wrong month.

This is the same shape as ADR-028's BigBlueButton adapter, where green tests
prove the request is well-formed and say nothing about whether the integration
works. The owner was told this and asked for the work anyway; this ADR records
what was assumed so the assumption is visible rather than buried.

**The design question: what is a matched statement line allowed to do?** A bank
credit is strong evidence that money arrived, and it is tempting to let a match
settle an invoice automatically. Rule 12 says otherwise, and so does experience:
the failure mode is money on the wrong child, which is very hard to unpick once
a receipt has been issued and a family notified.

## Decision

**1. The column map is configuration, not code.**
`ConfiguredCsvBankStatementParser` reads `config('finance.bank_statement')` for
the bank's own header names, the date formats to try in order, and the currency.
Adapting to a real export is an `.env` change. A file this parser cannot read
fails naming **both** what it expected and what the file actually contained, so
the error tells somebody how to fix it.

Both amount shapes are supported because banks use both: a single signed column,
or a credit/debit pair, with debits normalised to negative. Which one applies is
config, not a sniff of the data.

Date formats are tried in order and the order is load-bearing: `03/04/2026`
parses cleanly as both 3 April and 4 March, so the list decides. Day-first is
listed first because that is the Maldivian convention.

**2. The parser sits behind `BankStatementParserInterface`** (rule 4). A bank
that exports something other than CSV becomes a new implementation and a driver
change, touching neither import, matching, nor receipts.

**3. Matching suggests; a person decides.** Two strategies, ordered by how much
they actually prove:

- The invoice number appearing in the line's own text. A family typing the
  reference is the bank telling you which invoice this is — the only evidence
  here that is not circumstantial. It wins even when the amount disagrees.
- An exact amount against exactly one open invoice's balance. Weaker, and it
  **refuses to choose** when more than one invoice fits. Two families owing the
  same termly fee is the ordinary case, not an edge case. Ambiguity is recorded
  with the candidate invoice numbers and left unmatched, because "nothing
  matched" and "several matched" are different problems for the reviewer and
  only one of them means go and find the reference.

**4. Confirming is the only thing that writes money, and it writes it through
the existing path.** `ConfirmBankStatementMatchAction` calls
`RecordInvoiceReceiptAction` — the same action the cashier screen uses, which
allocates, numbers and renders the document. Nothing touches
`invoices.paid_amount` directly.

The method is **`transfer`, never `bml`**. A statement line is a bank saying
money arrived; it is not a gateway webhook. Recording it as `bml` would make the
reconciliation report lie about how the school was paid.

**It grants access to nothing.** Enrolment in a paid course still waits on BML
webhook confirmation (rule 12), and this deliberately cannot substitute for it.

**5. An overpayment is credited only up to the balance.** A family settling two
invoices with one transfer is common; the surplus stays on the line, unplaced,
for a person to put somewhere. Guessing is how money lands on the wrong child.

**6. Two permissions, because two different things happen.** `finance.manage`
opens the screen and imports a file — bookkeeping. `finance.record-manual-payment`
gates confirmation — money. Somebody who may read what the bank sent is not
thereby somebody who may decide the school has been paid.

**7. Nothing is deleted.** A dismissed line is marked `ignored` with a reason and
kept: rule 12's append-only spirit covers the record of what the bank said, and
a line dismissed in September has to be explainable in March. A confirmed line
cannot be ignored — undoing money is a refund, which has its own action, event
and audit trail.

## What this ADR does NOT claim

**No real bank statement has ever been through this importer.** Every fixture in
`BankStatementParserTest` was written by the same person who wrote the parser,
which makes them a test of internal consistency, not of the format. Expect the
first genuine export to need a config change, and possibly to reveal a shape
neither amount convention here covers.

The matcher has also never met a real month of transactions. Its two strategies
are the two that are defensible without data; how often they fire, and how often
the ambiguity branch is the *right* answer rather than an annoyance, is
something only real statements can say.

## Consequences

- `bank_statement_imports` and `bank_statement_lines`, both carrying
  `academic_year_id` (rule 10). It is nullable: refusing to import a bank's own
  record because the school has not yet created that academic year would be
  hostile, so the column is filled when a year covers the date and left null
  otherwise.
- Idempotent twice over — `source_hash` (the file) makes re-uploading the same
  export a no-op that returns the original import; `row_hash`, unique per
  import, keeps a re-exported overlapping period from doubling rows. Both
  duplicates happen routinely in practice.
- `config/finance.php` is new. Every key is env-driven and documented as an
  assumption rather than an observation.
