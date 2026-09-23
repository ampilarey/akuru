<?php

// NOTE: Dhivehi strings are a first pass and need native review before these
// receipts reach real guardians. See STATUS.md (S-track audit fixes).
return [
    'receipt' => [
        'heading' => 'ފައިސާ ބަލައިގަތް ރަސީދު',
        'invoice' => 'އިންވޮއިސް',
        'amount' => 'އަދަދު (ރުފިޔާ)',
        'method' => 'ދެއްކި ގޮތް',
        'received_at' => 'ބަލައިގަތް ވަގުތު',
        'methods' => [
            'bml' => 'ބީއެމްއެލް',
            'cash' => 'ފައިސާ',
            'transfer' => 'ބޭންކް ޓްރާންސްފަރ',
            'wallet' => 'ވޮލެޓް',
            'gift_card' => 'ގިފްޓް ކާޑް',
            'waiver' => 'މާފުކުރެވިފައި',
        ],
    ],
    'payslip' => [
        'heading' => 'މުސާރަ ސްލިޕް',
        'staff' => 'މުވައްޒަފު',
        'period' => 'މުއްދަތު',
        'basic_salary' => 'އަސާސީ މުސާރަ (ރުފިޔާ)',
        'gross' => 'ޖުމްލަ މުސާރަ (ރުފިޔާ)',
        'employee_pension' => 'ޕެންޝަން ފައިސާ (ރުފިޔާ)',
        'tax_withheld' => 'ޓެކްސް އުނިކުރެވުނު (ރުފިޔާ)',
        'unpaid_leave_deduction' => 'މުސާރަ ނުލިބޭ ޗުއްޓީގެ އުނިކުރުން (ރުފިޔާ)',
        'net_pay' => 'ލިބޭ މުސާރަ (ރުފިޔާ)',
        'employer_pension_note' => 'މި މުއްދަތަށް ސްކޫލުން ތިޔަ ފަރާތުގެ ޕެންޝަނަށް އިތުރު :amount ރުފިޔާ ދައްކައެވެ.',
    ],
];
