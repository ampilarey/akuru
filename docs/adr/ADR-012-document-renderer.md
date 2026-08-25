# ADR-012: Document renderer for report cards and certificates

## Context

S3 report cards, transcripts, ID cards, and awards need PDF output with
Thaana and Arabic RTL. `DocumentRendererInterface` already exists in
Support with a no-op stub. Spec S3.1 called this ADR-005; that number
is taken by the morph map (ADR-005).

## Decision

1. **Contract stays in Support** — one renderer for ExamsGrades, later
   Finance receipts, Library invoices, and course certificates.
2. **Production implementation (S3.6):** Browsershot / headless Chrome
   rendering Blade/HTML templates. HTML/CSS handles RTL typography
   better than native PDF libraries.
3. **Fallback:** Dompdf for simple, LTR-only docs if Chrome is missing
   on a host. Selection is a container binding, not an `if` in domain
   logic.
4. **Delivery:** queued jobs; bytes stored through Media (private).
5. **Until S3.6:** keep `StubDocumentRenderer`. S3.1–S3.5 do not render
   PDFs. Everything must work with the stub.

## Consequences

S3.1 can ship grade scales / exam types / weight schemes without a PDF
binary. S3.6 is the first production binding. Phase 3 certificates and
Library invoices reuse the same interface. No SDK calls from domain
Actions — they call the interface only.
