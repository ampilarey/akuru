# Customers, sellers and fulfilment: the Bake & Grill model (plan)

**Status:** audited 2026-09-30 against this repository and the owner's
Bake & Grill repository (read-only, `/home/user/ampilarey/bakeandgrill`);
decisions made by the owner the same day (§4). Slices P1–P8 in §5, one PR each, in that order. **P1 shipped 2026-09-30 (STATUS §5ma); P2 the same day (§5mb).**

**Owner's brief (2026-09-30):** "1) how seller and authors are registered,
they must upload both sides of the id cards, when the account is verified
they can use the service. 2) how customers are handled in bookstore and
digital library — read the bakeandgrill repo … customer can order with
only mobile and OTP, when he logs in he will be asked to set up a
password, next time he will use the password only, no OTP. 3) those who
register for online courses must upload their id card both sides. 4) all
the customer-based features must contain the same as bake and grill. 5)
all the selling-based features the same. 6) when a vendor lists an item,
it should be approved by admin. 7) when any purchase is made, vendor,
customer and admin must receive SMS and email notifications. 8) options
to handle inventory and delivery by vendor or Akuru; if Akuru manages
the inventory or delivery there is an extra charge."

Read with `docs/BOOKSHOP_PLAN.md` (the Bookstore), `docs/LIBRARY_PLAN.md`
(the Library), `docs/SIGN_IN_PLAN.md` (accounts and workspaces),
`docs/Payments-BML.md`, STATUS §5ly (guest checkout, the day before) and
CLAUDE.md's twelve rules. Sections: 1 the reference model, 2 what Akuru
does today (the audit, with the code), 3 the target, 4 the decisions,
5 the slices, 6 what the reference itself gets wrong, 7 what does not
change, 8 how to audit this document.

**For the session that builds this:** every slice below names its data,
Actions, routes, screens, tests, walk and docs. Follow CLAUDE.md: one
slice per PR; tests and the architecture suite green; walked in a
browser; STATUS updated; the merge gate is the `quality` check reporting
`success` on the PR head, read back. Baselines that will need an entry:
`tests/Architecture/Baselines/session_creations.php` (any new sign-in),
`public_routes.php` and `unguarded_write_routes.php` (any new public
route), `blade_screens.php` (any new public Blade partial — the public
site stays Blade; everything behind a sign-in is Inertia), and
`config/morph-map.php` for any new polymorphic column (ADR-005).

---

## 1. The reference: how Bake & Grill does it

Read from `backend/app/Http/Controllers/Api/Auth/CustomerAuthController.php`,
`backend/app/Http/Controllers/CustomerPortalController.php`,
`backend/app/Domains/Auth/Services/CustomerOtpService.php`,
`backend/routes/domains/*.php` and the listeners under
`backend/app/Domains/{Notifications,Sms}`.

**Customers.** A customer is a `customers` row keyed on the phone number
(`MaldivesPhone` normalises to `+9607XXXXXX`); the password is nullable.
The sign-in page asks for the number first (`POST /auth/customer/check-phone`
→ `{exists, has_password}`):

- has a password → password box (`passwordLogin`; 5 tries per number and
  address in 15 minutes, 20 per number an hour);
- no password → a six-digit code by SMS (`requestOtp`, purpose `register`;
  `verifyOtp` creates the customer if new), valid 10 minutes, 5 wrong
  tries per code;
- after a code sign-in the Blade site redirects to **complete your
  profile** (name, email, password) and sets `is_profile_complete`; from
  then on `check-phone` says `has_password` and the code path is refused
  for that number ("log in with your password, or use Forgot password");
- forgot password = a code with purpose `reset_password`, which can only
  reset, never sign in (purpose groups);
- **guest checkout** (`guestSession`): name + phone, no code, only ever a
  brand-new customer; an existing number is sent to sign in; ten tries per
  number and address an hour. Akuru copied this on 2026-09-30 (STATUS §5ly).

