<?php

return [
    /*
    | Qur’an A.4 — dual-write of mapped halaqa onto Offerings.
    | Off by default (Rule 9 deploy 1). Switch/cleanup are later deploys.
    */
    'halaqa_dual_write' => (bool) env('QURAN_HALAQA_DUAL_WRITE', false),

    /*
    | Default translation edition name used by QuranTextProviderInterface.
    | Fixture gloss ships for tests/walk; operators import a licensed set and
    | point this at that source_name (ADR-023).
    */
    'translation_source' => env('QURAN_TRANSLATION_SOURCE', 'Akuru teaching gloss (fixture)'),
];
