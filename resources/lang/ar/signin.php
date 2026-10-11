<?php

// Signing in and resetting a password (BACKLOG C21 slice SI1): the sign-in
// pages' layout, signing in, the admins' sign-in by code, both password reset
// flows, confirming a password and an email address, and what the server
// says on them.
return [
    // The layout every sign-in page shares.
    'tagline' => 'تعليم إسلامي متميز لجميع الأعمار — القرآن الكريم واللغة العربية والدراسات الإسلامية في جزر المالديف.',
    'feature_instructors' => 'معلمون مؤهلون',
    'feature_schedules' => 'مواعيد مرنة',
    'feature_ages' => 'أهلًا بجميع الأعمار',
    'quote' => 'اطلبوا العلم من المهد إلى اللحد.',

    // Signing in.
    'title' => 'تسجيل الدخول',
    'intro' => 'استخدم بريدك الإلكتروني أو رقم هاتفك أو رقم بطاقة هويتك.',
    'identifier' => 'البريد الإلكتروني أو الهاتف أو بطاقة الهوية',
    'password' => 'كلمة المرور',
    'forgot' => 'نسيت كلمة المرور؟',
    'show_password' => 'إظهار كلمة المرور',
    'remember' => 'تذكرني',
    'admin_access' => 'دخول المشرفين',
    'admin_code' => 'دخول المشرفين برمز',

    // The admins' sign-in by code.
    'admin_title' => 'دخول المشرف',
    'admin_intro' => 'أدخل بريدك الإلكتروني أو رقم هاتفك، وسنرسل إليك رمزًا لمرة واحدة.',
    'email_or_phone' => 'البريد الإلكتروني أو رقم الهاتف',
    'maldives_hint' => 'لرقم مالديفي، اكتب الأرقام السبعة دون رمز الدولة.',
    'send_code' => 'أرسل الرمز',
    'back_to_password' => 'العودة إلى الدخول بكلمة المرور',
    'code_title' => 'أدخل الرمز',
    'code_sent_to' => 'أرسلنا رمزًا من ستة أرقام إلى :contact.',
    'your_contact' => 'هاتفك أو بريدك الإلكتروني',
    'code_label' => 'رمز من ستة أرقام',
    'code_hint' => 'يبقى الرمز صالحًا :minutes دقيقة. يُرسَل النموذج تلقائيًا بمجرد كتابة الأرقام الستة.',
    'verify_sign_in' => 'تحقق وسجّل الدخول',
    'other_account' => 'استخدم حسابًا آخر',

    // Resetting a password: the forgot-password pages (an ID card number too).
    'forgot_intro' => 'نسيت كلمة المرور؟ أدخل رقم جوالك أو بريدك الإلكتروني أو رقم بطاقة هويتك، وسنرسل إليك رمز تحقق.',
    'forgot_students' => 'الطلاب: أدخلوا رقم بطاقة الهوية، ويصل الرمز إلى جوال ولي الأمر.',
    'forgot_identifier' => 'رقم الجوال أو البريد الإلكتروني أو بطاقة الهوية',
    'send_verification_code' => 'أرسل رمز تحقق',
    'verify_intro' => 'أدخل رمز التحقق المكوّن من ستة أرقام الذي أرسلناه إلى جوالك أو بريدك الإلكتروني.',
    'verification_code' => 'رمز التحقق',
    'verify' => 'تحقق',
    'new_password' => 'كلمة المرور الجديدة',
    'confirm_password' => 'تأكيد كلمة المرور',
    'reset_password' => 'إعادة تعيين كلمة المرور',

    // Resetting a password: the pages the sign-in page's "Forgot your password?" opens.
    'reset_title' => 'أعد تعيين كلمة مرورك',
    'reset_intro' => 'أدخل رقم جوالك أو بريدك الإلكتروني، وسنرسل إليك رمز تحقق.',
    'mobile_or_email' => 'رقم الجوال أو البريد الإلكتروني',
    'back_to_sign_in' => 'العودة إلى تسجيل الدخول',
    'reset_with_id_card' => 'هل تسجّل الدخول برقم بطاقة الهوية؟ أعد تعيين كلمة مرورك به من هنا.',
    'enter_code_title' => 'أدخل رمز التحقق',
    'code_sent_enter' => 'أرسلنا رمزًا من ستة أرقام إلى :contact. أدخله أدناه.',
    'verify_code' => 'تحقق من الرمز',
    'other_number' => 'استخدم رقمًا آخر',
    'set_new_password' => 'عيّن كلمة مرور جديدة',
    'choose_strong' => 'اختر كلمة مرور قوية لحسابك.',
    'at_least_8' => 'ثمانية أحرف على الأقل',

    // Confirming a password, and an email address.
    'confirm_intro' => 'هذا جزء آمن من الموقع. يرجى تأكيد كلمة المرور قبل المتابعة.',
    'confirm' => 'تأكيد',
    'verify_email_intro' => 'شكرًا لتسجيلك! قبل أن تبدأ، يرجى التحقق من بريدك الإلكتروني عبر الرابط الذي أرسلناه إليك للتو. إن لم يصلك، فسنرسل إليك رابطًا آخر بكل سرور.',
    'verify_email_sent' => 'أُرسل رابط تحقق جديد إلى البريد الإلكتروني الذي قدّمته عند التسجيل.',
    'resend_verification' => 'أعد إرسال رسالة التحقق',
    'log_out' => 'تسجيل الخروج',

    // Registering with an email address.
    'name' => 'الاسم',
    'email' => 'البريد الإلكتروني',
    'already_registered' => 'مسجّل من قبل؟',
    'register' => 'سجّل',

    // What the server says.
    'error_no_account' => 'لا يوجد حساب بهذا البريد الإلكتروني أو رقم الهاتف.',
    'error_inactive' => 'حسابك غير نشط. يرجى التواصل مع الدعم.',
    'error_admin_only' => 'الدخول برمز متاح لحسابات المشرفين فقط. يرجى تسجيل الدخول بكلمة المرور.',
    'error_identifier_first' => 'يرجى إدخال بريدك الإلكتروني أو رقم هاتفك أولًا.',
    'error_session_expired' => 'انتهت الجلسة. يرجى المحاولة مرة أخرى.',
    'error_account_not_found' => 'لم يُعثر على الحساب. يرجى المحاولة مرة أخرى.',
    'error_too_many' => 'طلبات كثيرة جدًا. يرجى الانتظار قليلًا ثم المحاولة مرة أخرى.',
    'error_sign_in_first' => 'يرجى تسجيل الدخول للمتابعة.',
    'error_verify_contact' => 'يرجى التحقق من رقم هاتفك أو بريدك الإلكتروني قبل المتابعة.',
    'flash_code_sent_phone' => 'أُرسل رمز إلى هاتفك. يرجى إدخال الأرقام الستة.',
    'flash_code_sent_email' => 'أُرسل رمز إلى بريدك الإلكتروني. يرجى إدخال الأرقام الستة.',
    'flash_new_code' => 'أُرسل رمز جديد.',
    'flash_code_if_registered' => 'إن كان هناك حساب، فقد أرسلنا إليه رمز تحقق.',
    'flash_code_verified' => 'تم التحقق من الرمز. يرجى تعيين كلمة المرور الجديدة.',
    'flash_password_reset' => 'أُعيد تعيين كلمة المرور. يمكنك تسجيل الدخول الآن.',

    // The offices' last English refusals (slice OF1).
    'error_account_inactive' => 'حسابك غير نشط. يُرجى التواصل مع المكتب.',
];
