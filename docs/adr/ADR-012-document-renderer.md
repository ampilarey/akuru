# ADR-012: Document renderer for report cards and certificates

**Status:** accepted (amended 2026-08-26)  
**Supersedes:** the S3.6 “Browsershot in production” bind recorded below, which never shipped.

## Context

S3 report cards, transcripts, ID cards, and awards need printable output
with Thaana and Arabic RTL. `DocumentRendererInterface` already exists in
Support. Spec S3.1 called this ADR-005; that number is taken by the morph
map (ADR-005).

The original decision (below) named **Browsershot / headless Chrome** as
the S3.6 production bind, with Dompdf as a fallback. What actually shipped
in S3.6 is `HtmlDocumentRenderer` (`AppServiceProvider`). CI and staging
do not run Chrome. UI downloads are labelled HTML (#89). Payslips (S5.6),
certificates (Phase 3), and ID cards all call this same interface.

Leaving “PDF someday, HTML today” unrecorded would make every later
document slice guess.

## Decision

1. **Contract stays in Support** — one renderer for ExamsGrades, later
   Finance receipts, Library invoices, and course certificates. Domain
   Actions call the interface only. No SDK and no `if (pdf)` in domain.
2. **HTML is the supported production output.** The container binds
   `HtmlDocumentRenderer`. Templates are Blade under `resources/views/documents/`
   (`dir="rtl"` for DV/AR). Downloads use `text/html` and a `.html`
   filename. Print-to-PDF is a browser/OS concern, not an app renderer.
3. **Why not Browsershot on this slice:** Chrome is not installed on CI
   or the current staging host. Binding Browsershot now would fail those
   environments. Thaana/Arabic already render in HTML; forcing PDF without
   a host that can run Chrome would regress RTL.
4. **PDF remains a future container binding swap** — a `BrowsershotDocumentRenderer`
   (or similar) can replace `HtmlDocumentRenderer` in `AppServiceProvider`
   without changing Actions. Dompdf is not the RTL path. Until that bind
   exists, do not claim PDF in UI copy.
5. **Delivery:** queued jobs; bytes stored through Media (private), as HTML
   until a PDF bind is introduced.
6. **Historical note:** “Until S3.6: keep `StubDocumentRenderer`” is
   obsolete. S3.6 bound `HtmlDocumentRenderer`. `StubDocumentRenderer`
   remains in the tree for tests that need a no-op.

## Original decision (S3.1, not the production bind)

Recorded here so the amendment is explicit, not silent:

1. Contract in Support.
2. Production implementation (S3.6): Browsershot / headless Chrome
   rendering Blade/HTML templates.
3. Fallback: Dompdf for simple, LTR-only docs if Chrome is missing.
4. Delivery: queued jobs; bytes stored through Media (private).
5. Until S3.6: keep `StubDocumentRenderer`.

## Consequences

S3.6, awards, ID cards, receipts, and payslips are honest HTML. Phase 3
certificates reuse the same interface and the same HTML default. A later
PDF slice is a binding change plus UI copy (`Download PDF`), not a new
interface. Chrome must be present on every host that should emit PDF
before that bind is flipped.
