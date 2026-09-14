<?php

/**
 * Every place the application starts a session, and **what the caller proved
 * first**.
 *
 * See `tests/Architecture/SessionsAreEarnedTest.php`. **This list may only
 * shrink.**
 *
 * ## Why this exists
 *
 * `Auth::login()` was the vector for two P0s on the same day (#367, #368). In
 * both, the call itself looked ordinary — a user object, fetched a few lines
 * up, signed in. What was wrong was upstream: the id came from
 * `session('pending_user_id')`, which a **public** route wrote from a phone
 * number in a request body, when an OTP was *sent* rather than entered.
 *
 * Reading the call tells you nothing. Reading what authorised it is the whole
 * question, and nothing in the code makes you ask it. So this list does.
 *
 * ## The rule for an entry
 *
 * Name the **proof**, not the intent. "the user just registered" is a story;
 * "`Hash::check` against the submitted password, rate-limited" is a fact the
 * next reader can go and check. An entry that cannot name a credential,
 * a verified one-time code, or an existing authenticated session is describing
 * a hole.
 *
 * @return array<string, string>
 */
return [
    // --- A credential was checked ------------------------------------------
    'app/Http/Requests/Auth/LoginRequest.php' => 'The password login. `Auth::attempt`-equivalent: the submitted password is checked before this line, behind the `auth-login` throttle.',
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::checkoutLogin' => '`Hash::check` against the submitted password, rate-limited per key, with a deliberately generic failure that does not reveal whether the account exists.',

    // --- A one-time code was verified --------------------------------------
    'app/Domains/Identity/Http/Controllers/Auth/OtpLoginController.php' => 'Immediately after `OtpService::verify()`, which throws on a wrong or expired code and rate-limits per contact and per code.',
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::verify' => 'Both branches sit after `OtpService::verify()` for the contact the funnel is about. This is the step the two P0s below were skipping.',

    // --- The session is the account's own creation -------------------------
    'app/Domains/Identity/Http/Controllers/Auth/RegisteredUserController.php' => 'Signs in the account this request just created, with the password it just set. There is nobody else it could be.',

    // --- Proof carried forward from a verified step ------------------------
    // The two P0s. `pending_user_id` says which account the funnel is about
    // and never said anybody owned it, because `start` writes it when the code
    // is sent. These three now require `otp_verified_user_id` — set only by a
    // successful verify, compared against the user id, and consumed on use.
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::setPassword' => 'Requires `otpWasVerifiedFor()` for this exact user id (#367). Before that, a phone number alone rewrote the password, name, date of birth and national ID.',
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::enroll' => 'Goes through `verifiedPendingUser()`, which returns nothing unless this session entered the code (#368). Before that, two POSTs produced a session as somebody else.',
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::continueForm' => 'The same helper, fixed in the same change — the other half of #368.',

    // --- An existing authenticated session ---------------------------------
    'app/Domains/Identity/Actions/SwitchAccountAction.php' => 'Switches to an account already linked to the signed-in one: re-reads the verified link from the database and refuses anything not linked, so the request names which account and never grants one. The session id is regenerated, which is what stops this being session fixation.',

    // --- A bearer token ----------------------------------------------------
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::resume' => 'A random v4 UUID from `?flow=`, matched on its own for an anonymous caller — so the link IS the credential. Defensible: 24-hour expiry, never sent by SMS or email, lives only in the returning visitor\'s own URL. Recorded in KNOWN_ISSUES with what nobody had written down: not single-use, survives in browser history, and grants a **full** session rather than one scoped to finishing a registration. Narrowing those is a product decision.',
];
