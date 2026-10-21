<?php

return [
    'attendance' => [
        // :name student name, :status translated status below, :date Y-m-d
        'marked' => 'Akuru Institute: :name was marked :status on :date.',
        'status' => [
            'absent' => 'absent',
            'late' => 'late',
        ],
    ],

    'registers' => [
        'unfilled_title' => 'Registers still to fill',
        'unfilled_body' => 'You have :count register(s) past their lesson time that are not submitted yet.',
    ],
    'digest' => [
        'title' => 'Daily summary — :date',
        'body' => 'Absent/late marks today: :absent. Registers still unfilled: :unfilled.',
    ],
    'family_digest' => [
        'title' => 'Tomorrow at school — :date',
        'body' => ':lessons Homework due soon: :homework. New notices: :notices.',
        'lessons' => ':count lesson(s), first is :first.',
        'no_lessons' => 'No lessons scheduled.',
    ],
    'request' => [
        'submitted_title' => 'New request from :name',
        'submitted_body' => 'A :type request about :regarding: :reason',
        'decision_title' => 'Your request was :status',
        'decision_body' => 'Your :type request was :status. :notes',
        'status' => [
            'approved' => 'approved',
            'rejected' => 'rejected',
        ],
    ],
    'substitution' => [
        'assigned_title' => 'Substitution assigned',
        'assigned_body' => 'You are covering a lesson on :date.',
    ],
    'behavior' => [
        'logged' => 'Akuru Institute: a :type was recorded for :name on :date.',
        'type' => [
            'notice' => 'notice',
            'warning' => 'warning',
            'incident' => 'incident',
        ],
    ],
    // HP3c (ADR-044, STATUS §5tz): notices that were English sentences in code.
    'invoice' => [
        'issued_title' => 'Invoice :number',
        'issued_body' => 'Akuru Institute: invoice :number for :name is :amount MVR, due :due.',
        'reminder_title' => 'Invoice reminder :number',
        'reminder_body' => 'Akuru Institute: invoice :number for :name has :amount MVR unpaid (due :due).',
    ],
    'hr' => [
        'expiring_title' => 'Document expiring in :days days',
        'expiring_body' => ':document expires on :date',
    ],
    'gift_card' => [
        'redeemed_title' => 'Gift card redeemed',
        'redeemed_body' => 'MVR :amount was added to your wallet.',
    ],
];
