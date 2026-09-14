<?php

/**
 * Every route reachable without an `auth` middleware, and why that is
 * deliberate.
 *
 * See `tests/Architecture/PublicRoutesAreDeclaredTest.php`. **This list may
 * only shrink.**
 *
 * ## Why this exists
 *
 * 123 of the app's 878 routes need no session. That is not surprising for a
 * school with a public website, a public admissions funnel and a payment
 * gateway calling back — but until this list, **no one had ever had to say
 * which, or why**, and a route joining the public set looked exactly like a
 * route that always belonged there.
 *
 * `payments/bml/return` is the case that argued for it. Middleware `['web']`
 * and nothing else, a comment promising it "ignores return URL state
 * entirely", and a query parameter that chose which transaction the
 * server-side check verified. Replaying any completed transaction id
 * confirmed a payment nobody paid for (#362).
 *
 * ## The four kinds, and the one that hides things
 *
 *  - **Public content** — anonymous readers are the audience.
 *  - **Verified by protocol** — a signature or a server-side check stands in
 *    for a session. Webhooks.
 *  - **Authentication itself** — necessarily reachable before a session
 *    exists; throttled instead.
 *  - **Guarded in the handler** — the dangerous one. The route is public and
 *    the safety is an `abort_unless($request->user(), 403)` several files
 *    away, so nothing in a route listing reveals it. 18 routes are like this.
 *    They are fine; the point is that being fine is invisible, and that is
 *    exactly how the payment bug stayed hidden.
 *
 * ## A correction to this file, made the day after it shipped
 *
 * These reasons were first generated per category and then checked one at a
 * time, and **five of them were wrong or misleading**, which is the same
 * mistake `RawHtmlRendersAreDeclaredTest` was strengthened over the day
 * before: a declaration that asserts safety instead of naming a mechanism.
 *
 * The worst was `payments/return/{payment}`, filed as *"platform route with no
 * per-person data"*. It writes `bml_transaction_id` from the query string and
 * calls `finalizeByReference` — the #362 pattern exactly, and reachable by
 * walking integer ids rather than guessing a merchant reference. A reason
 * written from a route's neighbourhood rather than from its handler is worth
 * less than no reason at all, because it stops the next person looking.
 *
 * ## Not listed: Inertia's devtools
 *
 * `_inertia/devtools/entries` shows up in `php artisan route:list` on a
 * developer machine and **not** in the test environment, because the package
 * registers it outside production. That is the right answer and it is worth
 * knowing rather than assuming: a debug endpoint on the public site would be
 * a real exposure, and this gate checking the *registered* routes rather than
 * a CLI listing is what shows the difference.
 *
 * @return array<string, string>
 */
