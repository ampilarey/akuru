<?php

return [
    'receipt' => [
        'heading' => 'Payment receipt',
        'invoice' => 'Invoice',
        'amount' => 'Amount (MVR)',
        'method' => 'Method',
        'received_at' => 'Received at',
        'methods' => [
            'bml' => 'BML',
            'cash' => 'Cash',
            'transfer' => 'Bank transfer',
            'wallet' => 'Wallet',
            'gift_card' => 'Gift card',
            'waiver' => 'Waiver',
        ],
    ],
    'payslip' => [
        'heading' => 'Payslip',
        'staff' => 'Staff member',
        'period' => 'Period',
        'basic_salary' => 'Basic salary (MVR)',
        'gross' => 'Gross pay (MVR)',
        'employee_pension' => 'Pension contribution (MVR)',
        'tax_withheld' => 'Tax withheld (MVR)',
        'unpaid_leave_deduction' => 'Unpaid leave deduction (MVR)',
        'net_pay' => 'Net pay (MVR)',
        'employer_pension_note' => 'The school contributes a further :amount MVR to your pension for this period.',
    ],
];
