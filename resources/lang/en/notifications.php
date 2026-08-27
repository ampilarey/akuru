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
    'request' => [
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
];
