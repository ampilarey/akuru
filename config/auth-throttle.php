<?php

/*
 * SPEC §32 "Authentication and Rate Limiting".
 *
 * > Build rate limiting into authentication from Phase 1.
 * >
 * > Throttle: Login attempts · **Registration attempts** · **Password reset
 * > requests** · OTP send requests · OTP verification attempts
 * >
 * > Rate limits must apply: **Per IP address** · Per account identifier where
 * > applicable · Per phone number for OTP
 *
 * Three of the five were already enforced and enforced well. `LoginRequest`
 * rate-limits by identifier **and** IP; `OtpService` caps sends and verify
 * attempts per contact with §32's own numbers in `config/otp.php`.
 *
 * The two missing ones share a shape: they are limited **per target**, or not
 * at all, and §32 also asks for **per IP**.
 *
 * - **Registration had no limit of any kind** — no middleware, no
 *   `RateLimiter`. `unique:users,email` answers "does this address already have
 *   an account?" on every attempt, so an unthrottled endpoint is both an open
 *   account-creation firehose and a free enumeration oracle.
 *
 * - **Password reset and OTP request are limited per contact**, which cannot
 *   see the attack that matters: the controller looks a user up by national ID,
 *   passport, email or phone **before** `OtpService` is reached, so every probe
 *   uses a different contact and never touches the per-contact counter. That
 *   makes the endpoint an unlimited "does this national ID exist?" oracle, and
 *   §32's per-IP limit is exactly what closes it.
 *
 * Env-driven for the same reason `config/otp.php` is: §32 requires these be
 * "configurable in system settings", and an operator under attack should be
 * able to tighten them without waiting for a deploy.
 */
return [
    // Account creation. Generous enough for a family signing several children
    // up in one sitting, tight enough that a script is not creating thousands.
    'register_per_ip' => (int) env('AUTH_THROTTLE_REGISTER_PER_IP', 10),
    'register_window_minutes' => (int) env('AUTH_THROTTLE_REGISTER_WINDOW', 60),

    // "I forgot my password" from one address. A real person does this once or
    // twice; a hundred attempts is somebody walking a list of identifiers.
    'password_reset_per_ip' => (int) env('AUTH_THROTTLE_PASSWORD_RESET_PER_IP', 10),
    'password_reset_window_minutes' => (int) env('AUTH_THROTTLE_PASSWORD_RESET_WINDOW', 15),

    // OTP requests from one address, across all contacts. The per-phone limit
    // in `config/otp.php` still applies and is the tighter one for a single
    // target; this is the one that sees a spread attack.
    'otp_request_per_ip' => (int) env('AUTH_THROTTLE_OTP_REQUEST_PER_IP', 15),
    'otp_request_window_minutes' => (int) env('AUTH_THROTTLE_OTP_REQUEST_WINDOW', 15),
];
