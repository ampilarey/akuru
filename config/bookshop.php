<?php

/**
 * Akuru Online Bookshop (docs/BOOKSHOP_PLAN.md). Knobs the office may later
 * move into a settings screen; env-backed until then.
 */
return [
    // Decision 5 (2026-09-25): 10–15% on goods, none on delivery; the
    // default is the low end, overridable per vendor by the office.
    'default_commission_rate' => (float) env('BOOKSHOP_DEFAULT_COMMISSION_RATE', 10),

    'currency' => 'MVR',

    // Product photos: public media, jpeg/png/webp.
    'photos' => [
        'max_per_product' => 8,
        'max_kilobytes' => 5120,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'variants' => [
        'max_per_product' => 30,
    ],
];
