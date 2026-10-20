<?php

// CLAUDE.md "Phone-first" and docs/MOBILE_FIRST.md — see
// tests/Architecture/PhoneFirstStylesTest.php.
//
// Inertia pages with a table that is neither `table-stack` (app.css) nor
// `data-phone-scroll`: on a phone it stays squeezed columns instead of a
// list of cards. Counted per table since MF4. The list may
// only SHRINK: give the table `table-stack` and each cell its `data-label`,
// then delete the line. Count: 0 (2026-10-20, MF6: every table in every Inertia page is stacked or a named grid). Keep it at 0.

return [
];
