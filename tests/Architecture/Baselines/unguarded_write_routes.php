<?php

// SPEC §45 "Backend must enforce permissions" / §44 "Do not rely only on
// frontend button hiding" — see tests/Architecture/WriteRoutesAreGuardedTest.php.
//
// Write routes with no guard the test's (deliberately narrow) detector can see.
// A route here is NOT asserted to be unsafe: this codebase puts rules in
// Actions (rule 5), which the detector cannot follow. Each entry says why it is
// here, and which ones were actually read.
//
// Baseline may only shrink. Count: 61.

return [
    // ---------------------------------------------------------------------
    // Public by design — there is no session yet, so there is nobody to
    // authorise. Rate limiting, not permissions, is what protects these
    // (config/auth-throttle.php, SPEC §32).
    // ---------------------------------------------------------------------
    'login' => 'AuthenticatedSessionController@store — public; throttled.',
    'logout' => 'AuthenticatedSessionController@destroy — ends your own session.',
    'register' => 'RegisteredUserController@store — public; throttled (auth-register).',
    'contact' => 'ContactController@store — public contact form.',
    'admissions' => 'AdmissionController@store — public admission application.',
    'apply' => 'AdmissionController@store — same controller, second public path.',
    'forgot-password' => 'PasswordOtpController@sendOtp — public; throttled.',
    'reset-password' => 'PasswordOtpController@resetPassword — public; token-proved.',
    'reset-password/verify' => 'PasswordOtpController@verifyOtp — public; OTP-proved.',
    'otp/request' => 'OtpLoginController@requestOtp — public; throttled (auth-otp-request).',
    'otp/verify' => 'OtpLoginController@verifyOtp — public; OTP-proved.',
    'otp/resend' => 'OtpLoginController@resendOtp — public; throttled.',
    'password/otp/send' => 'OtpPasswordResetController@requestOtp — public; throttled.',
    'password/otp/verify' => 'OtpPasswordResetController@verifyOtp — public; OTP-proved.',
    'password/otp/reset' => 'OtpPasswordResetController@reset — public; OTP-proved.',
    'password/otp/resend' => 'OtpPasswordResetController@resendOtp — public; throttled.',
    'email/verification-notification' => 'EmailVerificationNotificationController@store — sends to your own address.',
    'confirm-password' => 'ConfirmablePasswordController@store — proves your own password.',
    'funnel-events' => 'FunnelEventController@store — public analytics beacon.',
    'daily/sms-opt-out' => 'DailyUnsubscribeController@smsOptOut — unsubscribe must work without a login.',
    'prayer-times/sms-opt-out' => 'PrayerTimesController@smsOptOut — same: unsubscribe without a login.',
    'payments/bml/callback' => 'PaymentController@callback — BML webhook; authenticity is the signature, not a session (rule 12).',

    // Public course registration funnel — a prospective student has no account
    // until part-way through, so each step proves itself by OTP or token.
    'courses/register/start' => 'CourseRegistrationController@start — public funnel step.',
    'courses/register/verify' => 'CourseRegistrationController@verify — OTP-proved.',
    'courses/register/otp/resend-new' => 'CourseRegistrationController@resendNewRegistrationOtp — throttled.',
    'courses/register/set-password' => 'CourseRegistrationController@setPassword — OTP-proved.',
    'courses/register/enroll' => 'CourseRegistrationController@enroll — public funnel step.',
    'courses/register/enroll/confirm' => 'CourseRegistrationController@enrollConfirm — OTP-proved.',
    'courses/register/enroll/resend' => 'CourseRegistrationController@enrollResendOtp — throttled.',
    'courses/{course}/checkout/login' => 'CourseRegistrationController@checkoutLogin — public checkout login.',
    'courses/{course}/syllabus' => 'PublicSite\CourseController@syllabus — READ: sends a public marketing course its published syllabus; writes nothing.',
    'courses/{course}/waitlist' => 'PublicSite\CourseController@waitlist — public waitlist signup.',
    'events/{event}/register' => 'EventController@register — public event signup.',

    // ---------------------------------------------------------------------
    // Scoped to the caller's own data — the identity IS the authorisation, so
    // there is no permission to check. Each of these derives its subject from
    // the session rather than from the request.
    // ---------------------------------------------------------------------
    'password' => 'PasswordController@update — your own password.',
    'profile' => 'ProfileController@update — your own profile.',
    'portal/profile' => 'PortalController@updateProfile — your own profile.',
    'account/set-password' => 'AccountController@setPassword — your own password.',
    'portal/notifications/preferences' => 'PortalNotificationController@savePreferences — your own preferences.',
    'portal/notifications/read' => 'PortalNotificationController@markRead — your own notifications.',
    'api/notifications/{id}/mark-read' => 'NotificationController@markAsRead — READ: passes Auth::id() to the service, which scopes by it.',
    'api/notifications/mark-all-read' => 'NotificationController@markAllAsRead — same, Auth::id()-scoped.',
    'api/notifications/send-test' => 'NotificationController@sendTest — READ: sends only to Auth::id(), i.e. yourself.',
    'learn/pronounce' => 'PronunciationPracticeController@store — your own attempt; throttled 30/min.',
    'learn/lessons/{lesson}/complete' => 'LearnLessonController@complete — your own enrolment progress.',
    'payments/bml/initiate' => 'PaymentController@initiate — starts a payment for your own session.',
    'payments/course/{course}/start' => 'CheckoutController@start — your own checkout.',

    // Account linking — READ in full. `LinkAccountAction` requires the target's
    // own password; `SwitchAccountAction` re-reads the verified link from the
    // database and refuses anything not linked to the signed-in user, so the
    // request only ever names which account, never grants one.
    'account/linked' => 'LinkedAccountController@store — READ: LinkAccountAction requires the target account password.',
    'account/linked/{account}' => 'LinkedAccountController@destroy — READ: UnlinkAccountAction scopes to your own links.',
    'account/switch/{account}' => 'LinkedAccountController@switch — READ: SwitchAccountAction re-reads the verified link; refuses an unlinked account.',

    // Portal — READ in full. Membership and ownership are enforced in the
    // Actions, which is where rule 5 puts them.
    'portal/messages' => 'PortalMessageController@store — thread membership enforced in the Action.',
    'portal/messages/{thread}/poll' => 'PortalMessageController@respondToPoll — READ: RespondToMessagePollAction checks thread membership by user_id.',
    'portal/pickup/pin' => 'PortalPickupController@setPin — your own pickup PIN.',
    'portal/pickup/request' => 'PortalPickupController@request — your own children (guardian-scoped in the Action).',
    'portal/pickup/{notice}/confirm' => 'PortalPickupController@confirm — READ: AdvancePickupNoticeAction refuses a notice whose guardian_user_id is not yours.',

    // Library writer/reviewer portals — READ in full. `SaveWriterItemAction`
    // refuses an item whose writer_id is not yours and refuses states that are
    // no longer editable; `SubmitResearchReviewAction` refuses an assignment
    // whose reviewer_user_id is not yours.
    'write/apply' => 'WriterPortalController@apply — your own writer application.',
    'write/items' => 'WriterPortalController@storeItem — creates under your own writer profile.',
    'write/items/{item}' => 'WriterPortalController@updateItem — READ: SaveWriterItemAction enforces own-items-only and editable states.',
    'write/items/{item}/submit' => 'WriterPortalController@submit — own-items-only in the Action.',
    'write/bank-details' => 'WriterPortalController@saveBankDetails — your own payout details.',
    'write/payout-request' => 'WriterPortalController@requestPayout — your own balance; gated by library.payouts_enabled.',
    'review/{assignment}' => 'ReviewerPortalController@store — READ: SubmitResearchReviewAction refuses an assignment not assigned to you.',
];
