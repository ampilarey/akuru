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
    'request' => [
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
];
