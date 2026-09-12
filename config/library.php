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

    // L7 (§12.2/§29): research cannot be approved without a peer-review
    // recommendation while this is on.
    'research_review_required' => env('LIBRARY_RESEARCH_REVIEW_REQUIRED', true),

    /*
     * L2b (§9.2, §30.3): reading-abuse detection.
     *
     * Every threshold is a guess about human behaviour, so every one is
     * env-driven — the numbers that matter will come from watching real
     * readers, not from this file. They are set deliberately loose: a false
     * positive here accuses a paying reader of theft, which is far worse than
     * missing a few genuine cases early on.
     */
    'abuse' => [
        // "Detect rapid page opening": more than N page views inside this many
        // seconds. A reader skimming a reference book legitimately moves fast,
        // so this is set well above ordinary skimming.
        'rapid_pages_window_seconds' => (int) env('LIBRARY_RAPID_WINDOW', 60),
        'rapid_pages_threshold' => (int) env('LIBRARY_RAPID_PAGES', 40),

        // "Detect multi-device abuse": distinct devices on one account inside
        // this window. A family sharing an account across a phone, a tablet and
        // a laptop is normal; a book being resold is not.
        'device_window_hours' => (int) env('LIBRARY_DEVICE_WINDOW_HOURS', 24),
        'device_threshold' => (int) env('LIBRARY_DEVICE_LIMIT', 5),

        // "Limit simultaneous sessions": distinct sessions active in the last
        // few minutes.
        'session_window_minutes' => (int) env('LIBRARY_SESSION_WINDOW_MINUTES', 10),
        'session_threshold' => (int) env('LIBRARY_SESSION_LIMIT', 3),

        /*
         * §9.2 also says "limit simultaneous sessions", which implies refusing
         * a read. That is OFF by default and deliberately so: blocking on a
         * heuristic locks a paying reader out of a book they own, and the
         * threshold that decides it has never been checked against a real
         * reader. Detection is useful immediately; enforcement should wait for
         * evidence the numbers are right.
         */
        'enforce' => filter_var(env('LIBRARY_ABUSE_ENFORCE', false), FILTER_VALIDATE_BOOLEAN),

        // Events are pruned by age. They answer "what happened lately", and
        // keeping a child's reading history indefinitely serves nothing.
        'retention_days' => (int) env('LIBRARY_EVENT_RETENTION_DAYS', 90),
    ],
];
