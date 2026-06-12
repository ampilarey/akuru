# Akuru Institute — Next Steps

Last updated: February 2026

---

## 🔴 Before Going Live (Critical)

### 1. Switch BML to Production
- In `.env` on the production server, update:
  ```
  BML_ENV=production
  BML_API_KEY=<production key>
  BML_APP_ID=<production app id>
  BML_DEFAULT_CURRENCY=MVR
  ```
- Test a real MVR payment end-to-end before announcing the site.

### 2. Abandoned Payment Cleanup Job
- Payments that were initiated but never confirmed by BML stay as `status = 'initiated'` indefinitely.
- Create a scheduled Artisan command (e.g. `payments:expire-abandoned`) that:
  - Finds payments with `status = 'initiated'` older than 24 hours
  - Marks them as `status = 'expired'`
  - Logs the count for monitoring
- Register it in `routes/console.php` to run daily via the scheduler.

---

## 🟠 High Priority (User-Facing)

### 3. Enrollment Confirmation Email / Receipt
- After BML confirms payment, send the student a proper confirmation email with:
  - Course name, start date, schedule
  - Amount paid, currency, payment reference
  - Contact info for the institute
- A PDF receipt download link (or attach PDF directly)
- The `PaymentService::sendConfirmationEmail()` stub already exists — needs a proper `Mailable` and Blade template.

### 4. Student Dashboard
- Logged-in students should be able to see:
  - Their active enrollments with course details
  - Payment status for each enrollment
  - Payment reference / receipt download
  - Schedule / class times
- Route: `/en/my/enrollments` (already exists as `my.enrollments`, likely needs UI improvements)

### 5. Admin Enrollment Approval Flow
- When a course has `requires_admin_approval = true`, after payment:
  - Admin receives an email/SMS notification
  - Admin can go to the admin panel and approve or reject with a reason
  - Student is notified of the decision via email/SMS
- Currently approval is manual with no notification system.

---

## 🟡 Operational Improvements

### 6. Admin Notifications
- When a new **paid enrollment** comes in, notify admin(s) via:
  - Email (with student name, course, amount)
  - SMS (brief summary)
- The `PaymentService::sendAdminNewEnrollmentNotification()` method exists but check it sends to all configured admins.

### 7. Receipt / Invoice PDF
- Generate a PDF receipt using a package like `barryvdh/laravel-dompdf`
- Include: student name, course, date, amount, payment reference, institute logo
- Make it downloadable from the student dashboard and send as email attachment

### 8. Password Reset Flow
- Ensure the "Forgot password?" link on the checkout login tab works end-to-end
- Route: `password.otp.request` — verify it sends OTP and allows password reset correctly

---

## 🟢 Content & Polish

### 9. About Us Page
- Fill in real content: institute history, mission, team/teachers
- Upload a cover image
- Can be edited from Admin → Public Site → Pages

### 10. Homepage Real Content
- Replace any placeholder testimonials, stats, or sections with real data
- Ensure featured courses, latest news, and upcoming events are pulling from the database

### 11. Course Pages
- Make sure all courses have:
  - A proper description (no raw HTML showing)
  - Schedule / class times
  - Instructor names
  - Cover image

### 12. Multi-language Content
- Add Dhivehi (DV) and Arabic (AR) translations for course titles, descriptions, and news articles where needed
- The translation system is in place — just needs content

---

## 🔵 Technical / Infrastructure

### 13. Error Monitoring
- Set up error tracking (e.g. Sentry, Flare, or Laravel's built-in logging to a service)
- So server errors are caught and reported proactively

### 14. BML Webhook IP Allowlist
- BML sends webhooks from specific IP addresses
- Add their IPs to `config/bml.php` under `webhook_allowed_ips` to reject spoofed webhook calls
- Check with BML for their current webhook IP list

### 15. Session Driver Review
- Currently using `database` session driver — ensure the `sessions` table is being pruned regularly
- Add `php artisan session:gc` or use the built-in scheduler task

### 16. Image Optimisation
- Compress uploaded cover images for courses, news, events
- Consider using WebP format for faster page loads

---

## ✅ Already Done (Reference)

- BML payment gateway integration (UAT tested, deferred enrollment)
- OTP registration flow (mobile + email)
- User account creation after OTP verification (not before)
- Enrollment only created after payment confirmed
- Terms & Conditions and Refund Policy pages
- Multi-language support (EN, DV, AR) with all translation keys populated
- Admin panel for courses, news, events, gallery, pages
- Cookie consent banner
- Payment trust bar (BML compliance)
- OTP consent notice (digital signature) on enrollment confirm page
