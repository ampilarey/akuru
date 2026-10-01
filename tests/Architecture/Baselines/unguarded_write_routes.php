<?php

// SPEC §45 "Backend must enforce permissions" / §44 "Do not rely only on
// frontend button hiding" — see tests/Architecture/WriteRoutesAreGuardedTest.php.
//
// Write routes with no guard the test's (deliberately narrow) detector can see.
// A route here is NOT asserted to be unsafe: this codebase puts rules in
// Actions (rule 5), which the detector cannot follow. Each entry says why it is
// here, and which ones were actually read.
//
// Baseline may only shrink. Count: 64 (the old course portal's profile form left 2026-09-28, SIGN_IN_PLAN ID2b; the three device routes of SPEC §50 joined 2026-09-28: a person's own phones, scoped by user id in the Actions; the add-to-cart route of BOOKSHOP_PLAN
// B2 joined 2026-09-26: a guest's basket has no session to authorise).

return [
    // ---------------------------------------------------------------------
    // Public by design — there is no session yet, so there is nobody to
    // authorise. Rate limiting, not permissions, is what protects these
    // (config/auth-throttle.php, SPEC §32).
    // ---------------------------------------------------------------------
    'login' => 'AuthenticatedSessionController@store — public; throttled.',
    'logout' => 'AuthenticatedSessionController@destroy — ends your own session.',
    'register' => 'RegisteredUserController@store — public; throttled (auth-register).',
    'guest-checkout' => 'GuestCheckoutController@store — public by design (STATUS §5ly): creates the caller\'s own new account; refuses a number that already signs in; throttled.',
    'sign-in' => 'PhoneSignInController@check — pre-auth by necessity (P1); throttled, codes capped by OtpService.',
    'sign-in/code' => 'PhoneSignInController@verify — OTP-proved (P1); throttled.',
    'contact' => 'ContactController@store — public contact form.',
    'admissions' => 'AdmissionController@store — public admission application.',
    'apply' => 'AdmissionController@store — same controller, second public path.',
    'forgot-password' => 'PasswordOtpController@sendOtp — public; throttled.',
    'reset-password' => 'PasswordOtpController@resetPassword — public; token-proved.',
    'reset-password/verify' => 'PasswordOtpController@verifyOtp — public; OTP-proved.',
    'two-factor-challenge' => 'TwoFactorChallengeController@store — STATUS §5lk: public by necessity (the second step before a session exists); only for the person the password or OTP step put in the session; five wrong codes a minute per person; throttled.',
    'account/two-factor/start' => 'TwoFactorController@start — STATUS §5lk: the signed-in person\'s own account only (auth); throttled.',
    'account/two-factor/confirm' => 'TwoFactorController@confirm — STATUS §5lk: own account only (auth); needs a code from the new secret; throttled.',
    'account/two-factor/recovery-codes' => 'TwoFactorController@recoveryCodes — STATUS §5lk: own account only (auth); needs the password; throttled.',
    'account/two-factor/disable' => 'TwoFactorController@disable — STATUS §5lk: own account only (auth); needs the password; throttled.',
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
    'shop/sms-opt-out' => 'ShopSmsController@keyword — COMMERCE_PARITY_PLAN P7b: a STOP reply to the Bookstore\'s SMS offers; unsubscribe without a login. It only ever stops offers.',
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
    'shop/{vendor}/newsletter' => 'NewsletterController@subscribe — BOOKSHOP_PLAN B9c: a public newsletter sign-up with consent; throttled. READ: it only adds or renews the address given, for that shop, and shows nothing back.',
    'shop/cart' => 'ShopCartController@add — BOOKSHOP_PLAN B2: a guest\'s basket, by a token in their own session; throttled. READ: the cart comes from ResolvesCart, never from the request; the sibling shop/cart/{item} aborts 404 without a basket and so passes the detector.',

    // ---------------------------------------------------------------------
    // Scoped to the caller's own data — the identity IS the authorisation, so
    // there is no permission to check. Each of these derives its subject from
    // the session rather than from the request.
    // ---------------------------------------------------------------------
    'password' => 'PasswordController@update — your own password.',
    'profile' => 'ProfileController@update — your own profile.',
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
    'vendor/apply' => 'VendorApplyController@store — BOOKSHOP_PLAN B9a: your own application to open a shop; auth, throttled 5 an hour. READ: the row is keyed by the signed-in user id; the office decides it behind bookshop.manage.',
    'shop/wishlist/{slug}' => 'ShopAccountController@toggleWishlist — BOOKSHOP_PLAN B7: your own wishlist; auth, throttled. READ: the row is keyed by the signed-in user id, never a request value.',
    'shop/products/{slug}/notify' => 'ShopAccountController@toggleStockAlert — B7: your own back-in-stock notice; auth, throttled, keyed by the signed-in user id.',
    'shop/compare/{slug}' => 'ShopController@toggleCompare — STATUS §5li: add a product to this device\'s comparison, or take it out. READ: CompareProductsAction writes only the caller\'s own session list (four at most), of products for sale; public by design, throttled. No database write.',
    'shop/reviews/{review}/helpful' => 'ShopAccountController@helpful — STATUS §5lg: a signed-in customer marks a published review helpful, or takes it back. READ: ProductReviewsAction::toggleHelpful writes only the signed-in user\'s own vote (unique per review and person), refuses their own review; auth, throttled.',
    'shop/products/{slug}/questions' => 'ShopAccountController@question — STATUS §5le: a signed-in customer asks the shop about a product. READ: ProductQuestionsAction::ask writes a question owned by the signed-in user on a product for sale, at most 3 waiting per product; auth, throttled. Shown only once the shop answers.',
    'shop/products/{slug}/reviews' => 'ShopAccountController@review — B7: a review of something you bought. READ: ProductReviewsAction::eligibleItem finds a delivered order line of the signed-in user and refuses otherwise; auth, throttled.',

    // Account linking — READ in full. `LinkAccountAction` requires the target's
    // own password; `SwitchAccountAction` re-reads the verified link from the
    // database and refuses anything not linked to the signed-in user, so the
    // request only ever names which account, never grants one.
    'account/devices' => 'DeviceController@store — READ: RegisterDeviceAction writes only a device for the signed-in user (a token seen again is re-homed to whoever holds the phone now).',
    'account/devices/forget' => 'DeviceController@forget — READ: ForgetDeviceAction::byToken scopes to the signed-in user\'s own rows.',
    'account/devices/{device}' => 'DeviceController@destroy — READ: ForgetDeviceAction::byId scopes to the signed-in user\'s own rows.',
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
    'write/identity' => 'WriterPortalController@identity — your own ID card, stored against your own user id (COMMERCE_PARITY_PLAN P2).',
    'write/items' => 'WriterPortalController@storeItem — creates under your own writer profile.',
    'write/items/{item}' => 'WriterPortalController@updateItem — READ: SaveWriterItemAction enforces own-items-only and editable states.',
    'write/items/{item}/submit' => 'WriterPortalController@submit — own-items-only in the Action.',
    'write/bank-details' => 'WriterPortalController@saveBankDetails — your own payout details.',
    'write/profile' => 'WriterPortalController@saveProfile — your own author page; SaveWriterPublicProfileAction refuses a caller without an active writer profile.',
    'write/payout-request' => 'WriterPortalController@requestPayout — your own balance; gated by library.payouts_enabled.',
    'review/{assignment}' => 'ReviewerPortalController@store — READ: SubmitResearchReviewAction refuses an assignment not assigned to you.',
    'review/{assignment}/declare' => 'ReviewerPortalController@declare — READ: DeclareReviewerNoConflictAction refuses an assignment not assigned to you (R3b).',
    // LENDING_AND_USED_BOOKS_PLAN L1: book lending between people — every write is the signed-in person's own (auth + customer_password, throttled).
    'my-lending/register' => 'MyLendingController@register — your own lender record; RegisterLenderAction keys it on the signed-in user id, never a request value.',
    'my-lending/identity' => 'MyLendingController@identity — your own ID card, sent to the office through the Identity domain under the signed-in user id.',
    'my-lending/books' => 'MyLendingController@storeBook — your own books; the lender is found by the signed-in user (403 if unregistered), and ManageLendingBooksAction scopes every book to that lender.',
    'my-lending/books/{book}' => 'MyLendingController@storeBook — as above; READ: ManageLendingBooksAction looks the book up under the caller\'s own lender_id, so another lender\'s id is a 404.',
    'my-lending/loans/{loan}/{action}' => 'MyLendingController@loan — READ: LendingLoanAction::ownLoan scopes accept/decline/handover/returned to loans on the caller\'s own books, and cancel to the caller\'s own requests; anything else is a 404.',
    'lending/{slug}/request' => 'LendingController@request — a signed-in person\'s own request; LendingLoanAction::request writes it under the signed-in user id and refuses the lender\'s own book.',
];
