# Future handwriting model — notes

- Same local/offline policy as pronunciation (§51.9): no cloud OCR.
- Likely input: stylus/touch stroke data or scanned letter images.
- Reuse the human-in-the-loop pipeline (verified samples → batch training
  → versioned models with audited activation) before any student-facing
  scoring.
- Blocked on: dataset collection strategy and a writing activity type in
  the Arabic component.
