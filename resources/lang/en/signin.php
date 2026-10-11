<?php

// Signing in and resetting a password (BACKLOG C21 slice SI1): the sign-in
// pages' layout, signing in, the admins' sign-in by code, both password reset
// flows, confirming a password and an email address, and what the server
// says on them.
return [
    // The layout every sign-in page shares.
    'tagline' => 'Quality Islamic education for all ages — the Quran, the Arabic language and Islamic studies in the Maldives.',
    'feature_instructors' => 'Qualified instructors',
    'feature_schedules' => 'Flexible schedules',
    'feature_ages' => 'All ages welcome',
    'quote' => 'Seek knowledge from the cradle to the grave.',

    // Signing in.
    'title' => 'Sign in',
    'intro' => 'Use your email, phone number or ID card number.',
    'identifier' => 'Email, phone or ID card',
    'password' => 'Password',
    'forgot' => 'Forgot your password?',
    'show_password' => 'Show the password',
    'remember' => 'Remember me',
    'admin_access' => 'Admin access',
    'admin_code' => 'Admin sign-in with a code',

    // The admins' sign-in by code.
    'admin_title' => 'Admin sign-in',
    'admin_intro' => 'Enter your email or phone number, and we will send you a one-time code.',
    'email_or_phone' => 'Email or phone number',
    'maldives_hint' => 'For a Maldives number, type the 7 digits without the country code.',
    'send_code' => 'Send the code',
    'back_to_password' => 'Back to signing in with a password',
    'code_title' => 'Enter the code',
    'code_sent_to' => 'We sent a 6-digit code to :contact.',
    'your_contact' => 'your phone or email',
    'code_label' => '6-digit code',
    'code_hint' => 'The code lasts :minutes minutes. The form sends itself once the 6 digits are typed.',
    'verify_sign_in' => 'Verify and sign in',
    'other_account' => 'Use a different account',

    // Resetting a password: the forgot-password pages (an ID card number too).
    'forgot_intro' => 'Forgot your password? Enter your mobile number, email or ID card number, and we will send you a verification code.',
    'forgot_students' => "Students: enter your ID card number, and the code goes to your parent's mobile.",
    'forgot_identifier' => 'Mobile number, email or ID card',
    'send_verification_code' => 'Send a verification code',
    'verify_intro' => 'Enter the 6-digit verification code we sent to your mobile or email.',
    'verification_code' => 'Verification code',
    'verify' => 'Verify',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm the password',
    'reset_password' => 'Reset the password',

    // Resetting a password: the pages the sign-in page's "Forgot your password?" opens.
    'reset_title' => 'Reset your password',
    'reset_intro' => 'Enter your mobile number or email, and we will send you a verification code.',
    'mobile_or_email' => 'Mobile number or email',
    'back_to_sign_in' => 'Back to signing in',
    'reset_with_id_card' => 'Do you sign in with an ID card number? Reset your password with it here.',
    'enter_code_title' => 'Enter the verification code',
    'code_sent_enter' => 'We sent a 6-digit code to :contact. Enter it below.',
    'verify_code' => 'Verify the code',
    'other_number' => 'Use a different number',
    'set_new_password' => 'Set a new password',
    'choose_strong' => 'Choose a strong password for your account.',
    'at_least_8' => 'At least 8 characters',

    // Confirming a password, and an email address.
    'confirm_intro' => 'This is a secure part of the site. Please confirm your password before you go on.',
    'confirm' => 'Confirm',
    'verify_email_intro' => 'Thank you for signing up! Before you start, please verify your email address with the link we just sent you. If it has not arrived, we will gladly send another.',
    'verify_email_sent' => 'A new verification link was sent to the email address you gave when you registered.',
    'resend_verification' => 'Send the verification email again',
    'log_out' => 'Log out',

    // Registering with an email address.
    'name' => 'Name',
    'email' => 'Email',
    'already_registered' => 'Already registered?',
    'register' => 'Register',

    // What the server says.
    'error_no_account' => 'No account has this email or phone number.',
    'error_inactive' => 'Your account is inactive. Please contact support.',
    'error_admin_only' => 'Signing in with a code is for admin accounts only. Please sign in with your password.',
    'error_identifier_first' => 'Please enter your email or phone number first.',
    'error_session_expired' => 'Your session has expired. Please try again.',
    'error_account_not_found' => 'The account was not found. Please try again.',
    'error_too_many' => 'Too many requests. Please wait a little and try again.',
    'error_sign_in_first' => 'Please sign in to continue.',
    'error_verify_contact' => 'Please verify your phone number or email before you go on.',
    'flash_code_sent_phone' => 'A code was sent to your phone. Please enter the 6 digits.',
    'flash_code_sent_email' => 'A code was sent to your email. Please enter the 6 digits.',
    'flash_new_code' => 'A new code was sent.',
    'flash_code_if_registered' => 'If an account exists, we have sent it a verification code.',
    'flash_code_verified' => 'The code is verified. Please set your new password.',
    'flash_password_reset' => 'Your password was reset. You can sign in now.',

    // The offices' last English refusals (slice OF1).
    'error_account_inactive' => 'Your account is inactive. Please contact the office.',
];
