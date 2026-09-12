<?php

/*
 * SPEC §32 "OTP-Ready Rules".
 *
 * The spec states four rules and then says why they exist: "This protects
 * future Dhiraagu SMS integration from cost abuse and spam." Every send here is
 * a message somebody pays for, so the defaults below are the spec's own
 * numbers, not the looser ones the service used to hardcode.
 *
 * §32 also requires these be "configurable in system settings"; they are
 * env-driven here so an operator can tighten them under attack without waiting
 * for a deploy.
 */
return [
    // §32: "Maximum 3 OTP sends per phone number per 15 minutes."
    // Was 5 per 60 minutes, which allowed a burst of five inside the window
    // the spec caps at three.
    'max_sends' => (int) env('OTP_MAX_SENDS', 3),
    'send_window_minutes' => (int) env('OTP_SEND_WINDOW_MINUTES', 15),

    // §32: "Minimum 60-second resend cooldown." Was 30 — half the mandated
    // floor, which doubles the achievable send rate and therefore the bill.
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),

    // §32: "Maximum failed OTP verification attempts before temporary lockout."
    'max_verify_attempts' => (int) env('OTP_MAX_VERIFY_ATTEMPTS', 10),
    'verify_window_minutes' => (int) env('OTP_VERIFY_WINDOW_MINUTES', 15),

    // Failed guesses against one issued code, separate from the windowed
    // counter above: five wrong guesses burn that code rather than the account.
    'max_attempts_per_code' => (int) env('OTP_MAX_ATTEMPTS_PER_CODE', 5),

    // §32: "OTP abuse event logging for admin review." Kept longer than the
    // rate-limit windows, because the point is to see a pattern across days
    // rather than to enforce anything.
    'abuse_log_retention_days' => (int) env('OTP_ABUSE_RETENTION_DAYS', 90),
];
