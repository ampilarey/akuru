<?php

// The payment pages (BACKLOG C21 slice PY1): coming back from the bank, a
// payment's receipt, a return that lost its reference, the course checkout,
// the bank's required lines on every page that offers a card payment, and
// what the server says on them.
return [
    // Coming back from the bank.
    'processing_title' => 'التحقق من دفعتك',
    'checking' => 'نتحقق من دفعتك مع البنك',
    'checking_hint' => 'يرجى الانتظار. تتحدّث هذه الصفحة من تلقاء نفسها.',
    'confirmed' => 'تم استلام الدفعة',
    'confirmed_hint' => 'أكّد البنك دفعتك.',
    'keep_receipt' => 'يرجى الاحتفاظ بنسخة من إيصالك لسجلاتك.',
    'set_password_title' => 'وفّر الوقت في المرة القادمة',
    'set_password_hint' => 'عيّن كلمة مرور، وسيمكنك تسجيل الدخول بها بدل انتظار رمز.',
    'set_password' => 'تعيين كلمة مرور',
    'skip' => 'تخطي',
    'continue' => 'متابعة',
    'not_paid' => 'لم تتم الدفعة',
    'not_paid_hint' => 'لم يقبل البنك هذه الدفعة. يمكنك المحاولة مرة أخرى من حيث بدأت.',
    'slow' => 'لم يؤكد البنك الدفعة بعد.',
    'slow_hint' => 'إذا كنت قد دفعت، فستتأكد الدفعة تلقائيًا عندما يبلغنا البنك. يمكنك إغلاق هذه الصفحة والعودة لاحقًا.',
    'back_home' => 'العودة إلى الصفحة الرئيسية',

    // A return that lost its reference.
    'missing_title' => 'لم يُعثر على مرجع الدفعة',
    'missing_intro' => 'عاد متصفحك من البنك من دون مرجع الدفعة.',
    'reference' => 'المرجع',
    'missing_paid' => 'إذا أتممت الدفع، فستتأكد الدفعة رغم ذلك.',
    'missing_paid_hint' => 'يبلغنا البنك مباشرة، وتصل الدفعة إلى تسجيلك حين يفعل. هذه الصفحة تعني فقط أن المرجع لم يعد إلى متصفحك.',
    'missing_check' => 'تحقّق من :enrollments بعد بضع دقائق. إذا لم تظهر الدورة، فتواصل معنا مع ذكر المرجع أعلاه وسنجد الدفعة.',
    'your_enrollments' => 'تسجيلاتك',

    // A payment's receipt.
    'receipt_title' => 'إيصال الدفعة :number',
    'print' => 'اطبع أو احفظ بصيغة PDF',
    'receipt' => 'إيصال',
    'receipt_confirmed' => 'تم تأكيد الدفعة',
    'billed_to' => 'صادرة باسم',
    'date' => 'التاريخ',
    'maldives_time' => ':time، بتوقيت المالديف',
    'col_description' => 'الوصف',
    'col_student' => 'الطالب',
    'col_amount' => 'المبلغ',
    'total' => 'الإجمالي',
    'paid_through' => 'دُفعت عبر',
    'payment_method' => 'وسيلة الدفع',
    'transaction_id' => 'رقم المعاملة',
    'keep_copy' => 'يرجى الاحتفاظ بنسخة من هذا الإيصال لسجلاتك.',
    'keep_copy_hint' => 'يمكنك طباعته أو حفظه بصيغة PDF بالزر أعلاه.',
    'thanks' => 'شكرًا لتسجيلك في معهد أكورو. لأي سؤال عن هذه الدفعة، راسلنا على :email.',
    'line_course' => 'التسجيل في دورة',
    'gateway_bml' => 'بوابة BML Connect',
    'gateway_manual' => 'سُجّلت في المعهد',
    'method_cash' => 'نقدًا',
    'method_bank_transfer' => 'تحويل بنكي',
    'method_cheque' => 'شيك',
    'method_card' => 'بطاقة',
    'method_wallet' => 'المحفظة',
    'method_gift_card' => 'بطاقة هدية',
    'method_other' => 'أخرى',

    // The course checkout.
    'checkout_title' => 'الدفع: :course',
    'course_registration' => 'التسجيل في الدورة',
    'fee' => 'رسوم التسجيل: :amount',
    'outlet_country' => 'بلد منفذ التاجر: :country',
    'maldives' => 'المالديف',
    'we_accept' => 'نقبل',
    'other_cards' => 'والبطاقات الأخرى التي يقبلها بنك المالديف',
    'read_first' => 'قبل الدفع، يرجى قراءة:',
    'no_duties' => 'لا تُفرض أي رسوم استيراد أو تصدير أو رسوم جمركية على خدماتنا.',
    'agree' => 'أوافق على :terms و:refunds و:privacy.',
    'secure' => 'يعالج بنك المالديف الدفعة بأمان. لا نحتفظ ببيانات بطاقتك.',
    'pay' => 'ادفع :amount',

    // The bank's required lines.
    'card_brands' => 'نقبل أمريكان إكسبريس وفيزا وماستركارد ومايسترو',
    'currency' => 'عملة المعاملة: :currency',
    'mvr' => 'MVR (الروفية المالديفية)',
    'secure_ssl' => 'يعالج بنك المالديف (BML) المدفوعات بأمان عبر اتصال مشفّر. لا تُخزَّن بيانات البطاقات على خوادمنا أبدًا.',
    'retain' => 'ننصحك بالاحتفاظ بنسخة من إيصالك وسجلات معاملتك بعد الدفع.',

    // What the server says.
    'error_reference' => 'مرجع الدفعة هذا غير صالح.',
    'error_no_receipt' => 'لا يوجد إيصال لهذه الدفعة.',
];
