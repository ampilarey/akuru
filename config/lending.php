<?php

/*
 * LENDING_AND_USED_BOOKS_PLAN L1: book lending between Akuru's users.
 */
return [
    // How long a book may be kept: the lender's default, and the longest allowed.
    'default_days' => (int) env('LENDING_DEFAULT_DAYS', 14),
    'max_days' => (int) env('LENDING_MAX_DAYS', 60),

    // How many books one lender may list.
    'max_books' => (int) env('LENDING_MAX_BOOKS', 50),

    // The public shelf's page size.
    'per_page' => 24,

    'photo' => [
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_kilobytes' => 5120,
        'card_width' => 480,
        'large_width' => 1200,
    ],

    // L2: the daily reminders — this many days before the due date, on the day, every day overdue.
    'reminders' => ['days_before' => (int) env('LENDING_REMIND_DAYS_BEFORE', 2)],

    // Lending notices go in the app always; by email and SMS too when these are on.
    'notices' => [
        'email' => filter_var(env('LENDING_NOTICE_EMAIL', true), FILTER_VALIDATE_BOOLEAN),
        'sms' => filter_var(env('LENDING_NOTICE_SMS', true), FILTER_VALIDATE_BOOLEAN),
        'sms_max_length' => 300,
    ],
];
