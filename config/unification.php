<?php

$defaults = [
    'n/a',
    'na',
    '0',
    '-',
    '--',
    '---',
    '.',
    'unknown',
    'unk',
    'nil',
    'null',
    'none',
    'tbd',
    'pending',
    'not available',
];

$extra = array_map(
    static fn (string $value): string => mb_strtolower(trim($value)),
    explode(',', (string) env('UNIFICATION_NATIONAL_ID_PLACEHOLDERS', '')),
);

return [
    /*
     * National IDs treated as missing when matching registration_students
     * onto students (ADR-007 A2). Compared case-insensitively after trim.
     * Operators can append more via UNIFICATION_NATIONAL_ID_PLACEHOLDERS
     * (comma-separated).
     */
    'national_id_placeholders' => array_values(array_unique(array_filter(
        array_merge($defaults, $extra),
        static fn (string $value): bool => $value !== '',
    ))),
];
