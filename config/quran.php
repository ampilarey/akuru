<?php

return [
    /*
    | Qur’an A.4 — dual-write of mapped halaqa onto Offerings.
    | Off by default (Rule 9 deploy 1). Switch/cleanup are later deploys.
    */
    /*
    | SPEC §52.27 "Feature Flag" — the flag that did not exist. Its example
    | line is `QURAN_HIFZ_MODULE_ENABLED=false`, and §52.29 requires that "the
    | main platform works even if the Qur'an/Hifz module is disabled".
    |
    | Defaulted **true**, not false, deliberately. §52.27's example shows the
    | env line an operator writes to switch the module off, not the shipped
    | default — and this module is already built, live and linked from the
    | nav. Shipping `false` would switch off working features on every
    | deployment the moment this merged, which is a regression wearing a
    | spec-compliance badge.
    */
    'module_enabled' => (bool) env('QURAN_HIFZ_MODULE_ENABLED', true),

    'halaqa_dual_write' => (bool) env('QURAN_HALAQA_DUAL_WRITE', false),

    /*
    | Default translation edition name used by QuranTextProviderInterface.
    | Fixture gloss ships for tests/walk; operators import a licensed set and
    | point this at that source_name (ADR-023).
    */
    'translation_source' => env('QURAN_TRANSLATION_SOURCE', 'Akuru teaching gloss (fixture)'),
];
