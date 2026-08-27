<?php

// NOTE: Dhivehi strings below are a first pass and need native review before
// they reach real guardians. See STATUS.md (S1/S2 audit fixes).
return [
    'attendance' => [
        // :name student name, :status translated status below, :date Y-m-d
        'marked' => 'އަކުރު އިންސްޓިޓިއުޓް: :date ގައި :name ވަނީ :status ކަމަށް ފާހަގަކުރެވިފައެވެ.',
        'status' => [
            'absent' => 'ޣައިރު ހާޒިރު',
            'late' => 'ލަސްވެފައި',
        ],
    ],

    'registers' => [
        'unfilled_title' => 'ފުރިހަމަ ނުކުރެވޭ ރަޖިސްޓަރތައް',
        'unfilled_body' => 'ފިލާވަޅުގެ ވަގުތު ފާއިތުވެފައިވާ :count ރަޖިސްޓަރެއް އަދި ހުށަހަޅާފައެއް ނުވެއެވެ.',
    ],
    'digest' => [
        'title' => 'ދުވަހުގެ ޚުލާސާ — :date',
        'body' => 'މިއަދު ޣައިރު ހާޒިރު/ލަސްވި: :absent. ފުރިހަމަ ނުކުރެވޭ ރަޖިސްޓަރ: :unfilled.',
    ],
    'request' => [
        'decision_title' => 'ތިޔަ އެދިވަޑައިގަތުން :status',
        'decision_body' => 'ތިޔަ :type އެދިވަޑައިގަތުން :status ވެއްޖެއެވެ. :notes',
        'status' => [
            'approved' => 'ފާސްކުރެވިއްޖެ',
            'rejected' => 'ބާތިލްކުރެވިއްޖެ',
        ],
    ],
    'substitution' => [
        'assigned_title' => 'ބަދަލުގައި ކިޔަވައިދިނުން ހަވާލުކުރެވިއްޖެ',
        'assigned_body' => ':date ގައި ފިލާވަޅެއް ބަދަލުގައި ނަގަން ހަވާލުކުރެވިފައިވެއެވެ.',
    ],
    'behavior' => [
        'logged' => 'އަކުރު އިންސްޓިޓިއުޓް: :date ގައި :name އަށް :type އެއް ރެކޯޑްކުރެވިފައިވެއެވެ.',
        'type' => [
            'notice' => 'ނޯޓިސް',
            'warning' => 'އިންޒާރު',
            'incident' => 'ހާދިސާ',
        ],
    ],
];