**Notices on an order.** `OrderCreated` → staff SMS/push through
`StaffNotificationDispatcher` (per-staff preferences, `new_order`,
`order_ready`, `order_out_for_delivery`…); for online orders only once
paid (`OrderStatusChanged` to `pending` after `payment_pending`). The
customer gets an SMS with a tracking link and an email receipt from
`PaymentConfirmationNotifier` on `OrderPaid`. Every SMS goes through one
`SmsService` with an idempotency key, a log row and a per-type budget
(`/sms/control-center`).

**Selling.** One shop, so no vendor approval; items are the owner's. What
transfers: delivery drivers with live location and a proof-of-delivery
photo (`DriverDeliveryController`, `DriverProofController`), customer
addresses, favourites, reorder, loyalty, referral codes, gift cards, SMS
campaigns and audiences with opt-out, complaints and a complaint box,
customer tags and follow-up notes, credit accounts and deposits for trade
customers, pre-orders, receipts and pay links by SMS, push notifications.
What does not: POS, kitchen display, tables, reservations, catering,
supplier purchasing, shifts.

**Identity documents.** Bake & Grill asks nobody for one.

---

## 2. What Akuru does today (audit, 2026-09-30)

| # | Finding | Where |
|---|---|---|
| F1 | **A shop applies with no identity document.** The form takes shop name, legal name, TIN, contact, island, what they sell, a link and the agreement. Approval creates the shop `active` and selling at once. | `ApplyToSellAction`, `DecideVendorApplicationAction`, migration `vendor_applications` |
| F2 | **A writer applies with one optional ID file** (front *or* back, image or PDF, private media). Approval makes the writer active at once. | `ApplyAsWriterAction::ID_DOCUMENT_MIMES`, `DecideWriterApplicationAction`, `l5_writer_application_extras` migration |
| F3 | **Shops and writers the office created have no ID on file** (Fitrah, decision 9 of BOOKSHOP_PLAN §13 said hers would come through the portal; the portal has no such screen). | `CreateVendorAction`, `SaveVendorBankDetailsAction` (bank only) |
| F4 | **A customer cannot sign in with a code.** OTP sign-in is admin-only since the 2026-09-14 account-takeover fixes (KNOWN_ISSUES "A phone number was enough…"). Customers sign in by email/phone/ID + password, or since §5ly as a guest with a name and a number — a number nobody proved, so not a sign-in (BACKLOG C12). | `OtpLoginController::requestOtp` line 62; `GuestCheckoutController`; `StartGuestAccountAction` |
| F5 | **"Set a password" exists but is never required.** `users.force_password_change` marks an account with no usable password; `Identity/SetPassword` is offered on the workspace home (`must_set_password` shared prop) and can be skipped forever. | `AccountController::setPasswordForm`, `HandleInertiaRequests` line 88 |
| F6 | **Course registration takes the ID *number*, never an image.** `id_type`, `national_id`, `passport` as text on `start` and `continueForm`; nothing uploaded; the enrolment page shows the number. | `CourseRegistrationController` lines 162–170, 392–400; `Admissions/Enrollment.jsx` |
| F7 | **The documents system already exists** and is unused here: `DocumentType::NationalId`, `::Passport`, `StorePrivateMediaAction`, `ReadPrivateMediaAction`, `StoreUploadedDocumentAction`. | `app/Domains/Media` |
| F8 | **A shop puts a product on sale by itself.** `ProductStatus` is draft / active / archived; `SaveVendorProductAction` writes whatever status the form sends. The office moderates storefronts, reviews and questions, not products. | `SaveVendorProductAction` line 134, `AdminBookshopController` |
| F9 | **On a paid order the office hears nothing** unless stock ran short. The customer gets in-app + email (office switch default on) and SMS only if the office turned it on (default off); the shop gets in-app + email (default on), SMS off. | `MarkCheckoutPaidAction::tell`, `config/bookshop.php` `notices.office_defaults` and `vendor_defaults` |
| F10 | **On a Library sale the writer and the office hear nothing**; the reader gets `purchase_ready`. | `GrantLibraryAccessOnPaymentConfirmed`, `config/library.php` `notices.events` (`new_sale` is listed but never sent) |
| F11 | **SMS is not live on production.** `SMS_LIVE=false` by default, `LogSmsSender` bound otherwise; OWNER_ACTIONS item 1's OTP box has never been ticked. Everything in §1's customer model and F9–F10 depends on it. | `NotificationsServiceProvider` line 38, `config/services.php` `sms.live`, `.env.example` line 79 |
| F12 | **Akuru never holds stock and never delivers.** `DeliveryKind::CollectAkuru` is a free counter pick-up (2 days). No fulfilment-by-Akuru flag, no Akuru stock location, no drivers, no fee. | `DeliveryKind`, `config/bookshop.php` `delivery_template`, `vendors` and `vendor_delivery_methods` migrations |
| F13 | **The customer features Bake & Grill has that Akuru lacks:** phone-first sign-in (F4), SMS tracking links and receipts, complaints the office sees, SMS campaigns to shop customers with opt-out, customer tags and follow-up notes, credit accounts, drivers with proof of delivery, push *sending* (device registration exists, Mobile A). *(Corrected 2026-10-01, P8b: push sending already existed — #551 fans every in-app notice out to registered phones; what was missing was a notice for the driver.)* Akuru already has the rest of §1's list. | routes in `routes/web_public.php`, `docs/BOOKSHOP_PLAN.md` §4–§5, §16 |

---

## 3. The target

**Customers** sign in the Bake & Grill way, on the phone number: a
password if the account has one, otherwise a code by SMS. A code sign-in
creates the account with a *verified* mobile contact (so the number is a
real sign-in, unlike §5ly's guest number) and goes straight to **Set your
password**, which cannot be skipped; from then on the number takes the
password only, and a code is only for *Forgot password*. Yesterday's
guest form stays as the fallback wherever SMS is off, and a guest account
whose number is later proved by a code is claimed by that code.

**Sellers** (shops and writers) upload the **front and back** of their ID
card at application; the office sees both in the queue and its approval
is the verification. A seller the office created, or one approved before
this, sees a *Verify your identity* card in the portal and cannot put a
product on sale, publish a work or request a payout until the office
ticks *Verified*. The images are kept for the life of the account
(decision D1).

**Learners** upload the front and back of the ID card (the child's own
for a child) during course registration; the office verifies on the
enrolment page afterwards; enrolment is never blocked, a certificate is
withheld until verified (D2, D3).

**Listings** go through the office: a shop *submits* a product, the
office approves or declines with a note; price and stock changes never
need a second look, changes to what the product *is* do (D4).

**Every purchase** tells the customer, the seller and the office by
in-app, email and SMS (D6); the office has a number and an address for it.

**Fulfilment and delivery** are each *shop* or *Akuru* per shop; Akuru
charges a handling fee per order it packs, deducted from the shop's
earnings, and its own delivery fee, paid by the customer and kept by
Akuru (D5). Akuru's deliveries go to drivers who mark them delivered
with a photo.

**The rest of Bake & Grill's customer and selling features** that fit a
bookshop and a library follow, one slice each (P8).

---

## 4. Decisions

| # | Question | Decision (owner, 2026-09-30) |
|---|---|---|
| D1 | How long are ID images kept? | **For the life of the account** ("Life time"). Private media; the office alone opens them; deleted only with the account (`DeleteUserAccountAction`). |
| D2 | Does a course ID block enrolment? | **No.** Collected at registration, verified by the office on the enrolment page afterwards; **certificates are withheld until verified**. |
| D3 | Whose ID for a child? | **The child's own ID card.** |
| D4 | Which edits to a live product go back for approval? | As recommended: **title, description, images, category and variants' names do; price, sale, stock, SKU and delivery do not.** A shop the office marks *trusted* skips the queue. Products live before this slice stay live. |
| D5 | Akuru's fees | As recommended: a **handling fee per order** Akuru packs (office setting, default MVR 15, overridable per shop), **deducted from the shop's earnings**; **Akuru's delivery fee** at checkout is paid by the customer and is Akuru's, never commissioned; storage free. |
| D6 | Who is told of a purchase, and how | As recommended: customer, seller and office, each in-app + email + SMS; the office's number and address are settings. |
| D7 | Prerequisite | SMS goes live on production (OWNER_ACTIONS item 24, written by P1). Until then codes are logged, not sent, and the §5ly guest form remains the customer's way in. |

---

## 5. The slices

Order: P1 → P2 → P3 → P4 → P5 → P6 → P7 → P8. P1 unblocks nothing in
code but is the gate for the owner's SMS switch; P2 carries the identity
table the later ones read.

### P1 — Customers sign in on the phone number — **shipped 2026-09-30, STATUS §5ma**

The Bake & Grill fork, in Identity. Nothing here weakens the 2026-09-14
fixes: the admin OTP route stays admin-only and untouched.

- **Routes** (`routes/auth.php`, guest middleware, each throttled with
  its own prefix): `GET sign-in` (the customer page; the existing
  `login` stays for staff and keeps working for everyone),
  `POST sign-in/check` (number → `{has_password}`; answers only for a
  number with a *verified* mobile contact, otherwise `has_password:false`,
  so the page shows the code box — never "no account", which is the
  enumeration B&G's `check-phone` leaks), `POST sign-in/password`,
  `POST sign-in/code` (send), `POST sign-in/code/verify`,
  `POST sign-in/forgot` and `POST sign-in/reset`.
- **Actions** in `app/Domains/Identity/Actions/`:
  `StartPhoneSignInAction` (normalise via `ContactNormalizer`, find the
  verified contact, or create a user + *unverified* mobile contact and
  send `OtpService::send($contact, 'login')`), `VerifyPhoneSignInAction`
  (`OtpService::verify`, mark the contact verified, `Auth::login`,
  regenerate; if the number belongs to a §5ly guest account — `users.phone`
  equal and no contact — **claim it**: attach the verified contact to that
  user rather than making a second account, which closes BACKLOG C12),
  `ResetPasswordByPhoneAction` (purpose `password_reset`, sets the
  password, clears `force_password_change`). Rate limits as B&G's:
  5 password tries per number and address in 15 minutes; codes through
  `OtpService`'s own caps plus `throttle:auth-otp-request` per address.
- **Set your password, required.** A new middleware
  `RequireCustomerPassword` on the public checkout, Library purchase,
  gift-card purchase and `/my-*` routes: a user with `force_password_change`
  and no staff/vendor/writer role is redirected to `account.set-password`
  with `intended`. Staff keep the old prompt. After it is set, the number
  answers `has_password:true` and the code path refuses "This number has
  a password — sign in with it, or use Forgot password".
- **Guest form (§5ly)** gains a line "Have a number? Sign in with a code"
  linking to `sign-in`; when `config('services.sms.live')` is false the
  sign-in page says codes are not yet on and shows the guest form instead.
- **Baselines**: `session_creations.php` (proof: `OtpService::verify` for
  this contact; `Hash::check` for the password path), `public_routes.php`,
  `unguarded_write_routes.php`.
- **Screens**: one public Blade page `auth/customer-sign-in.blade.php`
  (add to `blade_screens.php` with the reason: pre-auth, public site) —
  number, then password or code, EN/DV/AR, RTL-safe.
- **Tests**: `tests/Feature/Identity/PhoneSignInTest.php` — new number →
  code → account with verified contact → forced to set a password →
  password-only afterwards; existing password account refuses a code;
  a guest account is claimed; wrong code caps; reset flow; enumeration
  (unknown number and known-without-password answer alike).
- **Walk**: `scripts/smoke/customer-sign-in.mjs` reading the code from
  `storage/logs/laravel.log` (LogSmsSender), and `checkout.mjs` step:
  the cart's sign-in link reaches the page.
- **Docs**: OWNER_ACTIONS item 24 "Turn SMS on" (`SMS_LIVE=true`,
  `SMS_USE_DHIRAAGU`, the Dhiraagu credentials from `akurusms`, one real
  code to the owner's phone, and the per-message cost); STATUS;
  BACKLOG C12 struck.

### P2 — Seller identity: front and back, verified by the office — **shipped 2026-09-30, STATUS §5mb**

- **Data** (Identity owns it; one table for every purpose, rule 11):
  `identity_verifications` — `user_id`, `purpose` (`vendor` | `writer` |
  `learner`), `subject_type`/`subject_id` (morph alias registered:
  a `student` for a child's learner check, else null), `front_media_file_id`,
  `back_media_file_id` (private media, `StorePrivateMediaAction`, images or
  PDF, 8 MB each), `status` (`pending` | `verified` | `rejected`),
  `decided_by`, `decided_at`, `note`, timestamps; unique on
  (`user_id`, `purpose`, `subject_type`, `subject_id`). Contract
  `Identity/Contracts/IdentityVerificationInterface` with
  `statusFor(userId, purpose, subject?)` and `submit(...)`, bound in the
  provider, so Bookshop, Library and Admissions never import the model.
- **Applications**: `ApplyToSellAction` and `ApplyAsWriterAction` require
  both files (validation `required|file|mimes:jpeg,jpg,png,webp,pdf|max:8192`
  on `id_front` and `id_back`); the writer's single `id_document` column
  is read for old rows and no longer written. Approval
  (`DecideVendorApplicationAction`, `DecideWriterApplicationAction`)
  marks the verification `verified` in the same transaction; decline
  marks it `rejected` with the note.
- **Existing sellers**: `/vendor` and `/write` show a *Verify your
  identity* card (upload front and back) while no verified row exists;
  `SaveVendorProductAction` refuses `status=active`/submission, `PublishLibraryItemAction`
  and `SubmitLibraryItemForReviewAction` refuse, `RequestVendorPayoutAction`
  and `RequestWriterPayoutAction` refuse, each with a message naming the
  card. `VendorScope` gains `identityVerified`; the writer dashboard the same.
- **Office**: `/admin/bookshop` and `/admin/library` queues show both
  images (served by `ReadPrivateMediaAction` behind `bookshop.manage` /
  the library's gate) and a *Verified* / *Reject with note* pair; a
  *Verified* column on the vendors and writers lists; CSV column.
- **Tests**: `IdentityVerificationTest` (both files required; office-only
  reads; approval verifies; an unverified Fitrah cannot activate a product
  or request a payout; verifying unlocks), plus updates to
  `VendorApplicationTest` and `WriterApplicationTest`.
- **Walk**: `vendor.mjs` and `writer.mjs` gain the upload and the office
  tick; `SmokeMarkerSeeder` plants a verified row for the walk's sellers.
- **Docs**: BOOKSHOP_PLAN §3 and LIBRARY_PLAN §11.1 amended; STATUS;
  `docs/vendors/FITRAH.md` note that her portal now asks for the card.

### P3 — Learners: the ID card at registration, verified afterwards — **shipped 2026-09-30, STATUS §5mc**

- **Funnel**: `CourseRegistrationController::continueForm` / `enroll` (the
  profile step, adult and parent flows) take `id_front` and `id_back` per
  learner — for a parent enrolling a child, per child (D3) — stored through
  the P2 contract with purpose `learner` and the `student` subject. The
  funnel is Blade; the fields join the existing form (no new screen).
  Enrolment proceeds regardless (D2).
- **Office**: `Admissions/Enrollment.jsx` shows the two images (private
  read behind the enrolment gate) with *Verified* / *Reject with note*;
  the enrolments list gets an *ID* column and filter; CSV column.
- **Certificates**: `ResolveCourseCertificateStatusAction` reports
  `withheld_unverified_id` and `IssueCertificateAction` refuses until the
  learner's verification is `verified`; the learner's *My learning* says
  "your certificate is ready once the office has checked your ID".
- **Tests**: `LearnerIdentityTest` — upload at registration, child's own
  card, office verify, certificate withheld then issued, a rejected card
  asks the learner to upload again (a new pending row).
- **Walk**: `registration.mjs` (or the funnel walk that exists) gains the
  upload; `exams.mjs`/`certificates` step checks the withhold.
- **Docs**: SPEC §32/§44 note; STATUS.

### P4 — Listings approved by the office — **shipped 2026-09-30, STATUS §5md**

- **Data**: `ProductStatus::PendingReview = 'pending_review'`;
  `products.submitted_at`, `review_note`, `reviewed_by`, `reviewed_at`;
  `vendors.trusted` (boolean, office-set).
- **Shop**: the product form's *Put on sale* becomes *Submit for
  approval* (→ `pending_review`) unless the shop is trusted; an active
  product edited in a D4 field returns to `pending_review` and leaves the
  store until approved (the listing query already reads `status`); price,
  sale, stock, SKU and delivery edits keep it live. The products list
  shows the state and the office's note.
- **Office**: a *Listings awaiting approval* queue on `/admin/bookshop`
  (product, shop, submitted, diff of the D4 fields for a re-review),
  *Approve* / *Decline with note*; `NotifyBookshopUserAction` events
  `listing_submitted` (office), `listing_decided` (shop). CSV.
- **Tests**: `ListingApprovalTest` — submit → invisible → approve → on
  sale; decline → note shown; a price edit stays live, a title edit does
  not; trusted shop skips; existing active rows untouched by the migration.
- **Walk**: `vendor.mjs` submits and the office approves before the
  product is bought in `checkout.mjs` (the seeder marks Fitrah's walk
  products approved).
- **Docs**: BOOKSHOP_PLAN §5 and §7; STATUS.

### P5 — Every purchase tells everyone — **shipped 2026-09-30, STATUS §5me**

- **Settings**: `bookshop_office_phone`, `bookshop_office_email`,
  `library_office_phone`, `library_office_email` on the two office
  settings screens (`/admin/bookshop` settings, `/admin/library/settings`).
- **Bookstore**: `NotifyBookshopUserAction::office()` gains an `$event`
  and, for `order_paid`, `slip_received`, `order_cancelled`,
  `return_requested`, emails the office address and texts the office
  number, gated by the office switches; the defaults flip to
  `customer_sms: true`, `vendor_sms: true`, `new_order` SMS true.
  `MarkCheckoutPaidAction::tell` calls it. The customer's `order_paid`
  SMS carries the tracking link (`public.shop.track` with the number).
- **Library**: `GrantLibraryAccessOnPaymentConfirmed` sends `new_sale` to
  the writer and to the office (`NotifyLibraryUserAction::office()` with
  channels); `config/library.php` `notices.email`/`sms` default true.
- **Every SMS** goes through the existing `SmsSenderInterface` with the
  `RecordSmsReceiptAction` log, as today. Queued; needs the worker
  (OWNER_ACTIONS item 3 — the PR's deploy note says so).
- **Tests**: `PurchaseNoticesTest` — a paid order produces three in-app
  notices, three mails and three SMS (Mail and the SMS log faked); a
  Library sale the same; switches off silence a channel; no office number
  → no office SMS, no error.
- **Walk**: `checkout.mjs` reads the log for the three SMS lines.
- **Docs**: BOOKSHOP_PLAN §4/§7, LIBRARY_PLAN §41, OWNER_ACTIONS item 23
  updated; STATUS.

### P6 — Fulfilment and delivery by Akuru, with a charge (one PR, or P6a data+money and P6b screens) — **shipped 2026-10-01: P6a STATUS §5mf (ADR-042), P6b drivers STATUS §5mg**

- **Data**: `vendors.fulfilment` (`vendor` | `akuru`), `vendors.delivery_by`
  (`vendor` | `akuru`), `vendors.akuru_handling_fee` (nullable override);
  settings `bookshop_akuru_handling_fee` (default 15.00),
  `bookshop_akuru_delivery_fee_male` (default 30.00),
  `bookshop_akuru_delivery_free_over`; `orders.fulfilled_by`,
  `orders.akuru_handling_fee`, `orders.delivery_revenue_to` (`vendor` |
  `akuru`); `stock_movements.kind` gains `received_at_akuru` and
  `returned_to_vendor`; `products.stock_at_akuru` (integer, the part of
  `stock` Akuru holds) — additive, rule 9.
- **Delivery**: `DeliveryKind::AkuruCourier = 'akuru_courier'`;
  `ResolveDeliveryOptionsAction` offers it for a shop with
  `delivery_by = akuru` in place of the shop's own courier rows, fee from
  the setting; `StartBookshopCheckoutAction` records
  `delivery_revenue_to = akuru`; `RecordVendorEarningAction` excludes that
  fee from the shop's earning and deducts `akuru_handling_fee` when
  `fulfilled_by = akuru`; `IssueCommissionInvoicesAction` adds a
  *Handling by Akuru* line. The money page and the vendor's order page
  show both.
- **Stock at Akuru**: the office records a hand-over (product, quantity)
  → `received_at_akuru`, raising `stock_at_akuru`; a sale of an
  Akuru-fulfilled order draws from it; the shop's stock screen shows
  "at Akuru: N" read-only for those products.
- **Orders**: an Akuru-fulfilled order lands in the **office's order
  queue** on `/admin/bookshop` (the same states as the vendor's:
  processing → ready → dispatched → delivered), not the shop's; the shop
  sees it read-only. `FulfilVendorOrderAction` gains an office scope.
- **Drivers**: `delivery_drivers` (user_id, name, phone, active) and
  `order_deliveries` (order_id, driver_id, assigned_at, picked_up_at,
  delivered_at, proof_media_file_id, note); a `/deliveries` Inertia page
  for a signed-in driver (role `driver`, by migration as ADR-040's roles
  are): today's list, *Picked up*, *Delivered* with a photo from the
  phone camera (`StorePrivateMediaAction`); the customer's tracking page
  shows *Out for delivery* and *Delivered* with the time; the
  `order_progress` notice fires on each.
- **Tests**: `AkuruFulfilmentTest` (fee maths, earnings, invoice line,
  stock at Akuru, office queue, shop read-only), `AkuruDeliveryTest`
  (driver assignment, proof, tracking, notices).
- **Walk**: `fulfilment.mjs` gains the office packing an Akuru-fulfilled
  order and a driver delivering it with a photo; `checkout.mjs` shows
  the Akuru courier fee.
- **Docs**: BOOKSHOP_PLAN §4, §5, §8 and decisions 20–21; STATUS; ADR for
  the fee model (money rules, rule 12: fees are ledger lines, never edits).

### P7 — Feedback the office sees, and SMS to customers (one PR each) — **shipped 2026-10-01: P7a §5mh, P7b §5mi, P7c §5mj**

- **P7a Complaints** — **shipped 2026-10-01, STATUS §5mh**: `order_complaints` (order_id, user_id, kind, text,
  photo, status open → in progress → resolved, resolution note); *Report
  a problem* on the order page; office queue on `/admin/bookshop` with a
  reply that reaches the customer (in-app, email, SMS); the shop sees its
  own; CSV. Tests, `fulfilment.mjs` step.
- **P7b SMS campaigns for the shop** — **shipped 2026-10-01, STATUS §5mi** (office-sent at a shop's request; opt-in at checkout, since People consents cover only students and guardians): reuse the prayer-times broadcast
  screens' pattern (`Domains/PrayerTimes` recipient groups and broadcasts)
  as a Bookstore *Campaigns* screen: audience = customers who bought from
  a shop / everyone who opted in; per-message cost shown; opt-out link
  and `STOP` reply honoured through the existing opt-out routes; a
  monthly budget setting. Tests, walk.
- **P7c Customer notes and tags** — **shipped 2026-10-01, STATUS §5mj** (`/admin/bookshop/customers`) on the office's customer view (who
  bought what, last order, tags, follow-up notes) — B&G's
  `AdminCustomerController`. Tests, walk.

### P8 — Later, on request

Credit accounts for schools (B&G's `CustomerCreditLedger`), deposits,
pre-orders, push sending (Mobile A's registration exists), receipts by
SMS link. Each is its own slice when the owner asks; recorded in
BACKLOG C13.

The owner asked for it on 2026-10-01. Four slices: **P8a receipts by SMS
link — shipped 2026-10-01, STATUS §5mk**; **P8b push — shipped 2026-10-01, STATUS §5ml** (sending already existed since #551; the slice tells the driver); **P8c credit accounts for schools, with deposits — shipped 2026-10-01, STATUS §5mm**; P8d pre-orders with a deposit.

---

## 6. What the reference itself gets wrong (for the Bake & Grill repository)

Found while reading; not fixed here (that repository is read-only in this
session). Worth a session of its own there:

1. **BML webhooks are verified against a self-generated secret** — the
   same mistake Akuru fixed on 2026-09-30 (STATUS §5lw): BML signs with
   `sha256(nonce . timestamp . apiKey)` and issues no webhook secret, so
   every real webhook is refused with 503 and BML retries forever.
   `BmlWebhookController`, the payments BML service.
2. **`check-phone` tells anyone whether a number has an account and a
   password** (`CustomerAuthController::checkPhone`). Answer `has_password`
   only, and only for a verified number; treat unknown as "no password".
3. **"Set up your password" is a redirect on the Blade site only**
   (`CustomerPortalController::verifyOtp` line 250). The `/order` React
   app and the API never require it, so a customer can order forever on
   codes alone. Gate the order-creating routes on `is_profile_complete`.
4. **Passwords need six characters** (`min:6` in `completeProfile` and
   `resetPassword`); Akuru uses eight.
5. **Twenty codes per number per five minutes** (`otp-request:login`):
   an attacker can bill the restaurant ~240 SMS an hour to any one number.
   Five per half hour, as the reset path already does.
6. `SendOrderConfirmationListener` skips every order type and is dead
   code; harmless.

---

## 7. What does not change

- Money rules (CLAUDE.md rule 12): paid access still depends on the BML
  webhook; fees and Akuru's revenue are append-only ledger lines.
- The admin OTP route and the 2026-09-14 fixes; `SessionsAreEarnedTest`
  keeps every new sign-in honest.
- The §5ly guest checkout stays as the fallback while SMS is off, and as
  the way in for someone whose phone cannot receive a code.
- The public site stays Blade; everything behind a sign-in is Inertia.
- Existing shops, writers, products and enrolments keep working; the new
  gates apply from the slice that ships them, with the migration leaving
  live rows live.

## 8. How to audit this document

For each finding in §2 open the file named and confirm the behaviour;
for each slice, after it ships, replace its heading's "(one PR)" with
"— **shipped <date>, STATUS §…**" as `docs/SIGN_IN_PLAN.md` does, and
strike the findings it closes. A slice whose walk did not run is not
shipped.
