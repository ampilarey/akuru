<?php

// Inertia pages that submit a form and never mention `errors`, so a refusal
// would be invisible to the person who pressed the button.
//
// **The list is empty**, and that is the point of keeping the file: all 92
// pages that submit a form can now report what came back. It started at 28,
// found by driving the forms rather than reading them — a non-numeric Hours on
// the CPD screen produced no row, no message, and the typed values still
// sitting in the boxes.
//
// See tests/Architecture/FormErrorsAreShownTest.php. An entry may be added back
// only with a reason a reader can check; the test fails on a stale entry as
// loudly as on a new violation.
//
// Count: 0.

return [];
