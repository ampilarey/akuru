<?php

/*
 * L6 (LIBRARY_PLAN §22, §24, §29): commercial knobs for the Knowledge
 * Library. Payouts stay DISABLED until the operator confirms the tax /
 * accounting treatment of writer payouts (ROADMAP §9.4) — sales accrue
 * as earnings meanwhile.
 */
return [
    // Days after purchase before a writer earning matures (refund window §24).
    'refund_window_days' => env('LIBRARY_REFUND_WINDOW_DAYS', 7),

    // §9.4 operator gate: writers cannot request payouts until this is on.
    'payouts_enabled' => env('LIBRARY_PAYOUTS_ENABLED', false),

    // §22 default split: writer 70 / Akuru 30. Overridable per writer
    // (writer_profiles.default_commission) and per item
    // (library_items.commission_type/commission_value).
    'default_writer_commission' => env('LIBRARY_WRITER_COMMISSION', 70),

    // Minimum available balance before a payout can be requested.
    'min_payout' => env('LIBRARY_MIN_PAYOUT', 100),
];
