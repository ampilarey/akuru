<?php

// CLAUDE.md "Phone-first" and docs/MOBILE_FIRST.md — see
// tests/Architecture/PhoneFirstStylesTest.php.
//
// Desktop-first CSS: each file's count of `@media (max-width: …)` rules, the
// sign of a desk layout patched for phones afterwards. A count may only go
// DOWN: rewrite the rule phone-first (the phone layout as the default, the
// wider one under `min-width`) and lower or delete its line. Total: 19 in 7
// files (2026-10-20).

return [
    'resources/css/app.css' => 3,
    'resources/views/components/public/footer.blade.php' => 1,
    'resources/views/components/public/nav.blade.php' => 2,
    'resources/views/public/home.blade.php' => 7,
    'resources/views/public/layouts/public.blade.php' => 2,
    'resources/views/public/partials/prayer-banner-assets.blade.php' => 3,
    'resources/views/public/shop/_theme.blade.php' => 1,
];
