<?php

/*
 * Laravel's own sign-in lines in Arabic (BACKLOG C21, slice SI1). The
 * framework ships English alone, so a wrong password or too many tries on an
 * Arabic sign-in page was refused in English. Every key of the framework's
 * English file is here, with the same placeholders, as `validation.php` is.
 */

return [
    'failed' => 'بيانات الدخول هذه لا تطابق سجلاتنا.',
    'password' => 'كلمة المرور المدخلة غير صحيحة.',
    'throttle' => 'محاولات دخول كثيرة جدًا. يرجى المحاولة مرة أخرى بعد :seconds ثانية.',
];
