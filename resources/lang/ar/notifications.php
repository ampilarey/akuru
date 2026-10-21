<?php

// NOTE: Arabic strings below are a first pass and need native review before
// they reach real guardians. See STATUS.md (S1/S2 audit fixes).
return [
    'attendance' => [
        // :name student name, :status translated status below, :date Y-m-d
        'marked' => 'معهد أكورو: تم تسجيل :name كـ :status بتاريخ :date.',
        'status' => [
            'absent' => 'غائب',
            'late' => 'متأخر',
        ],
    ],

    'registers' => [
        'unfilled_title' => 'سجلات لم تُستكمل بعد',
        'unfilled_body' => 'لديك :count سجل/سجلات تجاوزت وقت الحصة ولم تُرسل بعد.',
    ],
    'digest' => [
        'title' => 'الملخص اليومي — :date',
        'body' => 'حالات الغياب/التأخير اليوم: :absent. السجلات غير المستكملة: :unfilled.',
    ],
    'family_digest' => [
        'title' => 'غدًا في المدرسة — :date',
        'body' => ':lessons الواجبات القادمة: :homework. إشعارات جديدة: :notices.',
        'lessons' => ':count lesson(s), first is :first.',
        'no_lessons' => 'No lessons scheduled.',
    ],
    'request' => [
        'submitted_title' => 'طلب جديد من :name',
        'submitted_body' => 'طلب :type بخصوص :regarding: :reason',
        'decision_title' => 'تم :status طلبك',
        'decision_body' => 'تم :status طلب :type الخاص بك. :notes',
        'status' => [
            'approved' => 'قبول',
            'rejected' => 'رفض',
        ],
    ],
    'substitution' => [
        'assigned_title' => 'تم إسناد حصة بديلة',
        'assigned_body' => 'أنت مكلف بتغطية حصة بتاريخ :date.',
    ],
    'behavior' => [
        'logged' => 'معهد أكورو: تم تسجيل :type لـ :name بتاريخ :date.',
        'type' => [
            'notice' => 'ملاحظة',
            'warning' => 'إنذار',
            'incident' => 'حادثة',
        ],
    ],
    // HP3c (ADR-044, STATUS §5tz): notices that were English sentences in code.
    'invoice' => [
        'issued_title' => 'فاتورة :number',
        'issued_body' => 'معهد أكورو: الفاتورة :number لـ:name بمبلغ :amount روفية، مستحقة في :due.',
        'reminder_title' => 'تذكير بالفاتورة :number',
        'reminder_body' => 'معهد أكورو: على الفاتورة :number لـ:name مبلغ :amount روفية غير مدفوع (مستحقة في :due).',
    ],
    'hr' => [
        'expiring_title' => 'وثيقة تنتهي خلال :days يومًا',
        'expiring_body' => 'تنتهي :document في :date',
    ],
    'gift_card' => [
        'redeemed_title' => 'استُخدمت بطاقة الهدية',
        'redeemed_body' => 'أُضيف :amount روفية إلى محفظتك.',
    ],
];
