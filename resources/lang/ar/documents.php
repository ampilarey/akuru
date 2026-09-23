<?php

// NOTE: Arabic strings are a first pass and need native review before these
// receipts reach real guardians. See STATUS.md (S-track audit fixes).
return [
    'receipt' => [
        'heading' => 'إيصال دفع',
        'invoice' => 'الفاتورة',
        'amount' => 'المبلغ (روفية)',
        'method' => 'طريقة الدفع',
        'received_at' => 'وقت الاستلام',
        'methods' => [
            'bml' => 'بنك المالديف',
            'cash' => 'نقداً',
            'transfer' => 'تحويل بنكي',
            'wallet' => 'المحفظة',
            'gift_card' => 'بطاقة هدية',
            'waiver' => 'إعفاء',
        ],
    ],
    'payslip' => [
        'heading' => 'قسيمة الراتب',
        'staff' => 'الموظف',
        'period' => 'الفترة',
        'basic_salary' => 'الراتب الأساسي (روفية)',
        'gross' => 'إجمالي الراتب (روفية)',
        'employee_pension' => 'اشتراك التقاعد (روفية)',
        'tax_withheld' => 'الضريبة المقتطعة (روفية)',
        'unpaid_leave_deduction' => 'خصم الإجازة غير المدفوعة (روفية)',
        'net_pay' => 'صافي الراتب (روفية)',
        'employer_pension_note' => 'تساهم المدرسة بمبلغ إضافي قدره :amount روفية في تقاعدك عن هذه الفترة.',
    ],
];
