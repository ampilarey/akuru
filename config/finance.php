<?php

return [
    /*
     * Bank-statement import (ROADMAP §S4 backlog).
     *
     * ⚠ The real BML export format has never been seen. Everything here is a
     * documented default, not an observed one, which is exactly why it lives in
     * config: the first genuine statement is absorbed by editing `.env`, with
     * no code change and no redeploy of the matching logic.
     *
     * `columns` maps this application's meaning onto the bank's header names,
     * matched case-insensitively. Provide either a single signed `amount`
     * column or a `credit`/`debit` pair — whichever the export uses.
     */
    'bank_statement' => [
        'driver' => env('BANK_STATEMENT_DRIVER', 'csv'),
        'currency' => env('BANK_STATEMENT_CURRENCY', 'MVR'),

        'columns' => [
            'date' => env('BANK_STATEMENT_COL_DATE', 'date'),
            'description' => env('BANK_STATEMENT_COL_DESCRIPTION', 'description'),
            'reference' => env('BANK_STATEMENT_COL_REFERENCE', 'reference'),
            'amount' => env('BANK_STATEMENT_COL_AMOUNT', 'amount'),
            'credit' => env('BANK_STATEMENT_COL_CREDIT', 'credit'),
            'debit' => env('BANK_STATEMENT_COL_DEBIT', 'debit'),
        ],

        // Tried in order. Day-first is listed before month-first deliberately:
        // Maldivian bank exports are day-first, and 03/04/2026 parses cleanly
        // under both, so the order is the only thing deciding which month it is.
        'date_formats' => array_values(array_filter(explode(
            '|',
            env('BANK_STATEMENT_DATE_FORMATS', 'Y-m-d|d/m/Y|d-m-Y|d.m.Y|Y/m/d')
        ))),

        // How close an amount must be to an invoice balance to be proposed.
        // Exact by default: a bank credit that is 50 laari off an invoice is a
        // question for a human, not a rounding allowance.
        'match_tolerance' => (float) env('BANK_STATEMENT_MATCH_TOLERANCE', 0),
    ],
];
