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
 * Types: int, decimal, bool, text (max = characters), url (empty, a path
 * on this site, or an http(s) address). Phrases: resources/lang/<locale>/knobs.php.
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
    // ST4b: the Bookstore's own rules, for whoever runs the Bookstore office.
    'bookstore' => [
        'gate' => 'bookshop.manage',
        'groups' => [
            'checkout' => [
                'reservation_minutes' => ['config' => 'bookshop.checkout.reservation_minutes', 'type' => 'int', 'min' => 5, 'max' => 1440],
                'cart_reminders' => ['config' => 'bookshop.cart_reminders.enabled', 'type' => 'bool'],
            ],
            'reviews' => [
                'reviews_premoderate' => ['config' => 'bookshop.reviews.premoderate', 'type' => 'bool'],
            ],
        ],
    ],
    // ST4b: commission, payouts, tax and where bank transfers go — the system admin's alone.
    'bookstore_money' => [
        'gate' => 'bookshop.manage',
        'groups' => [
            'commission' => [
                'default_commission_rate' => ['config' => 'bookshop.default_commission_rate', 'type' => 'decimal', 'min' => 0, 'max' => 50],
                'payouts_enabled' => ['config' => 'bookshop.money.payouts_enabled', 'type' => 'bool'],
                'min_payout' => ['config' => 'bookshop.money.min_payout', 'type' => 'decimal', 'min' => 0, 'max' => 100000],
            ],
            'tax' => [
                'tax_standard' => ['config' => 'bookshop.tax.rates.standard', 'type' => 'decimal', 'min' => 0, 'max' => 50],
                'commission_tax_rate' => ['config' => 'bookshop.money.commission_tax_rate', 'type' => 'decimal', 'min' => 0, 'max' => 50],
                'issuer_gst_registered' => ['config' => 'bookshop.money.issuer_gst_registered', 'type' => 'bool'],
                'issuer_name' => ['config' => 'bookshop.money.issuer_name', 'type' => 'text', 'min' => 1, 'max' => 120],
                'issuer_tin' => ['config' => 'bookshop.money.issuer_tin', 'type' => 'text', 'max' => 40],
            ],
            'bank' => [
                'bank_name' => ['config' => 'bookshop.bank_transfer.bank', 'type' => 'text', 'min' => 1, 'max' => 120],
                'bank_account_name' => ['config' => 'bookshop.bank_transfer.account_name', 'type' => 'text', 'min' => 1, 'max' => 120],
                'bank_account_number' => ['config' => 'bookshop.bank_transfer.account_number', 'type' => 'text', 'max' => 40],
            ],
        ],
    ],
    // ST4b: student blogs. Tips and rewards are on the Library's own settings page.
    'blogs' => [
        'gate' => 'library.manage',
        'groups' => [
            'students' => [
                'teacher_review' => ['config' => 'library.blog.teacher_review', 'type' => 'bool'],
                'posts_per_week' => ['config' => 'library.blog.posts_per_week', 'type' => 'int', 'min' => 1, 'max' => 50],
                'guidelines_url' => ['config' => 'library.blog.guidelines_url', 'type' => 'url', 'max' => 255],
            ],
        ],
    ],
];
