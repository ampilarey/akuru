<?php

// The payment pages (BACKLOG C21 slice PY1): coming back from the bank, a
// payment's receipt, a return that lost its reference, the course checkout,
// the bank's required lines on every page that offers a card payment, and
// what the server says on them.
return [
    // Coming back from the bank.
    'processing_title' => 'Checking your payment',
    'checking' => 'Checking your payment with the bank',
    'checking_hint' => 'Please wait. This page updates on its own.',
    'confirmed' => 'Payment received',
    'confirmed_hint' => 'The bank has confirmed your payment.',
    'keep_receipt' => 'Please keep a copy of your receipt for your records.',
    'set_password_title' => 'Save time next time',
    'set_password_hint' => 'Set a password, and you can sign in with it instead of waiting for a code.',
    'set_password' => 'Set a password',
    'skip' => 'Skip',
    'continue' => 'Continue',
    'not_paid' => 'The payment did not go through',
    'not_paid_hint' => 'The bank did not take this payment. You can try again from where you started.',
    'slow' => 'The bank has not confirmed the payment yet.',
    'slow_hint' => 'If you paid, the payment is confirmed on its own once the bank tells us. You can close this page and come back later.',
    'back_home' => 'Back to the home page',

    // A return that lost its reference.
    'missing_title' => 'Payment reference not found',
    'missing_intro' => 'Your browser came back from the bank without the payment\'s reference.',
    'reference' => 'Reference',
    'missing_paid' => 'If you completed the payment, it will still be confirmed.',
    'missing_paid_hint' => 'The bank tells us directly, and the payment reaches your enrollment when it does. This page only means the reference did not come back to your browser.',
    'missing_check' => 'Check :enrollments in a few minutes. If the course has not appeared, contact us with the reference above and we will find the payment.',
    'your_enrollments' => 'your enrollments',

    // A payment's receipt.
    'receipt_title' => 'Payment receipt :number',
    'print' => 'Print or save as PDF',
    'receipt' => 'Receipt',
    'receipt_confirmed' => 'Payment confirmed',
    'billed_to' => 'Billed to',
    'date' => 'Date',
    'maldives_time' => ':time, Maldives time',
    'col_description' => 'Description',
    'col_student' => 'Student',
    'col_amount' => 'Amount',
    'total' => 'Total',
    'paid_through' => 'Paid through',
    'payment_method' => 'Payment method',
    'transaction_id' => 'Transaction ID',
    'keep_copy' => 'Please keep a copy of this receipt for your records.',
    'keep_copy_hint' => 'You can print it, or save it as a PDF, with the button above.',
    'thanks' => 'Thank you for enrolling with Akuru Institute. For questions about this payment, write to :email.',
    'line_course' => 'Course enrollment',
    'gateway_bml' => 'BML Connect',
    'gateway_manual' => 'Recorded at the institute',
    'method_cash' => 'Cash',
    'method_bank_transfer' => 'Bank transfer',
    'method_cheque' => 'Cheque',
    'method_card' => 'Card',
    'method_wallet' => 'Wallet',
    'method_gift_card' => 'Gift card',
    'method_other' => 'Other',

    // The course checkout.
    'checkout_title' => 'Checkout: :course',
    'course_registration' => 'Course registration',
    'fee' => 'Registration fee: :amount',
    'outlet_country' => 'Merchant outlet country: :country',
    'maldives' => 'Maldives',
    'we_accept' => 'We accept',
    'other_cards' => 'and the other cards Bank of Maldives accepts',
    'read_first' => 'Before you pay, please read:',
    'no_duties' => 'No import, export or customs duties apply to our services.',
    'agree' => 'I agree to the :terms, the :refunds and the :privacy.',
    'secure' => 'Bank of Maldives processes the payment securely. We do not keep your card details.',
    'pay' => 'Pay :amount',

    // The bank's required lines.
    'card_brands' => 'We accept American Express, Visa, Mastercard and Maestro',
    'currency' => 'Transaction currency: :currency',
    'mvr' => 'MVR (Maldivian rufiyaa)',
    'secure_ssl' => 'Bank of Maldives (BML) processes payments securely over an encrypted connection. Card details are never stored on our servers.',
    'retain' => 'We recommend you keep a copy of your receipt and your transaction records after you pay.',

    // What the server says.
    'error_reference' => 'That payment reference is not valid.',
    'error_no_receipt' => 'There is no receipt for this payment.',
];
