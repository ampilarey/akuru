<?php

// ROADMAP §5 "All new UIs: Inertia + React. No new Blade screens." and
// CLAUDE.md's Conventions, which say the same thing — see
// tests/Architecture/NoNewBladeScreensTest.php.
//
// Every Blade view in the repository. The rule has held on its own: no Blade
// view has been added since the tree was imported. This pins that, and the
// list may only SHRINK — which makes the count the Blade→Inertia migration
// metric ROADMAP §5 implies and nothing was tracking.
//
// Deleting a Blade screen because its Inertia replacement shipped is the
// expected direction, and the test asks you to update the count below when you
// do.
//
// Count: 219.

return [
    // (root) — 4
    'app.blade.php',
    'dashboard.blade.php',
    'home.blade.php',
    'welcome.blade.php',

    // account — 1
    'account/set-password.blade.php',

    // admin — 30
    'admin/enrollments/index.blade.php',
    'admin/enrollments/payments.blade.php',
    'admin/enrollments/show.blade.php',
    'admin/instructors/form.blade.php',
    'admin/instructors/index.blade.php',
    'admin/prayer-times/broadcasts/form.blade.php',
    'admin/prayer-times/broadcasts/index.blade.php',
    'admin/prayer-times/groups/form.blade.php',
    'admin/prayer-times/groups/index.blade.php',
    'admin/prayer-times/import.blade.php',
    'admin/prayer-times/islands.blade.php',
    'admin/public-site/courses/_cta_fields.blade.php',
    'admin/public-site/courses/_outcomes_fields.blade.php',
    'admin/public-site/courses/create.blade.php',
    'admin/public-site/courses/edit.blade.php',
    'admin/public-site/courses/index.blade.php',
    'admin/public-site/daily-content/form.blade.php',
    'admin/public-site/daily-content/index.blade.php',
    'admin/public-site/daily-content/queue.blade.php',
    'admin/public-site/daily-subscriptions/index.blade.php',
    'admin/public-site/funnel/index.blade.php',
    'admin/public-site/leads/index.blade.php',
    'admin/public-site/pages/create.blade.php',
    'admin/public-site/pages/edit.blade.php',
    'admin/public-site/pages/index.blade.php',
    'admin/public-site/pages/show.blade.php',
    'admin/public-site/research/form.blade.php',
    'admin/public-site/research/index.blade.php',
    'admin/settings/index.blade.php',
    'admin/users/index.blade.php',

    // analytics — 2
    'analytics/dashboard.blade.php',
    'analytics/reports.blade.php',

    // announcements — 3
    'announcements/create.blade.php',
    'announcements/index.blade.php',
    'announcements/show.blade.php',

    // auth — 16
    'auth/confirm-password.blade.php',
    'auth/forgot-password.blade.php',
    'auth/login.blade.php',
    'auth/otp-login.blade.php',
    'auth/otp-verify.blade.php',
    'auth/passwords/confirm.blade.php',
    'auth/passwords/email.blade.php',
    'auth/passwords/otp-request.blade.php',
    'auth/passwords/otp-reset.blade.php',
    'auth/passwords/otp-verify.blade.php',
    'auth/passwords/reset.blade.php',
    'auth/register.blade.php',
    'auth/reset-password.blade.php',
    'auth/verify-email.blade.php',
    'auth/verify-reset-otp.blade.php',
    'auth/verify.blade.php',

    // checkout — 1
    'checkout/course.blade.php',

    // components — 19
    'components/akuru-logo.blade.php',
    'components/application-logo.blade.php',
    'components/auth-session-status.blade.php',
    'components/danger-button.blade.php',
    'components/dropdown-link.blade.php',
    'components/dropdown.blade.php',
    'components/input-error.blade.php',
    'components/input-label.blade.php',
    'components/modal.blade.php',
    'components/nav-link.blade.php',
    'components/payment-trust-bar.blade.php',
    'components/primary-button.blade.php',
    'components/public/footer.blade.php',
    'components/public/nav.blade.php',
    'components/public/picture.blade.php',
    'components/public/viber-icon.blade.php',
    'components/responsive-nav-link.blade.php',
    'components/secondary-button.blade.php',
    'components/text-input.blade.php',

    // courses — 7
    'courses/checkout.blade.php',
    'courses/register-complete.blade.php',
    'courses/register-continue.blade.php',
    'courses/register-enroll-confirm.blade.php',
    'courses/register-otp.blade.php',
    'courses/register-set-password.blade.php',
    'courses/register.blade.php',

    // dashboard — 3
    'dashboard/public-user.blade.php',
    'dashboard/super-admin.blade.php',
    'dashboard/supervisor.blade.php',

    // documents — 7
    'documents/award-certificate.blade.php',
    'documents/course-certificate.blade.php',
    'documents/finance/receipt.blade.php',
    'documents/id-card.blade.php',
    'documents/report-card.blade.php',
    'documents/transcript.blade.php',
    'documents/transfer-certificate.blade.php',

    // e-learning — 5
    'e-learning/arabic-lessons.blade.php',
    'e-learning/index.blade.php',
    'e-learning/islamic-studies.blade.php',
    'e-learning/quran-lessons.blade.php',
    'e-learning/show.blade.php',

    // emails — 6
    'emails/admin-free-enrollment.blade.php',
    'emails/admin-new-enrollment.blade.php',
    'emails/daily-content-digest.blade.php',
    'emails/enrollment-confirmed.blade.php',
    'emails/enrollment-status.blade.php',
    'emails/free-enrollment-confirmed.blade.php',

    // errors — 2
    'errors/404.blade.php',
    'errors/500.blade.php',

    // hifz — 19
    'hifz/dashboard/dean.blade.php',
    'hifz/dashboard/parent.blade.php',
    'hifz/dashboard/student.blade.php',
    'hifz/dashboard/supervisor.blade.php',
    'hifz/dashboard/teacher.blade.php',
    'hifz/enrollments/create.blade.php',
    'hifz/enrollments/index.blade.php',
    'hifz/milestones/index.blade.php',
    'hifz/partials/alerts.blade.php',
    'hifz/programs/create.blade.php',
    'hifz/programs/edit.blade.php',
    'hifz/programs/index.blade.php',
    'hifz/programs/show.blade.php',
    'hifz/reports/haraka-mistakes.blade.php',
    'hifz/reports/index.blade.php',
    'hifz/reports/milestones.blade.php',
    'hifz/reports/parent-follow-up.blade.php',
    'hifz/reports/teacher-completion.blade.php',
    'hifz/reports/weak-students.blade.php',

    // layouts — 3
    'layouts/app.blade.php',
    'layouts/guest.blade.php',
    'layouts/navigation.blade.php',

    // my-enrollments — 1
    'my-enrollments/index.blade.php',

    // partials — 1
    'partials/pwa.blade.php',

    // payments — 3
    'payments/processing.blade.php',
    'payments/receipt.blade.php',
    'payments/return-missing.blade.php',

    // policy — 5
    'policy/placeholder.blade.php',
    'policy/privacy.blade.php',
    'policy/refunds.blade.php',
    'policy/services.blade.php',
    'policy/terms.blade.php',

    // portal — 6
    'portal/certificates.blade.php',
    'portal/dashboard.blade.php',
    'portal/enrollments.blade.php',
    'portal/layout.blade.php',
    'portal/payments.blade.php',
    'portal/profile.blade.php',

    // profile — 4
    'profile/edit.blade.php',
    'profile/partials/delete-user-form.blade.php',
    'profile/partials/update-password-form.blade.php',
    'profile/partials/update-profile-information-form.blade.php',

    // public — 49
    'public/about/index.blade.php',
    'public/achievements/index.blade.php',
    'public/admissions/apply.blade.php',
    // Not a screen: the S1.2 custom-fields partial the two admission forms
    // above @include. It exists because those forms are still Blade; it goes
    // when they do.
    'public/admissions/_custom-fields.blade.php',
    'public/admissions/create.blade.php',
    'public/admissions/thanks.blade.php',
    'public/articles/index.blade.php',
    'public/articles/show.blade.php',
    'public/careers/index.blade.php',
    'public/certificates/verify.blade.php',
    'public/commerce/wallet.blade.php',
    'public/contact/create.blade.php',
    'public/courses/_conversion_badges.blade.php',
    'public/courses/_price.blade.php',
    'public/courses/_syllabus_form.blade.php',
    'public/courses/_waitlist_form.blade.php',
    'public/courses/_whatsapp_link.blade.php',
    'public/courses/index.blade.php',
    'public/courses/show.blade.php',
    'public/daily/_card.blade.php',
    'public/daily/index.blade.php',
    'public/daily/show.blade.php',
    'public/daily/subscribe.blade.php',
    'public/daily/unsubscribed.blade.php',
    'public/events/index.blade.php',
    'public/events/show.blade.php',
    'public/gallery/index.blade.php',
    'public/gallery/show.blade.php',
    'public/home.blade.php',
    'public/home/_daily.blade.php',
    'public/home/_trust.blade.php',
    'public/instructors/show.blade.php',
    'public/lang-test.blade.php',
    'public/layouts/public.blade.php',
    'public/library/index.blade.php',
    'public/library/my.blade.php',
    'public/library/payment-return.blade.php',
    'public/library/reader.blade.php',
    'public/library/show.blade.php',
    'public/news/index.blade.php',
    'public/news/show.blade.php',
    'public/page/show.blade.php',
    'public/partials/json_ld.blade.php',
    'public/partials/prayer-banner-assets.blade.php',
    'public/partials/prayer-banner.blade.php',
    'public/prayer-times/index.blade.php',
    'public/research/index.blade.php',
    'public/research/show.blade.php',
    'public/search.blade.php',
    'public/test.blade.php',

    // quran-progress — 5
    'quran-progress/_form.blade.php',
    'quran-progress/create.blade.php',
    'quran-progress/edit.blade.php',
    'quran-progress/index.blade.php',
    'quran-progress/show.blade.php',

    // students — 1 (the Hifz progress tab; the CRUD screens were retired)
    'students/quran-progress.blade.php',

    // substitutions — 8
    'substitutions/absences/_form.blade.php',
    'substitutions/absences/create.blade.php',
    'substitutions/absences/edit.blade.php',
    'substitutions/absences/index.blade.php',
    'substitutions/requests/create.blade.php',
    'substitutions/requests/edit.blade.php',
    'substitutions/requests/index.blade.php',
    'substitutions/requests/show.blade.php',

    // teachers — 4

];
