<?php

/*
 * ST4 (admin audit 2026-11-27, STATUS §5uw): the business settings the
 * office may change from a screen, per product. Each was readable only in
 * `.env`, so changing a loan's length or a commission meant the server.
 *
 * A knob names the config key it sets; `OfficeKnobs` lays the office's
 * value over the deploy's at boot, so every existing `config()` reader
 * sees it and no reader changes. What the deploy carries stays the
 * default, shown beside each field. Secrets, server paths and anything a
 * wrong value could break the site with stay in `.env`.
 *
 * product => [gate (an ability), groups =>
 *   [group => [knob => [config, type, min?, max?, at_most? (another knob of the group)]]]]
 * Types: int, decimal, bool, text. Phrases: resources/lang/<locale>/knobs.php.
 */
return [
    'lending' => [
        'gate' => 'bookshop.manage',
        'groups' => [
            'loans' => [
                'default_days' => ['config' => 'lending.default_days', 'type' => 'int', 'min' => 1, 'max' => 365, 'at_most' => 'max_days'],
                'max_days' => ['config' => 'lending.max_days', 'type' => 'int', 'min' => 1, 'max' => 365],
                'max_books' => ['config' => 'lending.max_books', 'type' => 'int', 'min' => 1, 'max' => 1000],
                'free_taken_days' => ['config' => 'lending.free.taken_days', 'type' => 'int', 'min' => 0, 'max' => 365],
            ],
            'notices' => [
                'remind_days_before' => ['config' => 'lending.reminders.days_before', 'type' => 'int', 'min' => 0, 'max' => 30],
                'notices_email' => ['config' => 'lending.notices.email', 'type' => 'bool'],
                'notices_sms' => ['config' => 'lending.notices.sms', 'type' => 'bool'],
            ],
        ],
    ],
    'wallet' => [
        'gate' => 'commerce.manage',
        'groups' => [
            'alerts' => [
                'alert_credit_at' => ['config' => 'commerce.wallet.alert_credit_at', 'type' => 'decimal', 'min' => 0, 'max' => 1000000],
            ],
        ],
    ],
];