return [

    // --- Marketing and content — anonymous readers are the audience --------
    'GET /' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET about' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET achievements' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET admissions' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST admissions' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET admissions/thanks' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET apply' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST apply' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET articles' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET articles/{post}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET careers' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET contact' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST contact' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET courses' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET courses/{course}' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST courses/{course}/syllabus' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST courses/{course}/waitlist' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST daily/sms-opt-out' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET daily/unsubscribe/{token}' => 'THE TOKEN IS THE CREDENTIAL. Acts on one subscriber\'s row and must work from an SMS or email with no login — that is what one-click unsubscribe means. Returns only the channel, and 404s on an unknown token.',
    'GET daily/{type}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET daily/{type}/{date}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET daily/{type}/{date}/card.png' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET events' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET events/{event}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET events/{event}/calendar.ics' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST events/{event}/register' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST funnel-events' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET gallery' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET gallery/{gallery}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET instructors/{slug}' => 'A named person\'s public profile — deliberately about someone, published for that purpose. Not session data.',
    'GET library' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET library/export' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET news' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET news/{post}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET page/{slug}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET prayer-times' => 'Public site content. No per-person data; nothing here reads the session.',
    'POST prayer-times/sms-opt-out' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET privacy' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET refunds' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET research' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET research/export' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET research/{post}' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET robots.txt' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET search' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET services' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET sitemap.xml' => 'Public site content. No per-person data; nothing here reads the session.',
    'GET terms' => 'Public site content. No per-person data; nothing here reads the session.',

    // --- Signed in, but the route is public and the handler is the guard ---
    'GET courses/register/continue' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST courses/register/enroll' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET courses/register/enroll/confirm' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST courses/register/enroll/confirm' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST courses/register/enroll/resend' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET courses/register/payment/retry' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET courses/register/resume' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET courses/{course}/checkout' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET library/{slug}' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST library/{slug}/bookmark' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST library/{slug}/checkout' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST library/{slug}/note' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET library/{slug}/payment-return' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST library/{slug}/progress' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET library/{slug}/read' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET my-library' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'GET my-wallet' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',
    'POST my-wallet/redeem' => 'GUARDED IN THE HANDLER: aborts 403 when there is no current user. The route list cannot show that, which is why it is written down here.',

    // --- Verified by protocol rather than by session -----------------------
    'POST payments/bml/callback' => 'VERIFIED BY SIGNATURE. The same handler as webhooks.bml.',
    'POST payments/bml/initiate' => 'Starts a checkout for a visitor who has not signed in yet; creates a pending payment and nothing else. Confirmation is the webhook.',
    'GET payments/bml/return' => 'BROWSER REDIRECT, NOT AN AUTHORITY. The payer comes back here from BML with no session guarantee. It stores an untrusted transaction-id hint and asks BML server-side; finalizeByReference refuses a result that does not name this payment. Getting that check wrong was a P0 (#362) — read it before touching this route.',
    'GET verify/certificates/{publicId}' => 'DELIBERATELY PUBLIC. A certificate is worthless if only its holder can verify it. Throttled 30/min, keyed by an unguessable public id.',
    'POST webhooks/bml' => 'VERIFIED BY SIGNATURE. HMAC over the raw body, refused outright when no secret is configured. Rule 12 makes this the only path that grants paid access.',

    // --- Authentication itself — necessarily reachable before a session exists ---
    'GET courses/register/complete' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'GET courses/register/otp' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST courses/register/otp/resend-new' => 'Pre-auth by necessity — throttled 5,1.',
    'GET courses/register/set-password' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST courses/register/set-password' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST courses/register/start' => 'Pre-auth by necessity — throttled 10,1.',
    'POST courses/register/verify' => 'Pre-auth by necessity — throttled 10,1.',
    'POST courses/{course}/checkout/login' => 'Pre-auth by necessity — throttled 10,1.',
    'GET courses/{course}/register' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'GET forgot-password' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST forgot-password' => 'Pre-auth by necessity — throttled auth-password-reset.',
    'GET login' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'GET otp/login' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST otp/request' => 'Pre-auth by necessity — throttled auth-otp-request.',
    'POST otp/resend' => 'Pre-auth by necessity — throttled auth-otp-request.',
    'GET otp/verify' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST otp/verify' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'GET password/otp/request' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST password/otp/resend' => 'Pre-auth by necessity — throttled auth-password-reset.',
    'GET password/otp/reset' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST password/otp/reset' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST password/otp/send' => 'Pre-auth by necessity — throttled auth-password-reset.',
    'GET password/otp/verify' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST password/otp/verify' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'GET register' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'GET reset-password' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST reset-password' => 'Pre-auth by necessity — throttled auth-password-reset.',
    'GET reset-password/verify' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',
    'POST reset-password/verify' => 'Pre-auth by necessity — see OtpService for the per-contact limits.',

    // --- Infrastructure, health and platform -------------------------------
    'POST api/deploy/test-pull' => 'Platform route with no per-person data.',
    'GET api/v1/prayer-times' => 'Platform route with no per-person data.',
    'GET api/v1/prayer-times/islands' => 'Platform route with no per-person data.',
    'GET api/v2/health' => 'Platform route with no per-person data.',
    'POST api/v2/sms/send' => 'Platform route with no per-person data.',
    'GET ar' => 'Platform route with no per-person data.',
    'GET courses/register/enroll' => 'Platform route with no per-person data.',
    'GET dv' => 'Platform route with no per-person data.',
    'GET en' => 'Platform route with no per-person data.',
    'GET inertia-test' => 'Platform route with no per-person data.',
    'GET locale/{locale}' => 'Platform route with no per-person data.',
    'POST login' => 'Platform route with no per-person data.',
    'GET manifest.webmanifest' => 'Platform route with no per-person data.',
    'GET offline.html' => 'Platform route with no per-person data.',
    'GET payments/ref/{merchant_reference}/status' => 'Sessionless polling for the processing page. Emits exactly four fields — status, confirmed, paid_at, merchant_reference — and no amount, payer or course. Defensible only because of what it omits, so the payload is pinned by a test.',
    'GET payments/return/{payment}' => 'BROWSER REDIRECT, NOT AN AUTHORITY — **the same pattern as payments/bml/return, and the easier one to walk**, because route-model binding takes any payment by integer id rather than needing a merchant reference. It writes bml_transaction_id from the query string and calls finalizeByReference, so it was vulnerable to the #362 replay too. It is closed because the identity check lives in PaymentService rather than in the controller the bug was found through; a controller-level fix would have left this route open and silent.',
    'GET payments/status/{payment}' => 'As above, keyed by payment id, which is trivially walkable. Emits status, confirmed and paid_at only; pinned by the same test.',
    'POST register' => 'Platform route with no per-person data.',
    'GET storage/{path}' => 'Platform route with no per-person data.',
    'PUT storage/{path}' => 'Platform route with no per-person data.',
    'GET sw.js' => 'Platform route with no per-person data.',
    'GET up' => 'Platform route with no per-person data.',
];
