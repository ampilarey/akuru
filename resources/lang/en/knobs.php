<?php

// ST4 (STATUS §5uw): the business settings each product's office changes on a screen (config/office-knobs.php).
return [
    'save' => 'Save',
    'saved' => 'Saved. It is in force now.',
    'on' => 'on',
    'off' => 'off',
    'server_value' => 'The server\'s own value:',
    'error_unknown' => 'That group of settings is not on this page.',
    'error_range' => ':label must be a number from :min to :max.',
    'error_at_most' => ':label cannot be more than :other.',

    'lending_title' => 'Lending settings',
    'lending_intro' => 'How long a book may be kept, how many a lender may list, and how lending notices go out.',
    'lending_group_loans' => 'Loans and listings',
    'lending_group_notices' => 'Reminders and notices',
    'lending_default_days' => 'Usual loan, in days',
    'lending_default_days_hint' => 'The length a lender is offered when they list a book. They may change it.',
    'lending_max_days' => 'Longest loan, in days',
    'lending_max_days_hint' => 'No lender may set a loan longer than this.',
    'lending_max_books' => 'Books one lender may list',
    'lending_max_books_hint' => 'The most books one person may have on the shelf at a time.',
    'lending_free_taken_days' => 'Days a taken give-away stays shown',
    'lending_free_taken_days_hint' => 'A free item marked Taken stays on the Free items page this long, then leaves it.',
    'lending_remind_days_before' => 'Remind this many days before a book is due',
    'lending_remind_days_before_hint' => 'The borrower is reminded this many days ahead, on the day, and every day overdue. 0 starts on the day.',
    'lending_notices_email' => 'Send lending notices by email too',
    'lending_notices_email_hint' => 'Notices always go in the app. Switch this off to stop the email copy.',
    'lending_notices_sms' => 'Send lending notices by SMS too',
    'lending_notices_sms_hint' => 'Each SMS costs money. Switch this off to keep lending notices to the app and email.',

    'wallet_title' => 'Wallet settings',
    'wallet_intro' => 'When the office is told about money added to a wallet by hand.',
    'wallet_group_alerts' => 'Alerts',
    'wallet_alert_credit_at' => 'Alert on a hand credit of this much (MVR) or more',
    'wallet_alert_credit_at_hint' => 'When someone in the office adds this much or more to a wallet by hand, the other commerce managers are told in the app.',
];
