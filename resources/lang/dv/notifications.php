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
    'family_digest' => [
        'title' => 'މާދަމާ ސްކޫލްގައި — :date',
        'body' => ':lessons ހޯމްވޯކް: :homework. އާ ނޯޓިސް: :notices.',
        'lessons' => ':count lesson(s), first is :first.',
        'no_lessons' => 'No lessons scheduled.',
    ],
    'request' => [
        'submitted_title' => ':name ގެ އައު އެދިވަޑައިގަތުމެއް',
        'submitted_body' => ':regarding އާ ބެހޭ :type އެދިވަޑައިގަތުމެއް: :reason',
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
    // HP3c (ADR-044, STATUS §5tz): notices that were English sentences in code.
    'invoice' => [
        'issued_title' => 'އިންވޮއިސް :number',
        'issued_body' => 'އަކުރު އިންސްޓިޓިއުޓް: :name ގެ އިންވޮއިސް :number ގެ އަދަދަކީ :amount ރުފިޔާ، ދައްކަންޖެހެނީ :due ގައި.',
        'reminder_title' => 'އިންވޮއިސް :number ހަނދާންކޮށްދިނުން',
        'reminder_body' => 'އަކުރު އިންސްޓިޓިއުޓް: :name ގެ އިންވޮއިސް :number ގައި :amount ރުފިޔާ ނުދައްކާ ހުރި (ދައްކަންޖެހެނީ :due ގައި).',
    ],
    'hr' => [
        'expiring_title' => ':days ދުވަހުން މުއްދަތު ހަމަވާ ލިޔުމެއް',
        'expiring_body' => ':document ގެ މުއްދަތު ހަމަވަނީ :date ގައި',
    ],
    'gift_card' => [
        'redeemed_title' => 'ގިފްޓް ކާޑު ބޭނުންކުރެވިއްޖެ',
        'redeemed_body' => 'MVR :amount ތިބާގެ ވޮލެޓަށް އެއްކުރެވިއްޖެ.',
    ],
];
