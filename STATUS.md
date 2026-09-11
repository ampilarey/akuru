# Status

**Verified against:** `main` after PRs **#86–#126** (`5ecc37e` W2.5 research posts).  
**This PR:** `cursor/w3-prayer-times-063c` / **#128** — **W3 prayer times** (CODE + TESTED + USABLE).  
**USABLE column** cites `docs/PILOT_REHEARSAL.md` (Rounds 1–3) and staging notes in the [archive](docs/STATUS_ARCHIVE.md). It is **not** inferred from code or tests.  
**History:** per-slice append log (including Round-2 fixes 1–7) → [`docs/STATUS_ARCHIVE.md`](docs/STATUS_ARCHIVE.md).  
**Defects:** [`docs/KNOWN_ISSUES.md`](docs/KNOWN_ISSUES.md).

## 1. Honest summary

Akuru on `main` has a large Laravel 12 + Inertia/React codebase: People unification (Deploy 1–2, Deploy 3 not run), academic years/classes/registers/attendance, exams/marks, HTML report cards, fees/invoices, HR/payroll (payroll **off**), a course engine with offerings and four activity patterns, and Arabic/Qur’an A-track catalog pieces. CI (pint + Pest + architecture) is the merge gate.

What **runs for a person** is still narrower than the code. Staging `test.akuru.edu.mv` public pages return 200; **seed logins do not authenticate** (Round 1 step 0, Round 2, Round 3). This agent cannot SSH or seed staging.

Local `migrate:fresh --seed` now includes `PilotRehearsalSeeder` (#87). Round 3 Chrome (stacked #86–#92, now on `main`) walked: teacher **Today** landing, fill grid **number + DOB**, **Parent Dashboard** + **Parent notified** column, absence-note approve, missing-weights **banner** + honest **HTML** (not PDF) labels, invoice **sent** rows on the Pilot year. SMS binds `LogSmsSender` unless `APP_ENV=production` **and** `SMS_LIVE` is an explicit true (#86).

Still blocking a real teacher: staging access, AppShell nav IA (**proposed**, `docs/APPSHELL_NAV_IA.md`, awaiting owner decision — wrap still live), parent notified still **—** on excused rows. People → Students can create a child (#95). Weights can persist a year scheme (#96). Documents are HTML by decision (ADR-012 / #97). Roster picker flags PIL-01 vs blank as one identity (#99). `/academics/gradebook` redirects to `/exams/gradebook` (#100). Class teacher can be set on an existing class; year seeders `firstOrCreate` by name. Course certificates (C1 #106), completion/performance reports (C2 #107), teacher review reports (C3 #108), composed portal home (D1 #109), parent-teacher meeting slots (D2 #110), staff overview (D3 #111), W1.1–W1.6 (#112–#117), W2.1–W2.5 (#118, #119, #121, #124, #126) are on `main`. W3 prayer times is this PR. Most catalog/HR/course-engine slices remain **UNVERIFIED**.

Hifz untouched until Phase F. Deploy 3 not executed. Track B leftovers B1–B4 are on `main` (#102–#105).

## 2. Phase / slice table

Legend — **CODE:** implementation in repo (models/migrations/actions/routes/pages). **TESTED:** Pest coverage of the slice’s behaviour, not merely that a class constructs. **USABLE:** a person can complete the task in a browser (or staging), with a citation; otherwise UNVERIFIED.

| Slice | CODE | TESTED | USABLE | Notes / known holes |
|---|---|---|---|---|
| Phase 0 foundation | Yes. Domain skeleton, contracts, CI. | Architecture suite + route-name tests. | Staging public `/up` `/en` 200 (2026-06-13). Auth/BML/portal/Hifz **not** credential-smoked then; 2026-08-23 seed login **failed**. | Staging HEAD in archive is far behind current `main`. |
| Morph-map hotfix | Yes. `config/morph-map.php`, backfill, `morph-map:verify`. | `MorphMapBackfillTest`. | Staging verify **OK** (2026-08-16, `05b8cca`). Later seed login still failed. | Mixed-era staging was the real test of collapse. |
| S1.1a schema | Yes. Additive student/guardian/document columns. | `UnifiedStudentSchemaTest`. | UNVERIFIED as a user task (schema). | Deploy 3 cleanup not run. |
| S1.1b backfill | Yes. `UnifyStudentsAction`, `students:verify-unification`. | `UnifiedStudentBackfillTest`, representative seeder test. | Staging verify **red** (collisions + orphan guardians, archive 2026-08-25). Representative gate **green** (ADR-021). | `--backfill` refused on `APP_ENV=production`. |
| S1.1c read switch | Yes. Dual-write still on. | `UnifiedStudentReadSwitchTest`. | UNVERIFIED in a browser. Staging enrollments with null `student()` noted in archive. | Posted enrollment id still legacy RS. |
| S1.2 custom fields | Yes. Admin CRUD + student profile fields. Directory create/edit added. | `CustomFieldsTest`, `StudentDirectoryCrudTest`. | Walked **create** (#95): Add student → show → class picker. | Course-only nullables supported. Status only via `ChangeStudentStatusAction`. |
| S1.3 consent | Yes. Ledger + profile tab. | `ConsentTest`. | UNVERIFIED. | |
| S1.4 staff profiles | Yes. Inertia `people.staff.*`. | `StaffProfileTest`. | UNVERIFIED. | `teachers` row ≠ Spatie role `teacher` (mitigated for seed: `EnsureTeacherRowAction` in `UserSeeder`, #87). |
| S1.5 years/terms/classes | Yes. Years/classes/roster/promotion. | `AcademicYearBackboneTest`, `YearClassUniquenessTest`. | Walked **partial** (R1 S1, R2 S1, R3 S1). Create unique year/class **validated** (#91); first R3 pass hid errors, follow-up paints `errors.name`. Year seeders `firstOrCreate` by name. Class teacher can be assigned on an existing class (show page). Picker identity_key **omits class** (#90) **and student number** (blank / PIL-01 vs PIL-99 still flag). | `ActivateAcademicYearAction` will not close the current year for you. |
| S2.0 unify-verify gate | Yes. `scripts/pull-deploy-test.sh`. | `PullDeployTestScriptTest`. | Staging evidence **not pasted**. First #15 deploy used pre-pull script (archive). | Operator-only to confirm a gated deploy log. |
| S2.1 rooms | Yes. CRUD + CSV. | `RoomCrudTest`. | UNVERIFIED. | |
| S2.2 timetable conflicts | Yes. Additive year/room/validity + checker. | `TimetableConflictSaveTest`. | UNVERIFIED as a lone task. | |
| S2.3 timetable builder | Yes. Week grid. | `TimetableBuilderTest`. | Walked **partial** (R2 S1): seeder grid shown; extra-period drag **did not persist**. | |
| S2.4 room bookings | Yes. | `RoomBookingTest`. | UNVERIFIED. | |
| S2.5 calendar days | Yes. | `CalendarDayTest`. | UNVERIFIED. | |
| S2 event/elective registration | Yes. Min/max seats, waitlist, parent confirm, second round. Reuses 1B.2 `EnforceSeatLimitAction` (no second limiter). | `EventRegistrationTest` (lock reject, waitlist, parent confirm, second-round promote, portal 403). | Walked **#103**: admin create 1-seat elective → parent register/confirm → second child waitlisted → second round promotes. | Portal `/portal/events`; admin `/academics/events`. Occupying statuses: pending, confirmed, pending_parent. |
| 2 leftover — class quiz/assignment → engine | Yes. `assessments.classroom_id` XOR `course_id`; additive `legacy_*` ids; `assessments:verify-legacy-migration`. | `LegacyAssessmentMigrationTest` (XOR attach, remaining counts, roster 403, class CSV). | Walked **#104**: Grade 5 A class show lists migrated quiz + assignment; roster student opens player. | Legacy tables kept (rule 9 / ROADMAP §3.5). Engine stays subject-ignorant. |
| S2.6 class register | Yes. Today, generate, fill, submit. | `ClassRegisterTest`. | Walked **ok** for fill+submit (R2 S2, R3 S2). Teacher login **lands on Today** (#88). Fill grid **Number + DOB** (#90). Generate flash names already-existing registers (#91). Cold `DatabaseSeeder` now includes `PilotRehearsalSeeder` (#87). | Unfilled still hides today’s remaining periods until they are late. |
| S2.7 class attendance | Yes. Writer + daily grid. | `ClassAttendanceTest`. | Walked **partial** with S2.6 (R2 S2). School in **per-lesson** mode; daily store rejects. | `excused` still on the teacher grid. |
| S2.8 absence notes | Yes. Portal submit + teacher approve → excused. | `AbsenceNoteTest`. | Walked **ok** (R2 S4, R3 S4). Date not defaulted. Attachment/period not in the form. | |
| S2.9 behavior | Yes. | `BehaviorRecordTest`. | UNVERIFIED. | |
| S2.10 requests / leave | Yes. | `SchoolRequestTest`. | UNVERIFIED. | |
| S3.1 grading foundations | Yes. Scales, types, weights UI. | `GradingFoundationsTest` including HTTP store. | Weights form now saves a year scheme (#96): numeric defaults summing to 100. | Previously walked **fail** (R2/R3 JSON zeros). |
| S3.2 exams | Yes. Status machine, schedule. | `ExamSchedulingTest`. | Walked **ok** (R2 S5) schedule → published. Easy to schedule the wrong class (form defaults). | |
| S3.3 marks | Yes. Grid + CSV. | `ExamMarksTest`. | Walked **ok** (R2 S5) 15/15. PIL numbers **on this grid**. | |
| S3.4 term grades | Yes. `ComputeTermGradesAction`, gradebook. | `TermGradesTest` happy path **and** missing-weights (#89); `WeightSchemePersistTest`. | Walked **explained fail** until weights persist (#96): scheme from Weights then Recompute fills Term % / Grade / Rank. `/academics/gradebook` redirects to `/exams/gradebook` (#100). | |
| 2 leftover — unified gradebook | Yes. `GradeItemContract` + exam/assessment providers; `grade_items` on `/exams/gradebook`. | `UnifiedGradebookTest`; `GradeItemContractTest`. | Walked **#105**: Grade 5 A gradebook shows exam marks and engine quiz/assignment scores. | Engine stays subject-ignorant. Term % still exams-only. |
| 3 C1 course certificates | Yes. `certificate_templates` + `issued_certificates`; admin builder; issue; public QR verify. | `CourseCertificateTest`. | Walked **#106**: template → issued AKU-2026-C9CAKP to Fatima Yoosuf → guest `/verify/certificates/{ulid}` face only; CSV. | Unlocalized verify URL. Morph aliases. HTML, not PDF. |
| 3 C2 completion + performance reports | Yes. Staff `/catalog/reports/completions`; portal `/portal/performance`. | `CourseCompletionReportTest`. | Walked **#107**: admin roster 12 rows (Unification representative course); parent Hassan sees Fatima Yoosuf performance card; CSV. | Course-only enrollments included; offering summaries empty when `course_offering_id` is null. |
| 3 C3 teacher review reports | Yes. `/catalog/reviews` pending + weakness + revision; CSV. | `TeacherReviewReportTest`; existing `TeacherReviewTest`. | Walked **#108**: pending Mariyam Ali “Write a sentence” scored 8/10; weakness/revision for “Choose meaning” 0/10 + retry. Live walk found `passing_score` 50 on a 2-point quiz (legacy percent) falsely marking 2/2 as weak — treated as percent when passing > max. | No `course_type` branch. Threshold default 50% when no passing score. |
| D1 composed parent/student home | Yes. `/portal/home` + CSV; parent/student `/dashboard` redirect. Reads Academics/ExamsGrades/Finance/Courses Actions and `StudentHifzSummaryReader`. | `PortalHomeTest`; `RoleLandingTest`. | Walked **#109**: parent Hassan `/en/dashboard` → `/en/portal/home` for Fatima Yoosuf (attendance 0% / 2 absent / 1 excused, Term 1 Arabic Final 70/100, three sent invoices, empty course + Hifz on seed). Student Mariyam Ali same page with course row. CSV `portal-home.csv`. | Portal new files import no other-domain Models and no `App\Domains\Hifz\`. AppShell Home link. Seed has no Hifz rows; Pest composes Hifz via a program created in the test. |
| D2 parent-teacher meeting slots | Yes. `meeting_slots` + `meeting_bookings` (year-scoped); admin generate/publish `/academics/meetings`; portal `/portal/meetings` book/cancel + CSV. | `MeetingSlotTest`. | Walked **#110**: admin published Term 1 PTM 2026-09-03 18:00/18:30 for Grade 5 B (Fatima’s roster class) / Fatimat Ali. Parent Hassan booked 18:00 for Fatima Yoosuf; portal CSV; admin CSV shows 1/1 Fatima. | Morph aliases `meeting_slot` / `meeting_booking`. Permission `meetings.manage`. Portal files import no Models / no Hifz. AppShell Meetings (wrap 82). |
| D3 staff overview | Yes. `/portal/overview` + CSV. Reads Academics `ListUnfilledRegistersAction` (unfilled, fill rates, plan adherence) and ExamsGrades `ListExamsAction::ungraded`. Admin/headmaster `/dashboard` redirect. | `StaffOverviewTest`; `RoleLandingTest`. | Walked **#111**: admin `/en/dashboard` → `/en/portal/overview` (2026-2027 Pilot). Unfilled Grade 5 A Quran Recitation Period 2 expected 2026-08-26; ungraded **D3 Ungraded Walk** marks_entry; fill 66.7% (Ustadha Aishath Shifa 0/1); plan Grade 5 Arabic Term 1 1/1 100%. CSV `staff-overview.csv`. Registers + Exams Open links. Parent `/portal/home`; parent `/portal/overview` **403**. | No new tables. Portal new files import Actions only, no Models / no Hifz. AppShell Overview (wrap 83). Super_admin landing stays Blade. Walk exam `D3 Ungraded Walk` was created locally (seed had none). |
| W1.1 conversion urgency | Yes. Public course cards/detail: seats thresholds, deadline badge, early-bird from `courses.meta`, waitlist → `contact_inquiries`. Expired open courses hidden from Open Courses. | `CourseConversionTest`; `PublicRouteNamesTest`. | Walked **#112**: homepage Open Courses shows Arabic Beginners **Limited seats** + 5 days left + struck 300 / early-bird 180; Advanced Arabic Grammar **7 seats left**; Cursor Test Course (null seats) has **no** seats badge; **W1 Expired Open Course** absent from listing, 200 on direct URL. Tajweed for Beginners full waitlist form; submitted Walk Waitlist → inquiry id 1 `waiting_list` course_id 7. | Occupying = pending+active. Enroll CTA still prints `registration_fee_amount` (300) beside early-bird display of `fee`. Homepage Open Courses is `take(6)` so a later full course may only appear on `/courses`. |
| W1.2 trust above the fold | Yes. Settings group `trust_settings`; homepage hero accreditation / years / students / partner logos. Years from founded year or override; students manual or unified `students` count. Logos via Media public files. | `HomepageTrustTest`; `PublicMediaTest`. | Walked **#113**: `/en` hero shows **Registered with the Ministry of Education… Reg. MOE/EDU/2019/042**, **6** Years operating (founded 2020), **31** Students taught (computed from unified students), MOE partner logo. `/dv` shows the Dhivehi accreditation line + same 6 / 31. Homepage HTML has **no** `5+`, **no** “Years of service”, **no** “Students enrolled”. | Empty settings omit the signal. No admin form yet. About page still hardcodes Est. 2020 and `years=5`. Walk accreditation + logo are **dev-DB only**, not in seeders. |
| W1.3 outcome-led course pages | Yes. `courses.learning_outcomes` JSON; admin one-line-per-locale fields; `testimonials.course_id`; public show outcomes above description, course testimonials with general fallback, prominent instructor qualifications. | `CourseOutcomesTest`. | Walked **#114**: Arabic Language for Beginners shows **What you'll be able to do** (alphabet / makhraj / haraka) above description, **Qualifications** Ijazah in Quran for Ustadha Aishath Shifa, course testimonial “letters finally click”. Cursor Test Course has **no** outcomes section and shows the **general** quote. Homepage does **not** show the course-specific quote. | Catalog Inertia create does not edit outcomes. No testimonial CMS (walk attach via tinker). Walk outcomes/instructor/quotes are **dev-DB only**. |
| W1.4 mobile CTA + leads | Yes. Sticky price + Register + WhatsApp; `leads` table; syllabus magnet; admin listing + CSV. | `CourseLeadCaptureTest`. | Walked **#115**: Arabic Beginners mobile bar shows **180.00 MVR** + WhatsApp icon + **Register · Limited seats** above the site tab bar. Sidebar **Ask on WhatsApp** (`wa.me/9607972434?text=Arabic Language for Beginners`) and **Get full syllabus**. Submitted Walk Parent / 7778888; admin `/admin/public-site/leads` lists Hassan W14 + Walk Parent (`syllabus`); CSV `leads.csv`. | Per-course WhatsApp, else `conversion.whatsapp_number`, else contact `viber`. Syllabus only when public `media_files` id is set. Waiting list dual-writes leads. Walk WhatsApp/syllabus file are **dev-DB only**. |
| W1.5 SEO + sharing | Yes. schema.org `Course` + `CourseInstance` + sitewide `Organization` + `FAQPage`; OG/Twitter title/cover/price; `hreflang` en/dv/ar/x-default; XML sitemap with xhtml alternates for courses/articles/news/events. | `CourseSeoTest`. | Walked **#116**: Arabic Beginners view-source has `Organization` (Akuru Institute), `Course` + `CourseInstance` (start **2026-09-01**, end **2026-12-15**, price **180.00 MVR**), `FAQPage` with the six accordion questions. OG title/cover/`product:price:amount` 180.00 MVR. `hreflang` en/ar/dv/x-default. `/sitemap.xml` (302 → `/en/sitemap.xml`) includes `/en/courses/arabic-language-beginners`, `/en/articles/w15-walk-article`, `/en/events/2` with xhtml alternates. Page still shows the FAQ accordion. | No new tables. Website sitemap uses Courses `ListPublicCourseSitemapEntriesAction` (baseline shrunk). Price tags omitted when `fee` is null. News loc is slug. Organization name is `institute_name` or **Akuru Institute** (not `APP_NAME`). Walk dates + article/event are **dev-DB only**. Lighthouse ≥ 85 not measured in this VM. |
| W1.6 funnel measurement | Yes. `funnel_events` (no `academic_year_id` — website conversion, like `leads`); `course_view` / `register_click` / `registration_started` / `payment_completed` / `whatsapp_click` / `syllabus_download`; admin `/admin/public-site/funnel` + CSV. | `CourseFunnelTest`. | Walked **#117**: Arabic Beginners show writes `course_view` (3); sticky/hero Register beacon `register_click` (1); WhatsApp beacon `whatsapp_click` (1); checkout (logged-in continue) `registration_started` (1). Admin `/en/admin/public-site/funnel` lists the course, view→click **33.3%**, ADR-026 decision “Keep iterating W1 content…”. CSV `funnel.csv`. | Client may only post click names. `payment_completed` is webhook/`finalizeByReference` success only (never the return URL). Admissions/Finance pass **strings** into Website `RecordFunnelEventAction` (no Enum import). No new Spatie permission; no AppShell link. Iterate-from-data rule is ADR-026. |
| W2.1 Quran translations | Yes. `quran_translations` on existing `quran_ayahs`; `QuranTextProviderInterface` (Hifz `ReadQuranTextAction`); `quran:import-translations`. | `QuranTextProviderTest`. | Walked **#118**: this DB had no `quran_ayahs` (HifzDemo not seeded). Created walk mushaf + Fātiḥah 1:1, imported fixture EN+DV; provider returns Arabic + Pickthall English + Dhivehi teaching gloss. | No parallel Quran tables. Fixture is **not** a published edition (ADR-023). Operator imports a licensed set. No Hifz dashboard change. No public widget yet (W2.3). |
| W2.2 daily content store | Yes. `daily_contents` (no `academic_year_id` — public-site calendar, like `leads`); ayah/hadith/saying/reminder; Blade `/admin/public-site/daily-content` calendar + form + queue + theme batch + CSV. | `DailyContentStoreTest`. | Walked **#119**: admin saved Fātiḥah 1:1 ayah draft; Supervisor Ibrahim published Bukhari 1; CSV `daily-content.csv`; theme batch 3 knowledge reminders still draft. | Hadith four-field gate + maker–checker (ADR-024). Website uses `QuranTextProviderInterface` only. No AppShell link. |
| W2.3 public display | Yes. Homepage widget (`daily.homepage_layout` stacked/rotate, fallback to latest published); archive `/daily/{type}` + permalink `/daily/{type}/{date}`; Article JSON-LD + OG; 1080×1080 share cards via Media `ImageProcessorInterface`; `daily-content:publish-due` 00:05 Indian/Maldives. | `DailyPublicDisplayTest`; `PublicRouteNamesTest`. | Walked **#121**: `/en` widget shows today’s ayah **Al-Fatihah 1:1** (Pickthall EN + Dhivehi) and Bukhari 1 hadith as **fallback** (`data-fallback`); permalink `/en/daily/ayah/2026-08-27` Article schema (headline Daily ayah · Al-Fatihah 1:1, no fixture gloss in JSON-LD), RTL Arabic/Dhivehi, WhatsApp + Twitter; archive `/en/daily/ayah`; hadith permalink shows **Bukhari 1 · sahih · Sahih al-Bukhari**; `card.png` is 1080×1080; sitemap includes `/en/daily/ayah/2026-08-27`. | Prayer/Hijri is **W3**. No AppShell link (wrap stays 83). Share-card Arabic is unshaped (GD `imagettftext` has no HarfBuzz); HTML RTL is correct. Permalink shape: `/daily/{type}/{date}`. Additive `share_card_path`. |
| W2.4 subscriptions | Yes. `daily_content_subscriptions` + `daily_content_deliveries` (no `academic_year_id` — website operational log, like `leads`); SMS/email/push schema; `daily-content:deliver` every 15 min Indian/Maldives; token + STOP unsubscribe. | `DailyContentSubscriptionTest`; `PublicRouteNamesTest`. | Walked **#124**: student Ahmed Hassan opted in SMS ayah+hadith 06:00; `daily-content:deliver` logged SMS to **+9607772434** (permalink `/en/daily/ayah/2026-08-27`, STOP, no Arabic; LogSmsSender `env=local`); second run delivered 0; token unsubscribe paused immediately; admin `/admin/public-site/daily-subscriptions` SMS **0 active / 1 paused**, CSV `daily-subscriptions.csv`. | Opt-in only; push stored not sent. Empty days skip with no delivery row so a later publish can still send. Website uses Identity Actions + `SmsSenderInterface` only. No AppShell link (wrap stays 83). Seed users have no mobile — walk contact is **dev-DB only**. |
| W2.5 research posts | Yes. Reuses `posts.type` (`article`/`news`/`research`) — no parallel `post_type`. Additive `authors` JSON, `abstract`, `citation_note`, `pdf_document_id` → `media_files`. Public `/research` + `/instructors/{slug}`; admin `/admin/public-site/research`; CSV on both listings. | `ResearchPostTest`; `PublicMediaTest`; `PublicRouteNamesTest`. | Walked **#126**: admin saved **W25 Walk: Dhivehi Tafsir Methods** (`w25-walk-dhivehi-tafsir`) with Ustadha W25 Walk + Dr External Scholar + PDF; guest `/en/research` lists 2026 authors + abstract + CSV; permalink ScholarlyArticle + Download PDF `research-pdfs/`; instructor page shows Ijazah + the paper; `/en/articles/{slug}` and `/en/news/{slug}` **404**; articles/news indexes do not leak the title; sitemap has `/en/research` + permalink. | Free front door only (no paywall). Spec said `pdf_document_id` / `post_type`; implemented as `media_files` FK and existing `type`. L1 library migration deferred. No AppShell link (wrap stays 83). Instructor + PDF are **dev-DB only**. |
| W3 prayer times | Yes. New `PrayerTimes` domain: 366-day categories, islands, times (minutes since midnight), recipient groups, broadcasts + recipients. `PrayerTimeProviderInterface`; `prayer:import` (366-row gate); leap-year resolver; versioned cache; Haversine nearest-island. Public Blade `/prayer-times` + `GET /api/v1/prayer-times` + homepage widget. Admin Blade `/admin/prayer-times/*` (`prayer.manage`). SMS preview → confirm → queue via `SmsSenderInterface`; S1 `prayer_reminders` consent; STOP keyword. | `PrayerTimesTest`; `ConsentTest`; `PublicRouteNamesTest`; morph-map; architecture. | Walked **this PR** 2026-08-27: `/en/prayer-times` 200 Malé · 14 Rabi' al-awwal 1448 AH · Fajr **09:00** (synthetic); `/dv/prayer-times` 200; homepage widget; API JSON 200; sitemap includes `prayer-times`; admin islands (Malé + Hulhumalé + Hithadhoo) + CSV `prayer-islands.csv`; import page; SMS preview included=1 cost=0.40 MVR; confirm queued; `queue:work --once` sent=1 `LogSmsSender env=local` to **+9607772434**; STOP → next preview included=0 `no_consent`. | **CODE + TESTED + USABLE.** Spec said Inertia; public/admin UI is Blade (public site still Blade). `salat.db` is **not** in the repo — seeder is a synthetic 366-day fixture (Fajr 09:00 is not Bake&Grill Malé). Admin role still lands on `/portal/overview` (D3), so the super-admin Blade prayer box was **not walked** (no super_admin seed user). No AppShell link (wrap stays 83). Walk phone/consent are **dev-DB only**. Rule 10 exemption: no `academic_year_id`. |
| S3.5 standards | Yes. | `StandardsTest`. | UNVERIFIED. | |
| S3.6 report cards | Yes. Templates, queued HTML via `HtmlDocumentRenderer`. | `ReportCardsTest` Content-Type HTML; ADR-012 HTML decision. | Walked **honest HTML** (R3 S5) plus ADR-012 citation (#97). Queue worker required. | HTML is the supported output (ADR-012 amended). |
| S3.7 awards / docs | Yes. HTML certificates/ID cards. | `AwardsDocumentsTest`. | UNVERIFIED. | Also HTML, not PDF (`AwardController`). |
| S4.1 finance schema | Yes. Year/term on invoices, receipts. | `FinanceSchemaTest`. | UNVERIFIED as a user task. | |
| S4.2 fee structures | Yes. | `FeeStructureTest`. | UNVERIFIED (structure was **seeded** for the walk). | Default seed now includes pilot fees via `PilotRehearsalSeeder` (#87). |
| S4.3 invoice generation | Yes. Generate/issue/arrears. | `InvoiceGenerationTest`. | Walked **ok** on Pilot year (R3 S6): admin lists **all statuses** (`draftsOnly=false`, #91); sent rows visible. Period defaults from year’s term (`ResolveDefaultTermPeriodAction`). Issue SMS is **log** outside production (#86). | Extra year tab can still look empty if that year has no invoices. |
| S4.4 payment plans | Yes. | `PaymentPlanTest`. | UNVERIFIED. | |
| S4.5 adjustments | Yes. | `FeeAdjustmentTest`. | UNVERIFIED. | |
| S4.6 payment + portal | Yes. Webhook + parent Fees. | `PaymentPortalTest`. | Walked **partial** (R2 S6): parent saw 3 invoices + Pay now. BML **not** exercised. | |
| S5.1 staff attendance | Yes. | `StaffAttendanceTest`. | UNVERIFIED. | |
| S5.2 leave | Yes. | `LeaveManagementTest`. | UNVERIFIED. | |
| S5.3 contracts | Yes. | `ContractsComplianceTest`. | UNVERIFIED. | |
| S5.4 recruitment | Yes. Public `/careers`. | `RecruitmentTest`. | UNVERIFIED. | |
| S5.5 performance/CPD | Yes. | `PerformanceTest`. | UNVERIFIED. | |
| S5.6 payroll | Yes. **Flagged off** (`PAYROLL_ENABLED` + `payroll.enabled`). | `PayrollTest` (turns the flag on). | UNVERIFIED; default **off** is by design. | |
| 1A.1 auth/roles | Yes (Phase 0 + S1). | Auth tests, `RoleLandingTest`. | Walked login **ok locally** (R2/R3). Teacher `/dashboard` → Today (#88). Parent/student `/dashboard` → composed `/portal/home` (D1). Admin/headmaster `/dashboard` → `/portal/overview` (D3 #111). Staging login **fail**. | |
| 1A.2–1A.7 course engine | Yes. Catalog, outline, text/media blocks, glossary term bank + lesson attach, `/learn`, portal learning. | Matching `tests/Feature/Courses/*` including `GlossaryTest`. | Glossary walked (#102). Rest of 1A still UNVERIFIED. | `glossary_items` / `lesson_glossary_items` (SPEC §22). |
| 1B.1–1B.6 offerings/PWA | Yes. Offerings, pin/seats, sessions, extra blocks, unlock/completion, PWA/i18n. | Matching Offerings/Progress/Pwa tests. | UNVERIFIED. | 1B.5 tests the 2/3 = 66 formula. **1B.5's "evaluators" are one hardcoded policy each** — sequential unlock, required-lessons+sessions completion — now behind contracts with a single implementation (ADR-022). No per-course strategy config exists; ROADMAP §2a describes the target, not `main`. **1B audit (2026-08-27):** seat limits, pinning, sessions (§2d L1), PWA all verified solid; but §3.4's split **backfill was never written** — offerings are created lazily, legacy enrollments keep `course_offering_id = null`, and the public site still reads legacy `courses.seats`/`enrollment_deadline`. Backfill is mandatory before first real use (see ROADMAP §3.4 as-built note). |
| 2.1–2.5 activities | Yes. Four patterns, bank, assessment player, review, session polish. Class quizzes/assignments migrate onto the same engine. Unified gradebook via `GradeItemContract`. | Matching Courses/Progress tests + `LegacyAssessmentMigrationTest` + `UnifiedGradebookTest`. | Quiz/assignment migration walked **#104**. Unified gradebook walked this PR. Rest of 2.x still UNVERIFIED. | **Phase 2 audit (2026-08-27):** scoring covers all four patterns (teacher-marked short-circuits to review); review loop + standards-tied question bank verified; rule 6 holds behaviourally. **Deviations:** `Courses/Components/` was never created — Arabic/Quran code lives in `Courses/Models`+`Actions`, so rule 3's Components clause guards an empty set (correction point: Phase F, which creates `Components/Quran` and moves Arabic in the same slice — FQCN moves need morph-map + baseline updates together). Spec §43 `student_submissions`/`teacher_feedback` replaced by attempt `answers` json + review fields (recorded, fine). See ROADMAP §2a as-built notes. |
| Arabic A.1–A.3 | Yes. Letters/harakas, skill tag, reports. | `ArabicReferenceTest`, `ArabicSkillActivityTest`, `ArabicSkillReportTest`. | UNVERIFIED. | No AI (rule 8). **Audited 2026-08-27: PASS** — tables + `NormalizeTextAnswerAction` (spec normalization) + reports verified; skill metadata rides the four activity patterns (placement caveat = Phase 2 Components note). |
| Qur’an A.1–A.4 | Yes. Read actions, recitation metadata, mapping, dual-write **off**. | Matching Courses/Offerings tests. | UNVERIFIED. | No Hifz dashboard change. `QURAN_HALAQA_DUAL_WRITE` default false. **Audited 2026-08-27: PASS** — rule 11 held (no parallel Quran source tables; reads via `QuranReferenceReader` contract, Hifz implements as owner; `quran_translations` is planned new data, not duplication); mapping tables morph-aliased; dual-write env-flagged default-off per rule 9 with tests. Hifz freeze verified: 3 recent commits are pure additions (read actions/contract impls/bindings), compliant with ADR-021 scope-discipline freeze. |
| Hifz (frozen) | Legacy Blade exists. | `HifzAuthorizationTest` etc. | UNVERIFIED this week. Out of scope to change. | Rule 7. |
| Pilot blockers #79–#84 | On `main`: picker, AppShell logout, seed contacts, class-teacher field, periods CRUD, teacher generate-today. | Matching Pest files. | Walked in R2/R3. | |
| Round-2 fixes #86–#92 | On `main`: SMS log-bind (#86), seeder school (#87), role landings (#88), term-grades banner + HTML label (#89), fill-grid identity (#90), generate/uniqueness/invoices (#91), DoD browser walk (#92). | Matching Pest files (SMS, seed, landings, term grades, register, uniqueness, invoices). | Walked in **Round 3**. | Records: archive Round-2 fix 1–7. |
| Round 3 notes #93 | Docs only. `docs/PILOT_REHEARSAL.md` Rounds 1–3. | n/a | The walk itself. | `cursor/pilot-rewalk-063c` was **not** merged (stale product overlap of #79–#84). |

## 3. Current blockers

### Agent-doable (remaining after #86–#93)

1. **AppShell nav IA** — 50+ wrapping links, duplicate labels. **Proposed, awaiting decision** in `docs/APPSHELL_NAV_IA.md` (PR #98). Do not implement until Accept / Accept with edits / Reject. The wrap is still live.
2. **Parent notified shows — on excused** — column exists (#86); SMS body is not visible in the portal; log-only outside production.

### Operator-only

1. **Staging login / seed** — no SSH from this environment; webhook deploy only (`docs/STAGING.md`). Seed passwords 302 back to login. Someone with server access must seed (or set real passwords) and paste `students:verify-unification` + `morph-map:verify` for **current** `main`. Round 3 ranked #1.
2. **GitHub branch protection** — docs exist (`docs/BRANCH_PROTECTION.md`); apply was 403 (archive A4).
3. **Deploy 3 cleanup** — proposal only (`docs/migrations/s11-deploy-3-cleanup-proposal.md`). Dual-write still on. **Do not execute.**
4. **Credential smoke / BML sandbox** — never completed on staging.
5. **`QURAN_HALAQA_DUAL_WRITE`** — leave off until an operator confirms dual-write; no read switch.
6. **Payroll** — leave `PAYROLL_ENABLED` / `payroll.enabled` off.
7. **SMS_LIVE in production** — local/staging now fail closed. Decide when (if) production should send Dhiraagu.

## 4. Decisions awaiting the owner

| Decision | Why it is blocked on a person, not an agent |
|---|---|
| **Pilot timing** | Staging cannot start the rehearsal. Local walk is not `test.akuru.edu.mv`. |
| **Track B vs finishing gaps** | ADR-021 representative gate is green (archive). Track B leftovers **B1–B4 are on main** (#102–#105). Remaining school-loop gaps: staging, AppShell nav. |
| **Deploy 3** | Confirm or reject the cleanup proposal. Do not run it as a drive-by. |
| **Branch protection** | Apply on GitHub or accept that every PR must wait for CI and not self-merge (S2 kickoff terms). |
| **SMS_LIVE / production flag** | When (if) production should send Dhiraagu. Local/staging already bind `LogSmsSender`. |
| **HTML vs PDF documents** | **Decided (#97).** ADR-012: HTML is the supported production output. PDF is a future `DocumentRendererInterface` binding swap, not a domain `if`. |
| **AppShell nav IA** | `docs/APPSHELL_NAV_IA.md` (PR #98) proposes a role-first, frequency-second map. Reply Accept / Accept with edits / Reject. **Do not implement** until that reply. The wrap is still live. |

## 5. Overstated “done” (DoD now includes a browser walk)

`CLAUDE.md` / `.cursorrules` DoD was amended in **#92**: a slice is done when a user can complete the task **in a browser**, not only when tests pass.

| Claim that was too strong | Wording that matched the evidence (post #86–#93) |
|---|---|
| STATUS “Remaining blockers: none” | Remaining: staging login, AppShell nav. Student create (#95), weights persist (#96), HTML-as-output (#97), roster number-twins (#99), gradebook URL (#100), class teacher on existing class, and year-seeder uniqueness closed. |
| S3.4 “Term grades (done)” | Computes when a weight scheme exists. Banner when missing (#89). Weights UI now persists a scheme (#96). |
| S3.6 “Report cards (done)” | Queued **HTML**; labelled HTML (#89). %/grade fill when a scheme exists (#96). Not PDF. |
| S3.1 weights implied ready | Scales/types seed; Weights UI now posts numeric percents summing to 100 (#96). |
| 1A / 1B / 2 / Arabic A / Qur’an A “done” | Code + Pest exist. **1A glossary** tables/CRUD/player added this slice. Other 1A/1B/2 still **USABLE UNVERIFIED**. |
| S5.1–S5.5 “done” | Code + Pest. **UNVERIFIED**. S5.6 is honestly “done; flagged off”. |
| S2.3 builder “done” | Page exists. R2 extra-period drag did not persist. |

Fixed enough that the old overstatement no longer applies: SMS live-bind, `DatabaseSeeder` ≠ school, Blade parent/teacher landing, fill-grid names-only, generate-0 copy, class/year 500s, invoice drafts-only list.

## 5b. Phase 0 audit (2026-08-26) — findings and fixes

Phase 0 re-audited against `docs/PHASE_0_CHECKLIST.md` and ROADMAP §4. Core
objective **passes**: `app/Models` and `app/Services` are gone, 21 providers
registered, all 6 contracts exist with bindings, and the architecture baselines
have only **shrunk** across 116 PRs (76→74, 184→178, 4→4, 3→3). Morph map has
exact parity: **167 aliases / 167 models**.

Fixed in this slice:

| Finding | Action |
|---|---|
| `enforceMorphMap()` never flipped; its trigger ("after production verification") can never fire (ADR-021: no production). Provider comment contradicted ADR-005's stated intent. | **Enforced.** Assurance now comes from `MorphMapConfigTest` (every domain model mapped, aliases unique) + audited fact that no Eloquent models exist outside `app/Domains`. ADR-005 amended. |
| Checklist claimed a "duplicate `create_otps_table` migration". **False premise** — `2025_10_15_161251` creates `otps`, `2026_02_16_000002` creates `user_contact_otps`; only filenames matched. | Legacy `otps` table was dead (`Models\Otp` reads `user_contact_otps`; last reference a stale truncate). Dropped forward by `2026_08_26_000001`; stale truncate removed from `ClearNonAdminUsers`. Checklist corrected. |
| Checklist claimed `app/Http/Controllers` = `Controller.php` only; `Api/TestDeployWebhookController` disproves it. | Claim corrected. Controller **stays** — it is app infrastructure, not domain logic; an Ops domain for one controller would violate ROADMAP §7. Recorded as an accepted exception. |
| Per-domain `routes.php` split "deferred to early S1" — skipped through S1–S5, 1A–1B, Phase 2, A-track. | **Dropped, not deferred.** Central routes are the accepted end state; `tests/Feature/Routes/` is the guard. |

Still open (not fixed here):

- **PHPStan remains `continue-on-error`** with ~410 errors and no owner. The fix
  (generate a Larastan baseline, make new errors blocking) requires *running*
  phpstan; this environment cannot `composer install` (proxy returns 403 on
  `api.github.com` / `codeload.github.com`; only git protocol works). Needs an
  environment that can install dev dependencies.
- `2025_10_15_161402` added `users.otp_enabled` / `two_factor_enabled` /
  `phone_verified_at`, which appear unused. Not dropped in this pass.
- Phase 0's "site behaves identically" was never verifiable and still is not:
  the credential smoke has been open since June, and staging HEAD is far behind
  `main`, so the original Phase 0 deploy evidence describes nothing current.

## 5c. S1 + S2 audit (2026-08-27) — findings and fixes

### Fixed in this slice

| Finding | Action |
|---|---|
| **`SendAbsenceSms` throttle bug.** `Cache::add` claimed the once-per-student-per-day key *before* resolving guardian phones, so a student with no reachable guardian burned the day's slot — a guardian attached later that day could never be notified. | Recipients resolved first; throttle claimed only when there is somebody to send to. Regression test added (`ClassAttendanceTest`). |
| **Absence SMS was English-only**, against the S2 spec's trilingual template requirement — for a Dhivehi-first parent audience. | Message moved to `resources/lang/{en,dv,ar}/notifications.php`, rendered in the app locale. There is no per-guardian locale column, so app locale is the best available signal. **Dhivehi and Arabic strings are a first pass and need native review before reaching real guardians.** |

### Corrections to the audit itself

Four S2 findings I reported were **wrong**; verified against the code:

- `attendance_notify` (`absent_only` / `absent_and_late`) **does exist and is honored** — gating happens at dispatch in `RecordClassAttendanceAction:91`, not in the listener.
- **Chronic-absence reporting exists** — `chronic_threshold` is consumed by `ListClassAttendanceAction:106`, with a test covering a 5-day case.
- **Spec test 6 is covered** — `SchoolRequestTest:55` asserts leave approval creates an approved `TeacherAbsence`.
- S1's `term_key` "replace usages" is complete (zero code references remain); only the column drop is outstanding.

### Still open (not fixed here)

- **S2 notifications: 1 of 5 delivered.** Only absent/late SMS exists. Missing: unfilled-register reminder (the report exists, the nudge does not), leave-decision, substitution-assignment, behavior-incident-to-parent. No admin daily digest. This is a missing sub-slice, not a defect — it needs its own slice rather than being bolted on.
- **Timetable builder partial vs S2.1**: substitution overlay and print exist; copy-week, copy-from-class, and teacher-view/room-view tabs do not. Pilot R2 also recorded that dragging an extra period did not persist — still open.
- **Legacy Blade not removed** (S1 DoD line 159, S2 DoD line 91): `students.*` and `announcements` are still routed (`web_localized.php:253`). Same leftover class in both phases.
- **`academic_years.terms` json and `course_enrollments.term_id`/`term_key` columns** still present — deliberate additive deferrals, cheap to drop now that ADR-021 applies.
- **S2 DoD line 1** (teacher completes the loop on a phone, parent receives SMS) remains unverifiable while staging login is blocked. Walked on desktop with the log-fake sender only.

## 5d. S3 audit (2026-08-27) — clean

S3 traced against `docs/S3_SPEC.md` by call path (not single-file grep). **All 15
tables present; every spec rule and CI-gate test verified.** Highlights worth
recording because they are the items usually skipped: roster historical accuracy
(`ListExamRosterAction` excludes students whose `left_at` precedes `exam_date`,
tested), rank ties sharing a rank and recomputing after a mark correction
(`TermGradesTest:132,194`), report-card render asserted `dir="ltr"` for EN and
`dir="rtl"` for DV (`ReportCardsTest:181-189`), standards tagging whitelisting
types and storing **aliases** not FQCNs (`TagStandardAction`), and public
achievements gating photos on active `photo_media_use` consent
(`ListPublicAchievementsAction`).

**Fixed here:** stale ADR references across all five phase specs. S1 said
ADR-002/003, S2 said ADR-004, S3 said ADR-005, S4 said ADR-006, S5 said
ADR-007/008 — every one of those numbers had been claimed by an earlier record,
so the specs pointed at unrelated decisions. Now corrected to ADR-009/010, 011,
012, 014 and 015/016, each with a note on the original draft number. Those DoD
lines are also ticked, since the records exist.

**Two audit findings of mine were wrong** (same error as S2 — inferring absence
from grepping one file instead of tracing the call path):

- **Exam room-conflict checking IS implemented.** `SaveExamAction` warn-confirms
  on exam-vs-exam room+time overlap and calls `CheckRoomSlotConflictAction` →
  `RoomBookingClashChecker` for bookings and timetable slots. Covered by
  `ExamSchedulingTest`. I searched `SaveExamAction` for `TimetableConflictChecker`
  and wrongly concluded the rule was skipped.
- Golden-file tests: the spec asks for golden/snapshot files; the suite asserts
  numeric components and `toContain` on rendered HTML instead. Equivalent
  coverage — a deviation in form, not a gap.

**No S3 fix slice required.**

## 5e. S-track closure items (2026-08-27) — what shipped, what was rejected

Six items came out of the S1–S5 audits. Three shipped; three did not, and two of
those were rejected on investigation rather than left undone.

**Shipped**

1. **S2 notifications sub-slice** (#125) — the four missing notifications plus the
   admin digest. The only genuine feature gap in the S-track.
3. **S4 receipt template** — turned out to be a real gap, not the missing test I
   reported: no `documents/finance/receipt` view existed, so receipts rendered
   through `HtmlDocumentRenderer`'s generic fallback (`lang="en" dir="auto"`).
   Template + trilingual strings + RTL test added.
6. **Deploy 3 checklist note** — `BmlWebhookTest` builds its fixture from a
   `RegistrationStudent` row and is guaranteed to break when that table is
   archived; recorded so the cleanup PR budgets for it.

**Rejected on investigation (premise was wrong)**

5. **Deferred column drops — three of four are NOT safe.** Details in
   `docs/migrations/s11-deploy-3-cleanup-proposal.md`. In short:
   `course_enrollments.term_id` is still live (`EnrollmentService` reads and
   writes it); `term_key` is a **generated** column backing the unique key that
   prevents duplicate enrollments, so a plain drop removes that constraint
   silently; and `students.emergency_contact_*` cannot be dropped because the
   replacement is unfinished — `EmergencyContact` exists as a model but no
   action, controller or screen uses it. Only `academic_years.terms` json is
   safely droppable, and it is low value on its own.
   **New finding:** `course_enrollments.unified_term_id` is a **dead column** —
   referenced only by its migration and a schema-shape test. S1.5's intended
   switch never happened; `term_id` remained live. Needs a deliberate decision.

**Not attempted — needs capabilities this environment lacks**

2. **Timetable builder** — the R2 drag-persist bug needs a browser to reproduce
   (this environment cannot run the app), and copy-week / copy-from-class /
   teacher- and room-view tabs are substantial React work that should be walked
   before merging.
4. **Legacy Blade removal** — 29 references, including the shared
   `layouts/navigation.blade.php` used by every Blade page, and
   `students.quran-progress`, which links into frozen Hifz territory. Removing
   the routes also means editing the route-name snapshot suite that guards them.
   Doable, but not blind: it needs a test run and a browser pass, so it belongs
   in a slice where both are available.

**Also corrected here:** my S4 audit claimed the literal webhook double-POST test
was missing. It exists — `test_webhook_idempotent_does_not_double_enroll` posts
the identical payload twice and asserts a single enrollment. That is the third
audit finding of mine to dissolve on tracing (after four in S2 and two in S3), all
from the same error: concluding absence from a keyword search instead of following
the call path.

## 5f. Phase F — Hifz → engine migration (ROADMAP §2b, freeze lifted for this phase only)

Slice-by-slice, one PR each, CI-gated. The freeze exception covers exactly this
migration; no Hifz behaviour change outside it.

- **F0 (merged, #131):** `Courses/Components/{Arabic,Quran}` created — the Phase 2
  audit correction. 12 classes relocated with morph aliases updated in the same
  commit; two engine-owned seams (`ListSkillTaggedActivitiesAction`,
  `ResolveLatestEnrollmentIdAction`) so components never import engine models;
  `tests/Architecture/ComponentsIsolationTest` now enforces rule 3's Components
  clause against a non-empty set. Known residue: engine still calls component
  actions (SaveActivity validation, passage resolution) — inversion is a Phase F
  follow-up.
- **F1 (merged, #131):** halaqa mirror gate. `halaqa:verify-mirror`
  (+`--mirror-missing` heal) proves every dual-write link's legacy sessions have
  mirrored engine sessions and no link is orphaned; `ListOfferingSessionsAction`
  declares `read_source=engine` + `unmirrored_halaqa_session_ids` additively.
- **F2 (this PR):** structure mapping. Every Hifz program → engine Course
  (`course_type` `hifz`, subject `hifz`) + face-to-face Offering + A.3 link
  (`MapHalaqaProgramAction`, hand-made links respected); sessions mirrored
  regardless of dual_write (one-time migration path,
  `MirrorHalaqaSessionAction(requireDualWrite: false)`); active Hifz enrollments →
  `course_enrollments` + `offering_halaqa_enrollment_links` (morph alias added);
  session-record attendance → engine `attendance_records` (statuses map 1:1).
  Commands: `halaqa:backfill-structure` (idempotent, additive) and
  `halaqa:verify-structure` (rule 9 gate; unresolved listed, never guessed).
  Milestones → completion: `SyncHifzMilestoneProgressAction` in Components/Quran
  is ADR-022's named second `CourseCompletionEvaluator` consumer — a student's
  milestone rows are the required units, approved ones complete; persisted through
  the new engine seam `ApplyEnrollmentCompletionAction`. Mirrored halaqa sessions
  are created `is_required=false` so attendance history can never complete a hifz
  course through the session path — completion stays milestone-driven.
  **Recorded limitations:** (1) reader exposes ACTIVE Hifz enrollments only —
  paused/completed/transferred stay legacy-side until their own decision; (2) an
  attendance edit after milestone completion re-runs lesson/session progress sync
  and regresses `progress_percentage` (status/completed_at survive) until the next
  milestone sync — F3/F4 wire milestone sync to events instead of the backfill
  command. **Verification gate output must be captured here before any deploy
  that switches Hifz reads to engine structure.**
- **F3 (this PR):** the four §52.19–52.22 tables engine-keyed in
  Components/Quran — `quran_recitation_submissions` (course_enrollment_id +
  unified student_id + academic_year_id, audio via `media_files`, spec statuses
  incl. reserved ai_* values nothing sets — rule 8), `quran_mistake_marks`
  (letter/haraka ids FK the Arabic component's TABLES only; the Quran component
  never references Arabic code — isolation test holds), `quran_revision_schedules`
  and `quran_memorization_progress` (upsert per student+surah+range). Existing
  `surahs` reused via `QuranReferenceReader` (rule 11); enrollment resolves
  through the F0 seam. §52.2 haraka-strict rule is its own action
  (`DeriveHarakaMistakeAction`): same letter + different haraka → `wrong_haraka`,
  different letter → `wrong_letter`; review marks derive types when ids are given,
  explicit type required when underivable (loud failure, no guessing).
  `ReviewRecitationAction` (shaped like ReviewAttemptAction) closes a submission
  with marks in one transaction and rolls the student's memorization-progress row
  (passed→passed, needs_repeat→needs_revision, failed→weak). Four morph aliases
  added. Legacy `quran_progress`/`recitation_practices` (old Blade app) stay
  frozen for F5 archive; no backfill from them — they are pre-engine practice
  data, migrated only if the operator asks.
- **F4 (this PR):** the non-AI dashboard surfaces of §52.7–52.13 in
  Inertia/React over F2/F3 data — everything AI-flavoured in those sections
  (live checking, predictions, confidence, training samples) stays out per
  rule 8. Three surfaces: **student** `/learn/quran` (my submissions, my
  memorization progress, upcoming revision); **teacher** `/teach/recitations`
  (review queue oldest-first with status filter + CSV, inline review form
  posting to F3's ReviewRecitationAction — outcome, note, mistake rows);
  **supervisor/dean** `/catalog/quran/oversight` (submissions by status,
  common mistake types, most common wrong letters/harakas §52.12 — computed
  from teacher marks, no AI — teacher activity, per-student progress, CSV).
  The oversight controller is deliberately ENGINE-owned: it composes Quran
  aggregates (bare ids) with Arabic reference names, which no component may
  do itself (rule 3 isolation, documented engine→component direction).
  Teacher gate = has a teachers row (new read-only
  `People\ResolveTeacherForUserAction`) or `courses.manage`. New People
  actions: ResolveTeacherForUser, ListTeachersByIds (both read-only,
  additive). JSX parse-checked via esbuild (CI does not build JS).
  **Deferred, recorded:** student audio record-and-submit (§52.9 manual mode
  — needs private media upload + authenticated streaming; spec itself marks
  student recitation submission "later"); per-guardian/i18n strings are
  fallback-English pending the lang-file pass; supervisor/dean share one
  oversight page until their AI-era features diverge; frozen Hifz Blade
  routes stay until F5 retirement.
- **F5 (this PR — GATED, see ADR-025):** the mandated pre-build investigation
  (grep tests for Hifz imports first) showed F5's halves are coupled: the
  reader implementations can't move without the 7 Quran dataset models
  (courses-never-imports-Hifz guard), the models can't move while the frozen
  Blade app still uses them (would need new cross-domain model imports the
  shrink-only baselines forbid — and the scanners' blind spot for
  `Components\*\Models` paths is not a licence), and the Blade app can't be
  deleted because three workflows have no engine replacement yet:
  **three-lane session-record entry** (F2 mirrors attendance only),
  **assignments** (§52.18 deliberately not built in F3), and the **milestone
  recommend→review→approve workflow UI** (engine only consumes approved
  milestones). ADR-025 records the retirement gate (engine parity walked in a
  browser + green `halaqa:verify-structure` capture + operator sign-off +
  refreshed casualty inventory), the full test casualty list, and the rule
  that models + readers + Blade deletion land in ONE future slice. Legacy
  tables archive, never drop; `surahs`/`quran_*` stay live (rule 11). Also
  fixed here: ADR-022 number collision — the W1 funnel ADR (Cursor track)
  renumbered to ADR-026, references updated. **Phase F outcome: F0–F4 shipped
  and merged (#131–#134); §2b functionally complete for engine-side learning;
  formal retirement waits at the ADR-025 gate.**
- **F5-P1 (this PR — gate item 1 of 3):** three-lane session-record entry on
  the engine. `quran_session_records` (Components/Quran, morph alias added,
  rule 10 `academic_year_id` from the session): new-memorization lane
  (surah/ayah range + result + score), recent/old revision lanes, mistake
  breakdown (haraka/word/fluency; total derived when not given), notes,
  flags, overall status — enum values identical to legacy so the eventual
  data copy is straight. **Attendance is not duplicated**: the save action
  writes `attendance_status` through `RecordOfferingAttendanceAction` into
  `attendance_records` (single source); roster membership is proven through
  the same engine action the sheet reads (`ListSessionAttendanceAction`,
  which additively gains `session.academic_year_id`). Teacher sheet at
  `/teach/quran-sessions/{session}` (per-student expandable form, CSV);
  supervisor review POST is `courses.manage`-gated and mirrors the legacy
  review() (stamps reviewer, clears the flag). Remaining gate items: **P2
  assignments (§52.18)**, **P3 milestone approval workflow**. Browser walk
  of the sheet still needed before the gate counts it (DoD).
- **F5-P2 (this PR — gate item 2 of 3):** §52.18 assignments engine-keyed.
  `quran_hifz_assignments` (morph alias added; rule 10 academic_year_id;
  letter/haraka practice targets via table-level FKs only) with the five
  spec types and seven spec statuses, plus the §52.19 link F3 lacked:
  additive `quran_recitation_submissions.quran_hifz_assignment_id`. The
  loop closes: submit-against-assignment → `submitted`; review outcome maps
  passed/needs_repeat/failed onto the assignment; a repeat submission
  re-opens to `submitted`. Teacher board `/teach/assignments` (own board;
  staff see all; create from live hifz-enrollment targets via new generic
  engine seam `ListEnrollmentTargetsByCourseTypeAction` — course_type is a
  parameter, engine stays subject-ignorant; letter/haraka pickers composed
  at the ENGINE controller like oversight; CSV; cancel). Student dashboard
  gains an additive `assignments` section. Browser walk still needed
  before the gate counts it (DoD).
- **F5-P3 (this PR — gate item 3 of 3):** milestone recommend →
  supervisor-review → approve/reject on the engine, with
  **`hifz_milestones` remaining the single milestone store (rule 11)** until
  the retirement slice: writes cross the boundary through the new
  `Support\Contracts\HalaqaMilestoneWriter`, implemented and
  transition-guarded in Hifz (`WriteHifzMilestonesAction` — pure addition,
  freeze exception §2b), bound beside the readers. Board
  `/teach/milestones` lists mapped programs' milestones with recommendable
  targets from the F2 enrollment links; teachers recommend, review/decide
  are `courses.manage` (the engine permission model does not split
  supervisor from dean — recorded deviation). **Approval syncs straight
  through ADR-022's evaluator**: `DecideQuranMilestoneAction` re-runs
  `SyncHifzMilestoneProgressAction`, so an approved final milestone marks
  the engine enrollment completed in the same request; a decided milestone
  cannot be re-decided. **All three ADR-025 parity items now exist in
  code.** Gate remainder: browser walks of P1–P3, green
  `halaqa:verify-structure` capture here, operator sign-off — then the one
  retirement slice (models + readers + Blade deletion).

## 5g. L-track — Akuru Knowledge Library (LIBRARY_PLAN §39)

- **L1 Foundation (this PR):** `Domains/Library` created (per ROADMAP §9.1:
  Library + shared Commerce, reusing Media/Identity/People). Tables:
  `library_items` (full §35.1 shape — later-phase columns like price,
  writer, commission exist now so the table only grows additively),
  `library_categories` (trilingual name columns), `library_tags` +
  pivot, `library_item_authors`. Four morph aliases; `library.manage`
  permission seeded to super_admin/admin (W3 precedent). Public `/library`
  + `/library/{slug}`: Blade in the public-site zone (the W2.5 research
  precedent — recorded deviation from the Inertia rule, consistent within
  the zone), basic LIKE search + type/category/tag filters + CSV;
  **free-reading gate**: `free_public` reads open, `free_login` lists
  publicly but withholds the body until sign-in (never sent to the
  client), `paid`/`course`/`manual` show locked until L3. Admin
  `/admin/library` is Inertia (new admin area — the rule applies): create/
  edit with tags/authors, publish/unpublish stamping `approved_by`
  (business rule §43.3), categories, CSV. **PDF originals go to PRIVATE
  media** via the existing `StorePrivateMediaAction` (§43.6 — never
  exposed; protected reading is L2). EN strings added to
  `lang/en/public.php`; DV/AR first pass pending native review (existing
  operator item). Next: **L2 protected reader** (private page delivery,
  watermark, progress, bookmarks), then L3 payments, L4 Commerce.
- **L2 Protected Reader (this PR):** page-at-a-time reading with the gate
  run on EVERY request. `library_item_pages` (body split on an explicit
  `<!-- pagebreak -->` marker at save time — §36's secure-HTML path;
  PDF-to-page-image conversion needs server tooling and is recorded as a
  later infrastructure step), `library_reading_progress` (§35.3 upsert,
  completion stamped once at the last page, reading seconds accumulate via
  a throttled beacon endpoint), `library_bookmarks` (toggle per page).
  Three morph aliases. Reader at `/library/{slug}/read?page=N`: one page
  per response, per-user **watermark** (name • email • timestamp; generic
  label for guests on free_public), `free_login` redirects guests to
  login, locked types bounce to the item page; **no download path exists
  in the reader** (§43.6). `/my-library` (auth): continue-reading +
  bookmarks, private to the reader (§43.8). Item page shows Read
  online/Continue for multi-page items and renders single-page items
  inline. Reader UX is server-rendered Blade page turns (public zone
  precedent). Deviations recorded: reading seconds are beacon-optional
  (no JS timer shipped); PDF page conversion deferred as above.
- **L3 Paid Content (this PR, with L2):** money → access, rule-12/§43.5
  strict. New Finance pieces (both generic, Finance-owned):
  `InitiatePayablePaymentAction` (any payable morph + amount → Payment +
  BML redirect) and the `PaymentConfirmed` EVENT, dispatched inside the
  confirmation transaction wherever a VERIFIED provider result flips a
  payment to confirmed (webhook + finalize paths) — never the return URL.
  Library listens (`GrantLibraryAccessOnPaymentConfirmed`, registered in
  LibraryServiceProvider): payable `library_item` → purchase flips
  pending→paid once + idempotent `library_access_grants` row (§35.4; two
  new morph aliases). `ResolveLibraryAccessAction` is now the ONE gate for
  item page and reader: free types answer from access_type, everything
  else from an active grant; buy box on the item page (checkout POST →
  BML redirect; throttled), payment-return page only DISPLAYS state and
  refreshes. `/my-library` gains purchase history; admin gets the sales
  rollup (paid count + revenue per item). Tests fake the provider behind
  Finance's own `PaymentProviderInterface` and walk lock → checkout →
  pending → webhook → grant → reader opens, webhook-retry idempotency,
  not-for-sale/already-owned/guest refusals. Course enrollment's inline
  confirmation path is untouched — folding it onto the event is a
  recorded follow-up, not done here.
- **L4 Commerce (this PR):** platform-wide `Domains/Commerce` (Phase-0
  skeleton provider now real), MVP subset of §39.4 — campaigns, coupons,
  bundles stay post-MVP (§38). **Rule 12 enforced in code and CI**:
  `wallet_transactions`/`gift_card_transactions` are APPEND-ONLY (no
  updated_at, no update path; corrections are reversal rows carrying
  balance_before/after), credit/debit go through the only two actions
  (transaction + lockForUpdate, loud overdraft refusal); the **rule-6 arch
  test activates** — its Phase-0 `todo` is now a real scan (no
  Commerce\Models references and no DB::table on the money tables outside
  Commerce). Gift cards: issued by admin, SHA-256 **hash only** stored
  (§43.19), plain code flashed exactly once; redemption moves the full
  balance onto the wallet (gift card = payment method, §43.13; partial
  redemption is a recorded deferral). Discount codes: percentage/fixed
  with cap/window/minimum/global/per-user limits; redemptions are
  pending→confirmed (webhook or wallet debit) or released; **discounts
  never apply to gift cards** — trivially held since gift cards aren't
  purchasable online yet (admin-issued only; recorded). Library checkout
  integration: optional code reduces the BML amount and the webhook
  confirms the redemption with the payment; **wallet pays in full** →
  immediate paid purchase + grant, no BML round-trip; a fully discounted
  order completes immediately (source `coupon`). Surfaces: `/my-wallet`
  (balance, ledger, redeem form), buy box gains code field + wallet
  button, `/admin/commerce` (issue gift card with one-time code flash,
  manual wallet credit, discount codes). `commerce.manage` seeded.
  **Spec Phase 4 note:** course payments can now adopt
  `discount_redemptions.purchase_type` + the wallet actions — the L4→
  Phase 4 handoff ROADMAP §9 promised. DV/AR strings ride the operator
  item.
- **L5 writer portal (this PR):** §7.4/§11/§43.1–43.3. Writer = ROLE on
  the unified identity (ROADMAP §9 override) — `writer_applications`
  (agreement acceptance §31 stamped) → admin decision
  (`DecideWriterApplicationAction`: approve creates `writer_profiles` +
  assigns the `writer` role via the auth-configured user model, no
  cross-domain model import) → `/write` Inertia portal: drafts
  (`SaveWriterItemAction` — own items only, editable only while
  draft/changes_requested, price is a SUGGESTION, reuses the ONE item
  writer so pages/tags/PDF behave identically), submit
  (`SubmitLibraryItemForReviewAction`), editorial loop
  (`ReviewLibraryItemSubmissionAction`: approved → the ONE publisher
  stamps approved_by; changes_requested/rejected return it), every step
  in the APPEND-ONLY `library_item_reviews` trail.
  `LibraryItemStatus` gains submitted/changes_requested/rejected (public
  listings unaffected — publishedOnly). Admin: applications +
  submissions queues on `/admin/library` (existing library.manage gate);
  item forms (admin + writer) gain PRICE (closing the L3 gap where the
  admin UI could not set a price). Dashboard shows own items, latest
  editor comment, and AGGREGATE sales only (§43.9). Aliases
  writer_application/writer_profile/library_item_review same commit.
  Deferred (recorded): writer suspension flow, bank details +
  agreements tables (ride L6 payouts), writer-funded promotions
  (post-MVP §11.6).
- **L6 writer earnings & payouts (this PR):** §21–§23, §35.5, §43.7.
  `writer_earnings` accrues ONE row per PAID sale of a writer's item —
  from the webhook grant listener AND the wallet checkout branch
  (`RecordWriterEarningForPurchaseAction`, idempotent on the purchase;
  wallet is PAYMENT not discount §16.2). Commission: per-item override →
  writer default → config default 70/30 (§22). Funding models §21 exact:
  shared = writer% × paid; akuru-funded = writer% × original;
  writer-funded = paid − akuru% × original. `SaveDiscountCodeAction`
  gained `discount_funding_source` pass-through (L4 gap — codes were
  always 'akuru'). Earnings mature pending→available lazily after
  `library.refund_window_days` (§24/§43.7 — never payable while
  refundable); a FULL refund flips the earning `refunded` (clawback in
  the same P4.3 listener; an earning already PAID out that later refunds
  is an operator reconciliation case — visible in the report). Payout
  flow: bank details (`writer_bank_details`) → request
  (`RequestWriterPayoutAction`, **blocked by the §9.4 operator gate**
  `library.payouts_enabled=false` until the owner confirms
  tax/accounting treatment — sales accrue meanwhile) → admin marks
  paid/rejected (`DecideWriterPayoutAction`; paid stamps earnings
  paid_at, rejected releases them). Surfaces: writer earnings card +
  bank form + request button on `/write`; payout queue + per-writer
  earnings report + CSV on `/admin/library`. Aliases
  writer_earning/writer_payout/writer_bank_detail same commit.
  **Operator (§9.4):** flip `LIBRARY_PAYOUTS_ENABLED` only after the
  tax/accounting decision; until then requests refuse with a friendly
  message.
- **L7 research workflow (this PR):** §12.2 on top of the L5 editorial
  loop. A SUBMITTED research item gets a peer reviewer assigned by email
  (`AssignResearchReviewerAction` — reviewer is a role on the unified
  identity, granted on first assignment; unique per item+reviewer). The
  reviewer's `/review` Inertia inbox shows ONLY their own assignments
  (ownership enforced in `SubmitResearchReviewAction`); recommendations
  (accept/revise/reject) land in the SAME append-only
  `library_item_reviews` trail the writer reads — reviewers stay
  invisible to each other and to sales. The editor still owns publishing
  (§43.3), but `ReviewLibraryItemSubmissionAction` refuses to approve
  research without a done ACCEPT recommendation while
  `library.research_review_required` is on (§29 setting; config-off
  bypass tested). `library_items.citations` (additive) rides the one
  item writer, shows in the writer/reviewer editors and publicly on the
  research page. Alias library_review_assignment same commit.
  Post-L7 backlog per §38 (recorded): DOI, journal issues/volumes,
  subscriptions, bundles, audiobooks. **L-track L1–L7 complete.**

## 5h. Spec Phase 4 — course payments on the engine (adopting L4 Commerce)

- **P4.1 (this PR):** the ENGINE path for paid enrollment exists — the
  §7 "dual payment path" risk starts closing from the engine side.
  `StartCourseCheckoutAction`: fee = `registration_fee_amount` falling
  back to `fee` (the same money the legacy public checkout charges — the
  two paths cannot disagree on price); free courses take the unchanged
  free path; paid courses create a `pending/paid/payment_status=pending`
  enrollment through the SAME creator (`EnrollSelfLearningAction` grew an
  additive overrides param — one creator, both paths, no duplicated
  seat/offering mechanics), then BML via the generic payable flow
  (`payable_type=course_enrollment`) or wallet (immediate activation) or
  full discount. Activation is `ActivatePaidEnrollmentAction`: payment
  confirmed always; status active only when the course does not require
  admin approval (legacy semantics preserved), idempotent. The
  `PaymentConfirmed` listener registered in CoursesServiceProvider is the
  engine's money→access moment (rule 12; L3's recorded follow-up now done
  for the ENGINE path — the legacy PaymentItem flow keeps its inline
  handling untouched and its webhook test green: both paths verified,
  §7 rule). Discount codes work with `purchase_type=course_enrollment`
  (pending→confirmed by the same webhook/wallet). Catalog UI: paid rows
  show the fee with a discount-code field + wallet button. **Remaining
  Phase 4 work (recorded):** retire the legacy public checkout onto the
  engine path (needs the public-site enroll flow walked + W1 funnel
  events preserved), refunds, and offering-level pricing (fee lives on
  the course; per-offering price is a future decision).
- **P4.2 (this PR):** the legacy public checkout's MONEY mechanics retired
  onto the engine pattern — §7's dual-path risk closed at the
  money→access moment. (The OTP/identity onboarding UX is untouched;
  swapping the public flow for `/learn` remains gated on the operator's
  browser walk + nav decision.) Four changes, one concern:
  **(1) enroll-first** — `processEnrollmentFromSession` no longer writes
  `enrollment_pending_payload`; paid flows go through the SAME
  `EnrollmentService` path as free ones (pending enrollments +
  consolidated Payment + PaymentItems created BEFORE redirecting to BML),
  so the webhook only activates and can never swallow an
  enrollment-creation failure after taking money. Free-enrollment
  notifications now fire only for `payment_status=not_required` rows
  (paid ones announce from the webhook — rule 12).
  **(2) single activation point** — `ActivateEnrollmentOnPaymentConfirmed`
  handles BOTH payment shapes (payable `course_enrollment` and legacy
  PaymentItems); the inline activation copies in `PaymentService` (×2)
  are deleted, and `PaymentConfirmed` now dispatches BEFORE
  notifications so confirmation mail renders the activated state.
  **(3) stray confirmation paths unified** — `PaymentController::
  applyBmlTransactionStatus` (return-URL) and `ReconcilePaymentsCommand`'s
  private transaction both deleted; both now delegate to
  `finalizeByReference`, so reconciled/returned payments finally fire the
  event (Library grants, engine activation), send notifications, and
  record the `payment_completed` funnel — previously they silently
  activated and granted nothing else. DB-facade arch baseline shrank by
  one (PaymentController). W1 funnel events preserved untouched
  (`registration_started` in the public controllers,
  `payment_completed` in PaymentService).
  **(4) legacy-data safety net** — the `enrollment_pending_payload` READ
  branch stays (webhook still finalizes pre-P4.2 payments; pinned by
  test) but nothing writes it any more
  (`createPaymentForPendingEnrollment` deleted). **Cleanup deploy
  (recorded):** delete the safety-net branch +
  `createEnrollmentForConfirmedPayment` + drop the
  `payments.enrollment_pending_payload` column only after
  `SELECT COUNT(*) FROM payments WHERE enrollment_pending_payload IS NOT
  NULL AND status NOT IN ('confirmed','paid','failed','cancelled','expired')`
  returns 0 (rule 9 shape: this deploy stops writing, cleanup is a later
  deploy). Known gap (recorded): mixed free+paid carts redirect to BML
  before showing the free-course confirmation page — free notifications
  still send. Remaining Phase 4 work: refunds, offering-level pricing,
  and the public-flow UX swap (operator-gated).
- **P4.3 (this PR):** refunds — the mirror of the money→access moment.
  `payment_refunds` table (APPEND-ONLY, rule 12; alias `payment_refund`
  same commit) + `RefundPaymentAction`, the ONLY way a payment refunds:
  locks the payment, allows partials but never over the refundable
  remainder, destination `wallet` (credits through the Commerce ledger,
  source `refund` — §35.8) or `manual` (operator returned the money
  outside the system, e.g. BML transfer — no BML refund API call; SDK
  stays behind the interface), flips the payment to `refunded` when
  fully refunded, and fires the new `PaymentRefunded` event inside the
  transaction. Listeners mirror the confirm side: Courses
  `CancelEnrollmentOnPaymentRefunded` (FULL refund → payment_status
  refunded + status cancelled unless completed; both payment shapes;
  discount slot released via new
  `RecordDiscountRedemptionAction::releaseForRefund` — pending AND
  confirmed → released, the customer kept nothing) and Library
  `RevokeLibraryAccessOnPaymentRefunded` (purchase → refunded, grant →
  revoked + ends_at; reader loses access — verified through
  `ResolveLibraryAccessAction`). Partial refunds keep access (recorded).
  Admin surface: refund form per row on the EXISTING
  `/admin/enrollments/payments` Blade (existing-Blade zone — no new
  Blade screen), POST `admin/payments/{payment}/refund` behind
  `role:super_admin|admin` + seeded `payments.refund` permission.
  Deliberately out of scope (recorded): refunding wallet-paid engine
  checkouts (no Payment row exists — admin credits the wallet and
  cancels the enrollment manually), BML-initiated `refunded` webhook
  states (ignored, as before), and L-track writer-earnings clawback
  (§35.5 `refunded` status — L6 payouts must subtract refunded sales).
  Same migration widened `course_enrollments` enums additively —
  `status` gains `cancelled` (code filtered on it but the column never
  allowed it), `payment_status` gains `refunded`.
- **P4.4 (this PR):** SPEC §49 close-out — the last codeable DoD items.
  **(1) Offering price override** ("Offerings may override course
  price"): additive `course_offerings.price_override` (null = no
  override, 0 = free offering of a paid course), settable from the
  offerings admin (`SaveCourseOfferingAction` + Catalog/Index.jsx),
  honored by `StartCourseCheckoutAction` (explicit offering or the
  default self-learning offering via
  `ResolveOfferingPriceOverrideAction` / the extended
  `DefaultSelfLearningOfferingAction` payload) and shown as the catalog
  fee. The LEGACY public checkout stays course-fee-only (its
  enrollments carry no offering) — recorded, not a price disagreement:
  overrides only exist on engine offerings.
  **(2) Manual payment recording**: `RecordManualPaymentAction`
  (provider `manual`, created confirmed, fires `PaymentConfirmed` in
  its transaction) — money received outside the gateway flows through
  the SAME listener path as the webhook; form on the admin enrollment
  page behind `role:super_admin|admin` + seeded `payments.record`.
  `ActivatePaidEnrollmentAction` gained an optional paymentId (sets
  `payment_id` when empty — manual/legacy payments now link).
  **(3) Payment reports**: CSV export on `/admin/enrollments/payments`
  (filtered listing → payments.csv with refunded totals; convention:
  every listing gets CSV). **SPEC §49 DoD now closed in code** — the
  remaining §49 line "students cannot access paid content without
  eligibility" is enforced by payment_status gates (P4.1/P4.2 tests);
  trial lessons / subscription-ready access recorded as post-MVP
  decisions, and the public-flow UX swap stays operator-gated.

## 5i. Arabic Module B — Pronunciation AI (SPEC §51.9–§51.18)

- **Arabic B (this PR):** `Domains/Pronunciation` stood up — LOCAL/offline
  AI behind ONE contract (`PronunciationPredictionInterface`) and the
  `AI_PRONUNCIATION_ENABLED` flag, DEFAULT OFF (rule 8: everything below
  works fully with AI off — attempts are stored and teachers review by
  ear; AI is an accelerator, never a dependency). Flag off binds a null
  predictor; flag on binds `LocalPythonPronunciationPredictor`
  (proc_open → predict.py, JSON only — §51.9: never a cloud speech API).
  Tables §51.12/§51.13/§51.17/§51.18: `arabic_pronunciation_attempts`
  (audio → PRIVATE media), `ai_predictions` (final status by confidence
  threshold + letter/haraka match; a confident correct answer downgrades
  teacher review to a spot-check, everything else stays human),
  `training_samples` (teacher verdict → pending; admin approves/rejects
  — only approved data ever exports), `ai_model_versions` + APPEND-ONLY
  `ai_model_version_events` (register/activate/rollback all audited;
  exactly one active per type; old versions kept). Surfaces:
  `/learn/pronounce` (MediaRecorder practice page), `/teach/pronunciation`
  (staff ear + verified letter/haraka verdict), `/admin/pronunciation`
  (`pronunciation.manage` seeded: sample decisions, dataset cell stats,
  manifest export, model shelf). `/ai/pronunciation` per §51.10:
  audio_processor/model/predict/train/export scripts + README (train.py
  consumes the exported manifest — Laravel never trains, Python never
  touches the DB). Letter/haraka references are TABLE-LEVEL only (no
  cross-domain model imports); labels are `key_name`s matching the
  dataset folders. Five morph aliases same commit.
  **Deferred (recorded):** wiring speaking ACTIVITIES in Components/
  Arabic to the contract (activity_id column ready), §51.8 text
  normalizer contract (typed-answer checking — separate slice),
  audio playback streaming endpoint for the teacher queue (media id
  shown; ReadPrivateMediaAction exists), Qur'an B consumption (§52.3 —
  same service, second consumer). **Operator:** the flag stays OFF until
  a model is trained from real approved samples and §51.17 consent
  handling is confirmed (privacy backlog item).
- **Qur'an B (this PR):** §52.3 — the SAME Pronunciation service gains
  its second consumer (one model family, two consumers).
  `letter_haraka_practice` assignments already carry
  expected_letter/haraka (F5P2); when the flag is on,
  `SubmitRecitationAction` hands the submission's audio to
  `RecordExternalAudioPredictionAction` (Pronunciation owns its models —
  consumers call the ACTION), predictions link to the submission
  (`ai_predictions.quran_recitation_submission_id`, additive), and the
  teacher recitation queue shows the AI opinion beside each submission
  (mismatch/low-confidence visibly flagged). Shared classification core
  extracted (`PredictIsolatedSoundAction`) so Arabic attempts and Qur'an
  drills route through identical confidence rules. Flag off → zero
  predictions and the F3/F4 human flow byte-identical (rule 8; the Hifz
  program is fully functional without AI, per ROADMAP's Qur'an B
  precondition). Deferred (recorded): passage-level recitation
  assistance (future model family), auto-marking mistake marks from
  predictions (teacher stays the only mistake writer).
  **Backlog — owner-approved 2026-08-28, "later" (build on request):**
  **Recitation assist Tier 1** — word-level checking only (skipped /
  wrong / out-of-order words), scoped as: locally-run recitation-trained
  speech model (§51.9 — audio never leaves the server) doing FORCED
  ALIGNMENT against the assignment's known surah/ayah range; output =
  ADVISORY suspected-mistake flags in the teacher review queue via the
  existing contract/queue/model-shelf architecture (second model family
  on the shelf). Ship SHADOW-MODE first: run silently beside teachers,
  measure agreement on real (child, phone-mic, Maldivian-accent) audio
  before any teacher-visible output; student is never auto-notified — a
  teacher confirms every mistake (§52.10). Haraka-level (Tier 2) and
  tajweed (Tier 3) detection stay OUT of scope; revisit only with
  accumulated teacher-marked data. Teacher mistake marks accrue as the
  training/eval set passively from normal use.
## 5j. Phase 5 — mobile packaging (SPEC §50)

- **Phase 5 scaffold (this PR):** the codeable surface of §50 — the
  mobile app IS the responsive Inertia PWA, wrapped. `capacitor.config.ts`
  (shell loads the hosted site via `CAPACITOR_SERVER_URL`, default
  production — one codebase, every deploy reaches the app instantly),
  `@capacitor/*` dev dependencies + `cap:*` scripts, and `docs/MOBILE.md`
  with the build steps and the §50 device test checklist. Everything else
  in §50 is DEVICE work by nature — **operator:** run `npx cap add`
  on a machine with Android Studio/Xcode, walk the checklist on real
  devices, record results here, then store signing/listing. Push
  notifications stay future (devices table exists; needs FCM/APNs keys +
  a Notifications token endpoint). §50's "Optional AI Features" are
  covered by the Arabic B / Qur'an B foundations and stay optional
  (rule 8).

## 5k. Admin operations checklist

- **Ops checklist (this PR):** docs/OPERATOR_CHECKLIST.md as an in-app
  admin page at `/admin/operations` (`operations.manage`, seeded to
  super_admin/admin). Definitions live in code
  (`ListOperatorChecklistAction` — stable item keys, never renumber);
  only tick state is stored (`operator_checklist_checks`: who + when,
  shared across operators; untick deletes — progress tracking, NOT the
  evidence of record, which stays in STATUS.md per the merge gates).
  CSV export per the every-listing convention. Inertia page with
  progress bar and attribution.

## 5l. Admin feature walkthrough

- **Feature walkthrough (this PR):** the whole platform as a testable
  inventory at `/admin/operations/features` — every shipped area from
  the §2 phase table (public site, admissions, engine authoring,
  learning, Arabic/Qur'an, pronunciation AI, academics, exams, finance,
  people, portal, HR, notifications, platform) as items with what-to-
  try + where. Same shared tick store (`operator_checklist_checks`,
  `fw-` keys); a tick = a person walked it in a browser and it worked
  (the USABLE column, made clickable). CSV export; cross-linked with
  the close-out checklist; same `operations.manage` gate. No schema
  change.

## 5m. Trust-signal display floor

- **Display floor (this PR):** the computed students-taught count now
  holds back until it clears `trust.students_min_display` (default 25,
  `ComposeHomepageTrustAction::DEFAULT_STUDENTS_MIN_DISPLAY`) — a hero
  saying "9 students" undersells; below the floor the signal is omitted
  entirely (W1.2's nothing-is-invented ethos). A MANUAL
  `trust.students_taught` override always shows — the operator chose it.
  Also this window: hero slide stage fixed-height → min-height (phone
  widths clipped the Viber CTA under the trust stats; verified with
  rendered before/after screenshots at 1366/390px). Years-operating
  removed from the hero stats row entirely (owner call) — still
  composed for future non-hero surfaces, but it no longer renders or
  counts toward has_signals.

## 5n. Dhivehi translation overrides (T1)

- **T1 (this PR):** the lang/dv files carry machine-made strings a
  native speaker needs to fix without deploys. `translation_overrides`
  (locale+group+key unique, updated_by) + `DatabaseOverrideLoader`
  (extends FileLoader; DB override wins, file is fallback; cached per
  locale+group, cache forgotten on save; serves file-only before the
  table exists so migrate/console never break). Admin editor
  `/admin/translations` (`translations.manage`): every EN reference key
  with file-dv and a correction box; SUSPECT filter flags dv that is
  empty or identical to EN (the machine leftovers); CSV export.
  English keys are the reference — only existing keys accepted. UI is
  dv-only for now; schema supports any locale. **Deferred (recorded):**
  T2 machine-translation assist behind a Null-default contract
  (suggestions only, never auto-published — religious terminology);
  wrapping hardcoded-English admin JSX pages in keys (separate
  long-tail; public/portal surfaces already use __()).

## 5o. Admin nav links for ops/translations

- **Nav (this PR):** AppShell gains permission-gated links (Ops
  checklist, Feature walkthrough, Translations) via a new shared
  `auth.can` prop — nav-only hints, every route still enforces its own
  middleware gate. Pages were previously reachable only by URL.

## 5p. T2 — translation suggestions (contract only)

- **T2 (this PR):** `MachineTranslatorInterface` (Support contract) +
  `NullMachineTranslator` default binding via
  `services.machine_translator.driver` (env `MACHINE_TRANSLATOR`,
  default null — rule 4: any real provider lands behind this interface
  in its own slice). Editor gains a Suggest button (hidden while the
  Null binding is active) that PREFILLS the correction box via
  `POST /admin/translations/suggest`; nothing saves without a human
  pressing Save — machine output never auto-publishes (religious
  terminology). **Operator:** choosing/wiring a real provider (keys,
  cost, data-residency for student-visible text) is a future decision.

## 5q. JSX i18n tranche (user-facing pages)

- **Tranche (this PR):** the user-facing Inertia pages built this week
  now run through translation keys: Pronunciation Practice (12 learn.*
  keys), Writer portal + Peer review (24 common.* keys). `i18n.common`
  joins `i18n.learn` in the shared Inertia props, so these strings ride
  the T1 override loader — correctable at /admin/translations. dv/ar
  values seed as ENGLISH placeholders on purpose (honest EN beats wrong
  machine Dhivehi); the editor's Suspect filter queues them for the
  native speaker. Remaining hardcoded-English admin JSX stays the
  recorded long tail (§5n).

## 5r. Public-site discoverability links

- **Links (this PR):** the Knowledge Library was ORPHANED on the public
  site — /library served but nothing linked it. Nav (desktop + mobile)
  gains Library (existing `public.Library` key, so the label is already
  translatable); footer Quick Links gain Library, Daily reminders
  (`/daily/ayah`), and Careers (also previously orphaned). Footer labels
  stay raw-English per the existing footer pattern; `Careers` /
  `Daily reminders` lang keys added en/dv/ar for when the footer is
  localized.

## 5s. Real prayer times (Bake&Grill salat.db)

- **Real data (this PR):** `database/salat.db` — the Bake&Grill
  Maldivian dataset (42 zone categories × 366 leap-indexed days, 205
  islands, 188 active) — is now IN the repo, closing W3's "salat.db is
  not in the repo / Fajr 09:00 is not Bake&Grill Malé" gap.
  `ImportPrayerTimesFromSalatDbAction` now reads the REAL column shape
  (`IslandId`/`Island`/`Minutes`/`Status`/`Fajuru`; `Date` 0–365
  normalized to our 1–366) — the previous candidates were guessed names
  the real file never matched (islands would have collapsed to id 0 and
  the 366 gate failed). Bulk upserts (was per-row updateOrCreate) keep
  the 15k-row import seed-fast. Latin names backfill from
  `Support/IslandLatinNames` — Bake&Grill's curated Thaana→Latin map
  (21 atolls / ~190 islands) ported verbatim, dot-insensitive atoll
  fallback. Seeder prefers the real file (PRAYER_TIMES_DB overridable,
  mirroring Bake&Grill's contract; synthetic fixture only when absent)
  and defaults the island setting to real Malé (IslandId 102). Display
  stays Akuru-branded — the data changed, not the design. Tests: real
  column-shape mini fixture (mapping, day shift, Status, latin
  backfill) + end-to-end seed of the committed file (205/15372/188,
  Malé default, real Fajr band). Follow-up same window: the deployed
  TEST server had NO prayer data ("no island selected" API answer — the
  W3 walk was a local dev DB), so the admin import page gains an
  "Import bundled dataset" one-click (server-side database/salat.db, no
  upload) and any successful import now sets prayer.default_island_id
  to Malé when the setting is missing or dangling. **Operator:** on
  test, Admin → Prayer times → Import bundled dataset — one click,
  page + API go live with real times.
  Migration 2026_08_28_000031 additionally auto-imports the bundle on
  any deployment whose prayer tables are EMPTY (guards: skips testing
  env, existing rows, missing bundle) — test went live on deploy with
  no operator step; the admin one-click remains for re-imports.

## 5t. Prayer banner in the site chrome (Bake&Grill parity)

- **Banner (this PR):** Bake&Grill's PrayerBar ported to the Akuru
  public site in Akuru colors — desktop: compact pill in the nav row;
  mobile: strip between the nav and the page content (above the hero),
  site-wide like Bake&Grill's header placement. Next-prayer name +
  time + live countdown; expands to the six-cell day grid (gold
  highlight on the next prayer); island pill opens the searchable
  picker grouped by atoll (205 real islands); "Use my location" resolves
  the nearest island through the existing lat/lng resolver; localStorage
  caches the chosen island + day tables (akuru_pt_* keys), MVT clock
  with server-time skew. New `GET /api/v1/prayer-times/islands` (active
  islands, DTO shape); day/nearest ride the existing endpoint. Labels
  through __('public.*') so the Dhivehi editor reaches them. Assets
  partial included once; banner markup twice (script drives all
  instances, as in Bake&Grill).

- **Banner placement follow-up (2026-08-28, operator request):** the
  old maroon prayer box below the hero is removed everywhere (include,
  `public/home/_prayer.blade.php` partial, `ComposeHomepagePrayerAction`
  and its consumer-list pin in `PrayerTimesTest` — the banner + API are
  now the homepage's prayer surface). Mobile ribbon moved from the strip
  under the nav into the header row itself, between the logo and the
  translate button (`.header-prayer--mobile`, flex-1 pill; island pill
  hidden ≤520px, countdown hidden ≤400px so it fits a 390px header —
  both still reachable from the expanded panel). Banner markup count
  stays 2 (desktop slot + mobile slot, both in nav.blade.php).

- **Mobile polish round 2 (2026-08-28, operator request):** (1) translate
  button shrunk on phones (px-1.5/py-1, icon 3.5) and right-side gap
  tightened to gap-1 so it sits closer to the hamburger; (2) mobile pill
  height reduced to 32px and the island now SHOWS in short form
  (`data-pt-loc-short`: island name, >6 chars → 5+ellipsis; full "K. X"
  label ≥520px) instead of hiding; (3) expanding on mobile no longer
  stretches the header — the panel is a fixed full-width sheet pinned to
  the nav's bottom edge (1rem side margins, JS re-pins each tick);
  (4) Hijri date line (`data-pt-hijri`, api `hijri.formatted`) added to
  the expanded panel on both slots; day cache bumped to `akuru_pt_day2_*`
  storing `{times, hijri}`. Render test also pins the hijri node count
  (by class — the bare attribute appears in the JS selector too).

- **Mobile polish round 3 (2026-08-28, operator request):** KEY FINDING —
  `public/build` is a COMMITTED Vite build and the TEST deploy only
  git-pulls, so any Tailwind utility not already in the compiled CSS
  silently no-ops (that broke the translate icon: `w-3.5`/`sm:*` don't
  exist in the build). Public-site tweaks must use classes already in the
  build or plain scoped `<style>` CSS. Also: the public layout forces
  `min-height/min-width: 44px` on ALL button/a ≤768px (tap-target rule) —
  compact header controls need explicit exemptions. This round: icon back
  to `w-4 h-4`; `.nav-translate`/`.nav-burger`/`.nav-right` scoped CSS
  (smaller box, tighter gap, burger negative right margin so the icon
  aligns flush with the container's right content edge); pill + translate
  box both measured at exactly 26px; expanded date line now shows BOTH
  dates ("28 August 2026 · 15 Rabi' al-awwal 1448 AH").
  Round 4: both controls raised 15% to exactly 30px (translate py .375rem;
  pill 28px content + borders), operator request.

## 5t. Writer per-book sales + Vite bundle refresh (2026-08-28)

- **Per-book sales table (operator request):** new
  `ListWriterItemSalesAction` groups `writer_earnings` per item — copies
  sold, gross MVR, the writer's own share (matches the payout ledger),
  and refund count — served as `item_sales` on `/write` and rendered as
  a "Sales by book" table inside the earnings card. New `library_*` keys
  in en/dv/ar common.php (Dhivehi editor can override). Test in
  `WriterEarningsTest` covers grouping, share math, refunds, stranger
  isolation, and the page payload.
- **CRITICAL FIX — deployed JS bundle was stale since D3:** `public/build`
  is committed and the TEST deploy never runs Vite, and no build had been
  committed since the D3 slice — so every Inertia page added after it
  (Library Write/Review, Settings Operations/Features/Translations, the
  JSX i18n tranche) was MISSING from the deployed bundle. Rebuilt and
  committed (`npm install` — lockfile was behind package.json after the
  Phase 5 capacitor additions — then `vite build`). Also added
  `resources/js/**/*.jsx` to the tailwind content globs (JSX-only
  classes previously never reached the compiled CSS; additive).
  **Process rule going forward: any PR that touches resources/js must
  also commit a fresh `npm run build`.**

- **Countdown label parity (operator request):** the collapsed pill now
  reads "Fajr 04:47 · next in 5:33:48" — B&G prefixes the timer with
  "next in" (their `prayer.next_in` string); Akuru showed the bare
  timer. New `public.next in` lang key (en/dv/ar) feeds the script.

## 5u. Recitation module plan (2026-08-29, operator request)

- `docs/RECITATION_MODULE_PLAN.md` — implementation plan (PLAN ONLY, no
  app code) for the Tarteel-like recitation practice module. Grounded in
  verified repo facts: reuses `QuranHifzAssignment` /
  `QuranRecitationSubmission` / `QuranMistakeMark` (manual pipeline
  already shipped), `quran_mushafs/ayahs/words` tables (rule 11),
  `QuranTextProviderInterface`, the `Domains/Pronunciation`
  contract+flag pattern (rule 8), and existing People consents
  (`ai_training_samples`). Self-hosted inference: FastAPI +
  faster-whisper + `tarteel-ai/whisper-base-ar-quran` (verified on HF,
  Apache-2.0) on a separate CPU VPS (cPanel host is PHP-only).
  Phases: 0 benchmark w/ go/no-go bars (20 samples incl. children) →
  1 record+manual grade → 2 auto word grading (alignment over
  normalized Arabic, per-age tunable thresholds, never auto-fail) →
  3 hifz mode → 4 follow-along (conditional on Phase 0) → 5 analytics.
  Privacy: process-then-delete default, new RecitationAudioReview
  consent for retention, AiTrainingSamples reuse for fine-tuning.
  Tajweed scoring explicitly out of scope (teacher-listen flag only).
  Supersedes the shorter Tier-1 backlog sketch as the plan of record.
  Amended same day (operator Q&A): §3.2 hosting options with costs
  (rented CPU VPS ~$15–25/mo; self-owned machine with an
  outbound-polling worker — no inbound ports; GPU figures only for
  server-side follow-along), and Phase 4 split into Option A
  (server-side chunks, GPU-sized by concurrency) vs Option B
  (on-device whisper.cpp tiny model in a native app v2, $0 server,
  grading stays server-side).

## 5v. EduPage parity checklist (2026-08-29, operator screenshots)

- `docs/EDUPAGE_PARITY.md` — the Institute used akuru.edupage.org
  BEFORE this platform was built (operator correction); the doc maps
  every menu item from the operator's real EduPage app to this codebase (verified: Timetable +
  TimetableConflictException, TeacherAbsence/SubstitutionRequest/
  SubstitutionAssignment, Message model, LessonLog; missing: student
  homework list, surveys/forms builder, noticeboard feed, pick-up
  notice, student arrivals, competences, account switcher, canteen).
  Academic core is at parity or better; the daily communication layer
  is the gap. Ordered migration path: ① status-tile portal home (copy
  EduPage's home pattern: tomorrow strip + live-status tiles + role
  chip), ② messaging inbox UX, ③ student-facing homework,
  ④ noticeboard, then surveys/pick-up/arrivals/switcher. Stance: run
  Reframed as the remembered-expectations benchmark (no dual-running):
  ship ①–④ before or with the app shell.
  Follow-up in the same PR: `docs/EDUPAGE_FEATURES_PLAN.md` — the gap
  list turned into implementable slices. Wave 1 (daily-habit core):
  E1 status-tile portal home w/ tomorrow strip (extends
  ComposePortalHomeAction + Timetable), E2 messaging threads (additive
  on messages; per-family class threads), E3 homework read-side
  (lesson_logs.homework ALREADY EXISTS — add due date + portal list),
  E4 noticeboard (private, class-targeted). Wave 2: forms/surveys
  domain, pick-up notice, account switcher. Wave 3 owner-gated:
  arrivals, competences, canteen. 5 owner decisions listed; E1 first —
  every later feature feeds its tiles and it becomes the app shell's
  home tab.

## 5w. Desktop header fits again (2026-08-29, operator request)

- **Prayer times tab removed** from the public nav (desktop + mobile
  lists), leaving nine links: Courses, News, Articles, Research,
  Library, Events, Gallery, Achievements, Contact. Merged as #180.
- **BUG — the logo disappeared on desktop.** Reproduced in a mirror
  render of the live header (`test.akuru.edu.mv` markup + the deployed
  `app-BaEi0PBw.css`): at 1366px the nine links plus the 360px prayer
  pill (`width: min(360px, 32vw)`) plus Enroll overflowed the row, the
  logo flex item was squeezed to **0px wide**, and Translate/Login were
  pushed off-screen. The logo was in the DOM the whole time — it had no
  width. Fix, all in nav.blade.php's scoped `<style>` (rule from §5t
  round 3 still holds: the deployed CSS is a committed Vite build with
  no `xl:` variants, so breakpoint work is hand-written CSS):
  - `.nav-logo { flex: 0 0 auto }` — the home link can never collapse.
  - The full link row now starts at **1280px** (`.nav-desktop`, was the
    `lg` 1024px); 1024–1279 falls back to the hamburger layout, which
    already carries every link, so the logo and the prayer ribbon keep
    their room. `.nav-mobile-only`/`.nav-mobile-menu` replace the
    `lg:hidden` utilities and the JS resize threshold moved 1024→1280.
  - Link row uses `gap` instead of `space-x-6 rtl:space-x-reverse`
    (direction-agnostic, so it is RTL-correct for free): .7rem/13px
    below 1400, .85rem/14px above.
  - Desktop prayer pill is the one flexible item —
    `.header-prayer:not(.header-prayer--mobile) { width: min(320px,19vw);
    flex: 0 1 auto }` — it gives up width before anything leaves the row.
- **Measured after the fix** (probe over the mirror, logo width and
  container scroll vs client): no overflow at 1024/1280/1366/1440/1600/
  1920; logo 88px at every width; at 1280 the link row holds 812px of
  content in 935px — **~120px spare, room for about two more tabs**.
  Renders at 390px and 900px are byte-identical to the pre-change
  build, so nothing below 1024 moved.

## 5x. Desktop prayer panel + Viber logo (2026-08-29, operator request)

- **Desktop prayer panel now drops below the header** instead of
  stretching the nav row. The mobile slot already did this (§5t round 2);
  `positionMobilePanel()` is renamed `positionHeaderPanel()` and gained a
  desktop branch: mobile keeps the full-width fixed sheet, desktop hangs a
  fixed dropdown under the pill — `top` = nav bottom, `left` = pill left
  clamped to a 16px viewport gutter, `width` = max(pill, 380px). Matching
  CSS block for `.header-prayer:not(.header-prayer--mobile)
  .prayer-banner-panel`, and the expanded pill keeps `border-radius: 999px`
  in both slots (was 12px, which only made sense while it stretched).
  Verified by driving the live markup into the expanded state in a
  headless render: nav height stays **73px** open or closed, panel lands
  at top 76px / width 380px, 3-column grid with the next prayer
  highlighted.
- **Viber logo replaced with the real glyph.** The old inline path was a
  hand-rolled approximation. New `<x-public.viber-icon>` anonymous
  component carries the authentic Simple Icons (CC0) path, used by both
  the desktop float button and the mobile bottom-bar tab (was duplicated
  ~2KB twice). Brand colour **#7360F2** (verified against Simple Icons
  metadata, not guessed) replaces Tailwind `purple-600`: gradient fill,
  brand-tinted pulse and shadow, float trimmed 64px→56px with a 28px
  glyph, `prefers-reduced-motion` disables the pulse. New
  `public.Chat with us on Viber` key in en/dv/ar.

## 5y. Merge rule matches practice (2026-08-29, ADR-027)

- The written gate said "no bot self-merge" (CLAUDE.md, ROADMAP §4,
  BRANCH_PROTECTION.md) while the owner had been asking the agent to
  merge PR by PR, and on 2026-08-29 made it standing ("always merge").
  Doc and practice had diverged, which costs a turn of asking every
  session and erodes the rest of the file.
- **ADR-027** replaces the blanket ban with the condition that actually
  prevents the recorded S1 failure (ROADMAP §4: *a four-slice PR
  self-merged in two minutes with CI running only post-merge*). An agent
  may merge when the required check has **reported `success` on the PR
  head** — conclusion read back, never assumed — the PR is one slice, and
  gating evidence is already in STATUS.md. Merging with CI queued,
  running or failed stays prohibited, as does weakening branch protection
  to force a merge. All three docs now say the same thing.
- Branch protection itself is unchanged and still **unapplied** (403 from
  the agent token, 2026-08-25); operator action still outstanding.

## 5z. Dead dashboard code removed (2026-08-29, operator request)

- Asked how login differentiates account types; the answer exposed that
  roughly half of `Portal\DashboardController` was unreachable.
  `index()` redirects teacher → `academics.registers.today`, admin/
  headmaster → `portal.overview`, student/parent → `portal.home`, so the
  view-rendering methods behind those branches stopped being called and
  took their helper clusters with them.
- **Fixpoint analysis, not eyeballing:** iteratively removed private
  methods with zero call sites until stable — **33 of 48 dead in 4
  rounds** (`studentDashboard`, `parentDashboard`, `adminDashboard`, the
  9 `getTeacher*`, 7 `getChildren*` and 11 `getStudent*` helpers, plus
  `getUpcomingEvents`/`getAssignmentCompletionRate`). 15 survive, all
  reachable from `superAdminDashboard`, `supervisorDashboard`,
  `teacherDashboard` or `publicUserDashboard`. Re-ran after: 0 dead.
- Four unreachable Blade views deleted (`dashboard.student`,
  `.parent`, `.admin`, `.teacher` — the last orphaned when
  `teacherDashboard()` became a one-line redirect). Verified no other
  references; the `hifz.dashboard.*` views are separate and **untouched**
  (rule 7 freeze respected). **1,289 lines removed.**
- **The arch ratchet caught it, in the good direction.**
  `BaselineArchitectureTest` rule 2 failed with "fixed violators — remove
  from baseline": the deletion eliminated four real cross-domain Model
  imports from Portal (`Academics\Legacy\Models\AssignmentSubmission`,
  `Academics\Models\Attendance`, `Academics\Models\Timetable`,
  `Hifz\Models\RecitationPractice`). Removed those four entries from
  `cross_domain_non_contract.php` — the baseline may only shrink, so this
  is a permanent tightening. Arch green after.
- **Latent trap removed:** `studentDashboard()` returned
  `adminDashboard()` both when the student profile was missing and on any
  exception — anyone re-enabling that branch would have served
  school-wide admin stats to a student.
- Tests: `RoleLandingTest` already pinned the four redirects that made
  this code unreachable. Added the three branches that still render a
  view in place (super_admin, supervisor, and the no-role public user) so
  a future edit cannot silently point them at a deleted view. Feature
  tests need MySQL (absent locally) — CI is the gate for the new cases.

## 5aa. EduPage docs crawl — parity revision 2 (2026-08-29)

- Operator offered live EduPage credentials. Used the vendor's public
  documentation instead: crawled `help.edupage.org` — **41 modules, 3,720
  sub-pages** catalogued from titles, ~16 mechanism pages read in full. No
  account access.
- **Correction to the reason, and the real blocker.** The first pass declined on
  the grounds that `akuru.edupage.org` holds real families' records; the
  operator then clarified **the instance carries no real data**, so that
  objection does not apply. Tested the live route afterwards and it fails for an
  unrelated reason worth recording: **headless Chromium cannot reach external
  hosts from a remote session** — the agent proxy relay closes each browser
  tunnel after ~6 s (`ws_closed_mid_exchange`, ~1.8 KB sent / 39 B received)
  while `curl` to the same host succeeds. EduPage's login form is JS-rendered
  (curl sees only the Google search form), so a scripted login would mean
  reverse-engineering its auth endpoints. **Browser-driven exploration of any
  external site is not available in this environment** — do not retry it; use
  operator screenshots for layout/feel and vendor docs for mechanism.
- **Two corrections to revision 1** (which only saw the operator's enabled
  menu): (1) **"Library ✅ better" was wrong** — EduPage's Library is *physical
  lending* (titles/copies, QR + barcode labels, lend/return, student QR card)
  plus a separate Textbook storage module; Akuru's L-track is a digital
  reader/bookstore. Different products, not a comparison. (2) "Applications"
  understated a whole **approval-workflow engine** whose payoff is automatic
  chaining — approving staff leave offers to create the `TeacherAbsence` the
  existing substitution engine already consumes.
- Mechanism detail that changed the plan: messages carry **polls**, **reply
  policy** (reply-all off by default school-wide) and **saved recipient
  groups**; homework is assigned from the class register and **auto-dated to
  that class's next lesson**, with read/done tracking and a **daily digest that
  lists what is due tomorrow**; sign-ups support **parent confirmation** (only
  from a real parent account) and hand fees to the payments module; pick-up is a
  five-step two-way protocol gated by a **parent security pattern**, not a
  button. E6 pick-up re-estimated 3 days → 1 week; E5 promoted to its own
  Requests domain; messaging 2 → 2–3 weeks.
- Both docs rewritten. `EDUPAGE_PARITY.md` now maps all 41 modules with counts
  as a depth signal; `EDUPAGE_FEATURES_PLAN.md` renumbers to E1–E14 and answers
  two of the old owner decisions from the reference (per-family threads; the
  register stays the single homework entry point).
- **Also surfaced a live defect** (recorded in both docs): roles are additive
  but `DashboardController::index()` tests `isTeacher()` before `isParent()`, so
  a teacher who is also a parent can never reach the parent home from login.
  Separable from E7 and should be fixed on its own.
- E1 has exactly one blocking decision: does the tile grid cover teachers (whose
  home is the separate `portal.overview` composer), or student/parent only.
- **Plan completed to full coverage (same day, operator request).** The first
  rewrite gave concrete slices for the top 8 and waved at the rest; the plan now
  carries **E1–E22**, every one with a scope, an effort figure and acceptance
  criteria, plus a **coverage table mapping all 41 EduPage modules** to a slice,
  a ✅ (already at parity), or a documented "not planned" with the reason — so
  nothing is silently dropped. New in Wave 3: E9 staff-absence→substitution
  wiring (**3 days, no dependencies — the cheapest win in the plan**, since
  Akuru already has both halves), E10 attendance policy depth, E11 internal
  calendar + room booking, E12 consultation slots, E13 materials library, E14
  certificate printing. New in Wave 4 (all owner-gated): E15 lost & found, E16
  physical lending — deliberately named *Circulation* so it is never confused
  with the L-track Library — E17 interest groups, E18 gate arrivals, E19
  sensitive information (**policy before schema**), E20 competences, E21 work
  showcase, E22 notification centre with EduPage's daily-digest idea.
  Explicitly not planned, with reasons recorded: chat, canteen, kindergarten,
  generic AI features (recitation is the better bet), and the Slovak statutory
  outputs. Family-facing "nobody misses EduPage" line = E1–E4 + E9 + E22,
  ≈6–8 weeks.

## 5ab. Admin pages were unreachable from the Blade nav (2026-08-29)

- **Reported:** a super admin could not find the feature checklist. The pages
  were fine — `/admin/operations` and `/admin/operations/features` exist and the
  `operations.manage` permission is granted to `super_admin` and `admin` by the
  checklist migration. **The links were the problem: they lived only in
  `AppShell.jsx`**, the Inertia layout, while super admin lands on
  `dashboard.super-admin`, which `@extends('layouts.app')` — Blade, which never
  renders AppShell. Exactly the failure the CLAUDE.md definition of done names:
  *"Blade landings that hid the work."*
- Audit of all **24 admin GET landing pages** found more of the same class.
  A first pass claimed 15 orphans; that was wrong — most hang off sub-hubs
  (Website CMS, prayer-times). Verified count: **three linked from no view at
  all** (`admin.commerce.index`, `admin.library.index`,
  `admin.pronunciation.index`) and the **five prayer-times pages formed a closed
  island linking only to each other**, reachable only by typing a URL.
- Added to the Blade admin dropdown, each gated by `@can` on **the same
  permission the route's middleware checks**, so what is shown matches what is
  allowed: Ops checklist, Feature walkthrough, Translations, Commerce, Library,
  Prayer times, Pronunciation.
- **Regression guard** `tests/Feature/Routes/AdminPagesAreReachableTest.php`:
  every admin landing route must be linked from `layouts/navigation.blade.php`,
  or listed in `$allowed` **naming the parent screen** it opens from. The first
  draft measured "referenced anywhere" and would **not** have caught the
  reported bug — a link in AppShell.jsx satisfied it, and the prayer-times
  cluster satisfied it by linking to itself. Tightened to measure reachability
  from the Blade nav specifically; verified by reverting the fix, where it names
  all seven pages. A guard that passes trivially is worth nothing.

## 5ac. Super-admin protection + email login fallback (2026-08-31)

- **Operator deleted their own super admin account** from the profile page to
  test what would happen, and was locked out. `users` has no soft deletes, so
  nothing could be restored. `AdminUserController::destroy()` already refused
  to delete super admins; **`ProfileController::destroy()` did not** — it only
  checked the password. Guard moved to the model so every path meets it.
- **The first guard did not work, and its own test caught it.** Registering it
  in `booted()` put it *after* Spatie's `HasRoles::bootHasRoles()` deleting
  listener, which calls `$model->roles()->detach()`; traits boot inside
  `boot()`, before `booted()`, so `hasRole('super_admin')` then queried an
  empty pivot, returned false, and the guard silently passed. **Overriding
  `delete()`** puts the check ahead of every deleting listener and removes the
  ordering dependency. A protection that looks real but never fires is worse
  than none — the lesson is to test that a guard *refuses*, not just that the
  happy path still works. `User::allowSuperAdminDeletion()` is the deliberate
  escape hatch; a test pins that it re-arms even when the callback throws.
  Query-builder mass deletes remain uncovered (documented on the model);
  `ClearNonAdminUsers` is the only such caller and keeps super_admins.
- **Email login ignored `users.email` entirely** — it required a *verified*
  `user_contacts` row (`LoginRequest:58`). Found while recovering the account:
  the recreated user could not log in despite a valid row. The blast radius is
  much wider than recovery — **`RegisteredUserController::store()` creates no
  contact row**, so anyone who registered normally could authenticate exactly
  once (the `Auth::login` after signup) and never again. Admin-created users
  and seeded users had the same problem.
- Fix is deliberately narrow, because unverified contacts are a **meaningful
  state**: `AccountResolverService` and `CourseRegistrationController` create
  contacts with `verified_at = null` while OTP is pending. Rule now: a contact
  row wins when present (verified → in, unverified → refused, preserving the
  OTP gate); **only when no contact row exists at all** does login fall back to
  `users.email`, which is unique so it cannot resolve to two accounts. That
  grants nothing an unverified contact was withholding.
- Tests cover all six branches including the OTP gate staying shut, and an
  end-to-end register → logout → log-in-again that would have caught the
  original bug.

## 5ad. E1 — status-tile portal home (2026-09-04)

- **Scope call:** E1 ships **student/parent only**. The teachers-or-not question
  was put to the operator three times and answered with "start" each time, so
  the smaller option was taken and stated rather than blocking. Teachers keep
  landing on `portal.overview`; folding them in later is additive.
- **New `ListDayTimetableForStudentAction`** (Academics) — one day's periods for
  a student's class. Nothing read that before; Save, Copy, PreviewConflicts,
  Backfill and Sync all write or validate. **E3 needs the same action** for its
  next-lesson due dates, which is why it was split out and shipped first.
  - Honours `CalendarDay.affects_timetable` exactly as
    `GenerateExpectedRegistersAction` does. Without that the strip would promise
    lessons the registers never create — and the strip would be the liar.
  - An unassigned cover request reports **covered-but-unnamed** rather than
    showing the absent teacher; an unstaffed period is the useful thing to say.
  - Teacher names go through People's `ListTeachersByIdsAction`, not a `Teacher`
    import: the cross-domain baseline may only shrink (rule 3). Arch confirms
    nothing was added.
  - CI caught a fixture bug: `substitution_assignments.assigned_by` is a real FK
    to `users` and the test hardcoded `1`, which does not exist under
    `RefreshDatabase`.
- **`ComposePortalHomeAction` gains `tiles` + `nextSchoolDay`**, additive
  alongside `sections` so the existing cards and CSV export keep working. Every
  tile count is derived from the arrays already composed — no new queries — so
  a tile cannot drift from the page it links to. Hifz is omitted when empty
  rather than shown at zero. The prayer tile reuses
  `ComposeDashboardPrayerAction`; it is the one tile EduPage has no answer to.
- **`nextSchoolDay` looks forward up to 7 days**, not just literally tomorrow:
  the Maldivian weekend would otherwise leave a Thursday visitor with an empty
  strip. It returns `is_tomorrow` so the UI names the day honestly instead of
  calling a Sunday "tomorrow".
- **`public/build` rebuilt and committed** — §5t's rule: the TEST deploy only
  git-pulls, so a JSX change without a rebuilt bundle ships invisibly. The CSS
  hash moved too, so the new grid classes are actually in the bundle.
- Still to do for E1: the teacher FAB (deep links into E2/E3, which do not exist
  yet), and the teachers variant if the operator wants it.

## 5ae. E2a — messaging core loop (2026-09-05)

- **Scope call:** E2's full plan (class fan-out, saved groups, polls, blocking)
  is 2–3 weeks. E2a is the cut that is walkable in a browser: **start a 1:1
  thread, see an inbox, open it, reply.** Fan-out, groups and polls are E2b.
  The direction is deliberately one-way for *starting* threads — a family writes
  to the people who teach their child; staff get inbox + reply, which is the
  whole loop from their side. A teacher opening a thread with a whole class
  needs the audience rules that belong to the next slice.
- **Audit first (the §5aa lesson):** the `messages` table and `Message` model
  already existed with `is_important`, `is_read` and per-side soft delete — and
  **nothing in `app/` read them**: no route, no controller, no UI. So E2a is a
  thread layer over an existing table, not a new messaging system, and the plan
  overestimated the work.
- **Additive migration (rule 9):** `message_threads` + `message_participants`
  are new; `messages.thread_id` is **nullable**, so pre-existing rows keep
  working and nothing needs backfilling before reads switch over.
- **Migration lives in `database/migrations/`, not the domain folder.**
  CLAUDE.md says migrations live in the owning domain's `Database/migrations`,
  but **no `loadMigrationsFrom` call exists anywhere** and all 190 migrations
  are in `database/migrations/`. A domain-local migration would simply never
  run. Flagging the doc/reality mismatch rather than shipping a migration that
  silently does nothing — **CLAUDE.md needs an owner decision**: either add the
  loader or correct the convention.
- **Reply policy copies EduPage's default because it was earned:** above 5
  recipients a thread defaults to `author_only`. Without it, one message to
  every parent becomes a message *from* every parent. `author_only` still lets a
  recipient answer — privately, to the author; it withholds reply-all, not the
  right to respond. The thread page says where a reply will land before it is
  sent.
- **Unread is counted from messages addressed to the reader**, not from the
  thread's own timestamp, so a user's own reply never marks their own thread
  unread. `MarkMessageThreadReadAction` updates **both** `last_read_at` and the
  legacy per-message `is_read` flags — leaving the old flag stale would give the
  app two disagreeing answers to "unread".
- **Rule 3 held without growing the baseline.** Three separate places needed
  care and arch tests confirm nothing was added:
  - `MessageThread`/`MessageParticipant`/`Message` resolve the user model via
    `config('auth.providers.users.model')` instead of importing
    `Identity\Models\User`. This also **fixed a live bug**: `Message::sender()`
    and `recipient()` referenced a bare `User::class`, which resolved to
    `App\Domains\Notifications\Models\User` — a class that does not exist. It
    had never fired because nothing called those relations.
  - The Notifications actions take a **thread id**, not a model, so
    `PortalMessageController` never names `Notifications\Models\*`; membership
    is decided by the domain that owns the data.
  - Participant names come from `DB::table('users')`, not an Identity import.
- **New `ListTeacherContactsForStudentAction`** (Academics) — the messaging
  directory. A full staff directory is how a parent writes to the wrong person;
  the teachers on the child's timetable plus the class teacher are the honest
  default and need no new permission model. Two gotchas handled: the roster
  table is `class_student` (singular), and **`classes.class_teacher_id` stores a
  `users.id` while `timetables.teacher_id` stores a `teachers.id`** — merging
  the two id spaces would have silently produced the wrong teachers.
- **Route order:** `/portal/messages/new` is declared before `{thread}`, which
  is `whereNumber`-constrained, so compose is not swallowed by the thread
  matcher. A test asserts this, because it is the kind of thing that breaks
  quietly when a route is later reordered.
- **E1 tile wired:** the home screen gains a Messages tile with an unread badge
  (`ListMessageInboxAction::unreadCount`) — an unread badge is the only thing
  that earns a tile a place on a glanceable home screen — plus a `messages`
  entry in `sections`.
- **Tests:** 14 action tests (directory scoping in both directions, unread
  accounting, both reply-policy branches, morph-alias storage, guardian access)
  and 4 HTTP tests that walk compose → send → inbox → open → reply → the family
  sees it, plus the two 403 paths and the route-order guard.
- **`public/build` rebuilt and committed** — §5t's rule: the TEST deploy only
  git-pulls, so new JSX ships invisibly without it.
- **Not verified locally:** no MySQL in this environment and SQLite cannot run
  the suite (`2025_10_24_073337_add_advanced_fields_to_admission_applications`
  issues `SHOW INDEX`). Architecture tests ran green locally; the DB-backed
  tests are gated on CI. **Browser walk still owed on `test.akuru.edu.mv`**
  after deploy — that is the §5t lesson and it is not discharged by green CI.

## 5af. E3a — homework reaches the family (2026-09-05)

- **The "owner decision" I flagged on E3 was already answered by the code.** The
  question was whether homework extends `lesson_logs.homework` or the existing
  `assignments`/`assignment_submissions` module. `assignments` lives in
  `Academics\Legacy\Models` and `MigrateLegacyAssessmentsAction` migrates it
  into `assessments` — it is graded coursework on its way out, not a homework
  home. `lesson_logs.homework` is the live capture point. **No owner input
  needed; the plan's approach stands.**
- **Same shape as E2a:** `lesson_logs.homework` has existed since 2025-09,
  teachers already fill it in the register (`SubmitRegisterAction`,
  `Registers/Show.jsx`), and **nothing has ever shown it to a student or
  parent**. This slice is the reader plus the two missing pieces.
- **Additive migration (rule 9):** `lesson_logs.homework_due_date` (nullable)
  and a new `homework_ticks` table. Ticks are time-scoped so they carry
  `academic_year_id` (**rule 10**) — taken from the lesson, falling back to the
  class, because `lesson_logs.academic_year_id` is itself nullable on older
  rows. Stamping a tick with whatever year is current when the pupil gets round
  to it would have been the easy wrong answer.
- **Due date defaults to the next lesson, never tomorrow.** New
  `ResolveNextLessonDateForClassAction` scans forward 21 days for the next time
  the class meets *that subject*, honouring `CalendarDay.affects_timetable` with
  the same rule `GenerateExpectedRegistersAction` uses. Homework due on a day
  the class does not meet is how work goes unhanded-in and nobody knows why.
  Returns null when there is no lesson in the window, and the register says so
  rather than inventing a date.
- **Only submitted registers are visible.** A draft register is the teacher's
  working copy; showing half-typed homework to a family is worse than showing
  none. Submitted and Locked count; Expected and Draft do not.
- **A due date without homework is dropped** in `SubmitRegisterAction` rather
  than left dangling on an empty box.
- **Overdue is narrow on purpose:** dated, unticked, and past. Homework with no
  due date is *undated*, not late, and a ticked item stops being overdue.
- **The tick is the pupil's own.** A guardian sees the same list read-only and
  gets a 403 on the tick route — it is the pupil's statement about their own
  work. It is invisible to grading, for the reason EduPage found: the moment a
  self-tick counts towards a mark, pupils stop telling the truth with it.
- **Rule 3 held:** teacher names go through People's `ListTeachersByIdsAction`;
  the Portal controller uses Academics and People **Actions** only. Arch tests
  confirm the baseline did not grow.
- **E1 home** gains a Homework tile badged with outstanding count, derived from
  the same read as the page so the two cannot disagree.
- **Tests:** 13 action tests (roster scoping, draft invisibility, empty-box
  skip, tick/untick, idempotent tick, rule-10 year, overdue rules, sort order,
  next-lesson resolution including the holiday skip and the no-timetable case)
  and 3 HTTP tests walking pupil-sees → pupil-ticks, parent-sees-read-only, and
  the two 403s.
- **Deferred to E3b, deliberately:** homework attachments (needs Media
  plumbing), exam dates merged into the same list, follow-up questions and
  per-question points. The last of those is an assessment engine, not homework.
- **Not verified locally:** same as §5ae — no MySQL here and SQLite cannot run
  the suite. Architecture tests green locally; DB tests gated on CI. **Browser
  walk on `test.akuru.edu.mv` still owed** for both §5ae and this entry.

## 5ag. Browser verification of E2a/E3a from the agent session (2026-09-05)

The definition of done says *walked in a browser*, and §5t exists because
CI-green slices shipped empty grids and invisible bundles. Most of that walk
turned out to be reachable from here after all. What was actually done:

- **The deployed test site is reachable by curl** (headless Chromium is not —
  the proxy closes external tunnels). `/portal/messages` returned 302 → login
  while `/portal/homework` returned 404 exactly like a nonsense URL, which
  established that **E2a was already deployed to test and E3a was not** —
  before either was assumed.
- **Logged in as the super-admin and fetched the real pages.** Login posts to
  `/{locale}/login` with an `identifier` field, not `login`. Results:
  `/en/portal/messages` → component `Portal/Messages/Index`, `threads: []`,
  `canCompose: false` (right: a super-admin has no student or children, so the
  directory is correctly empty); `/en/portal/messages/new` →
  `Portal/Messages/Create`, `recipients: []`; `/en/portal/home` carries the
  `messages` tile and section. **The deployed bundle hash is `app-DXUF5yYG.js`
  — byte-identical to the E2a build**, so §5t's invisible-bundle failure did
  not happen.
- **Mirror render for the branches real data could not reach.** The live page
  shell plus the deployed bundle, served from localhost, with props swapped for
  fabricated rows; headless Chromium then probed the DOM. This is the same
  harness used for the §5w header bug. Verified, all with **zero page errors**:
  - Inbox empty state and populated state — unread badge renders `2`, thread
    links resolve, "With <name>" line renders.
  - Compose — the select lists recipients with their child context
    ("Fatimat Ali — Aisha Ali").
  - Thread, all three reply policies: normal reply form; **`author_only` shows
    "Reply — goes to the sender only"**; `none` replaces the form with
    "Replies are turned off for this message."
  - **E3a homework against the local (not yet deployed) bundle**: overdue item
    styled red, done item struck through with its checkbox checked, undated
    item shown as "No due date" and *not* red.
  - **The parent-cannot-tick affordance**: given a child with homework and
    `canTick` listing only the viewer's own student id, the child's section
    renders the item with **zero checkboxes**. This is the §5ac lesson — in
    #187 the action was guarded and the button was left on screen.
- **A silent-failure trap worth recording:** loading the wrong Vite entry
  renders a blank page with **no console error at all**. `resources/js/app.jsx`
  → the ~800 kB bundle is the React app; `resources/js/app.js` → a ~44 kB
  second entry. Read `public/build/manifest.json`, never `ls -t`.
- **What this does NOT cover, and still needs a human:** the family → teacher
  path end to end with real seeded data (a student on a class roster with a
  timetable, a parent account, an actual send and reply), and E3a on the
  deployed server at all — **E3a needs `scripts/pull-deploy-test.sh` run**,
  which requires the `akuruedu` cPanel account and cannot be done from an agent
  session.
- **Branch protection on `main` is permanently operator-only.** Retried today;
  the response is now explicit — *"Write access to this GitHub API path is not
  permitted through this proxy"*. This is not a token-scope problem that might
  clear later; no agent session can ever set it. **ADR-027's "required CI check
  before merge" is therefore a convention agents honour voluntarily, not an
  enforced gate, until an operator applies it in the GitHub UI.**

## 5ah. E7 dual-identity landing defect (2026-09-08)

- **The plan's description was overstated and is corrected here.**
  EDUPAGE_FEATURES_PLAN says a teacher-parent "can never reach the parent
  home". They can: the AppShell nav carries an unconditional `/portal/home`
  link and `PortalHomeController` has no role gate. The real defect is
  narrower and still worth fixing — `/dashboard` is the URL whose entire job is
  role-based landing, and for a dual-identity account **the order of an
  `elseif` chain, not any stated rule, decided the answer**; nothing on the
  landing page said the other identity existed. Calling it "locked out" would
  have justified a bigger change than the evidence supports.
- **The fix is not a different order.** Flipping `isParent()` above
  `isTeacher()` breaks the same case the other way — landing a teacher on their
  child's attendance instead of the register they have to fill. Staff still
  wins the landing, because that is the job the person signed in to do. What
  changed is that the displaced identity stops being invisible.
- **New `ResolveDashboardLandingAction`** (Portal) holds the precedence as a
  `match` with the rule written down, and returns the alternate identity
  alongside it. It takes **role names, not a User** — Portal may not import
  `Identity\Models` (rule 3), and a pure function of an array is testable
  without a database, which is how the dead code below was caught.
- **Dead code found and deleted before it shipped.** The first draft also
  offered a "Staff view" alternate to a family-first landing. Running the
  action standalone over every role combination showed that branch can never
  fire: every staff role outranks student and parent, so a `portal_home`
  landing means the person holds no staff role at all. A test now asserts the
  reverse case cannot arise, so nobody re-adds it.
- **`auth.alternate` is a shared Inertia prop**, so the link appears on every
  Inertia page rather than only the landing. It costs no query — Spatie already
  has the roles in memory — and is `null` for everyone with a single identity,
  which is almost everyone.
- **Rendered as a bordered pill, not a 41st nav link.** KNOWN_ISSUES #11 says
  the AppShell nav is unusable as navigation; adding another entry to a flat
  40-link list would have satisfied the ticket without helping anyone.
- **Verified in a browser** via the §5ag mirror harness: with the prop present
  the pill renders as "Family view → /portal/home"; with it null the pill is
  absent and the page keeps only its one ordinary nav link. Zero page errors.
- **A related gap, found while doing this, deliberately not fixed here:**
  `AttachGuardianAction` does not assign the `parent` role, so a guardian can
  have children without holding the role this feature keys on. The alternate is
  role-based because `/dashboard` routes by role and the two must agree —
  making it query guardian children instead would put two queries on every
  Inertia response. **Whether attaching a guardian should grant the role is a
  data-model question for the owner**, not a routing one.
- Still open in E7: the account switcher itself (`linked_accounts`, verified
  linking, re-auth for sensitive actions). This slice was only the defect the
  plan said should not wait for it.

## 5ai. E2b-a — staff address a class (2026-09-08)

- **The reason this came next:** E2a's reply policy defaults a thread to
  `author_only` above five recipients — written specifically so one message to
  every parent does not become a message *from* every parent. That code was
  **unreachable**. The only caller was the portal compose form, which passes
  exactly one recipient, so `policyFor()` could never return anything but
  `'all'` in production. Class fan-out is the case it exists for.
- **New `ListClassesTaughtByUserAction`** (Academics) — the mirror of E2a's
  `ListTeacherContactsForStudentAction`. Keeping both narrow means neither side
  gets a directory of the whole school: families reach the teachers of their own
  child, staff reach the classes they actually teach.
  - The **two id spaces** bite again and are handled again:
    `classes.class_teacher_id` holds a `users.id`, `timetables.teacher_id` holds
    a `teachers.id`. A test covers the class-teacher-only case, which the
    timetable query alone would miss.
- **New `ListFamilyUserIdsForStudentsAction`** (People) — the login accounts
  behind a roster. It lives in People because students and guardians are
  People's data; Academics knows who is on a roster, not who may speak for them.
  Only accounts that **exist** are returned: a broadcast claiming 30 recipients
  and delivering to 11 is worse than one that says 11.
- **Audience is explicit — parents, students, or both.** Conflating them means
  a "bring your PE kit" reminder lands in every parent's inbox. Default is
  parents.
- **The compose screen states the size before sending, and what the size does:**
  "Goes to 18 accounts. Replies come back to you only, not to the whole class."
  Discovering that rule after sending is exactly the surprise the policy exists
  to prevent.
- **`StartClassMessageThreadAction` deliberately passes no `reply_policy`** — it
  lets the >5 default fire. Overriding it there would quietly reintroduce
  reply-all on a broadcast to a whole class.
- Thread is filed against the class via the morph alias `class_room` (ADR-005);
  a test asserts the stored value is an alias, never an FQCN.
- **New permission `messages.broadcast`**, granted to teacher and headmaster in
  `RoleSeeder` (admin and super_admin hold everything). Authorisation is
  enforced twice: the permission gates the route, the taught-classes rule gates
  *which* class, and the latter lives inside the action so it is not restated
  per caller.
- **Rule 3 held.** The controller's private helpers return a thread **id**, not
  a model, so Portal still never names `Notifications\Models`. Arch tests
  confirm the baseline did not grow.
- **Tests:** 12 action tests (directory scoping both ways, class-teacher-only
  membership, per-audience delivery, morph alias, both sides of the reply-policy
  threshold with delivered-recipient assertions, unreachable-class refusals) and
  4 HTTP tests walking teacher → 6 families → parent replies to the teacher
  alone, plus the permission and hand-posted-class refusals.
- **Browser-verified** with the §5ag mirror harness, including the interactive
  branches: switching between person and class routes, and the reach notice
  tracking the class and audience live — "Goes to 3 accounts" → "Goes to 18
  accounts. Replies come back to you only." Zero page errors.
- **A shared test helper was extracted** (`tests/Support/MessagingTestHelpers.php`)
  rather than declaring `seedClassWithFamilies()` in one test file — a function
  declared in a test file only exists if that file happens to have been loaded,
  so the HTTP walk would have failed when run alone.
- Still open in E2b: saved recipient groups, message polls, and blocking.

## 5aj. E4 — the noticeboard reaches families (2026-09-10)

- **The plan said "ALREADY BUILT". It is not, and the audit is worth recording
  because this is the fourth time.** EDUPAGE_FEATURES_PLAN claims announcements
  are "surfaced to families via `EnhancedDashboardController::getRecentAnnouncements()`".
  That method **returns the integer `0`** — it is a stub. And families land on
  `/portal/home`, which never touches that controller; nothing redirects anyone
  to `/enhanced-dashboard` at all. **No family has ever seen an announcement.**
- **`target_audience` and `target_classes` have existed since 2025-09** and the
  admin form writes both. Grepping for readers outside the model found only the
  controller that *writes* them. The targeting was collected and never applied.
- **New `ListAnnouncementsForUserAction`** (Academics) is the reader. It honours
  publish window, expiry, published flag, audience and class targeting.
- **The dangerous inversion, handled explicitly:** a blank `target_audience` or
  `target_classes` means **the whole school, not nobody**. Treating a blank as a
  filter would have silently hidden every row written before targeting was read
  by anything. Two tests pin this.
- **A notice expiring today is still today's news** — the expiry comparison is
  inclusive, with a test for the boundary.
- **The tile badge counts only urgent and high priority.** There is no per-user
  read state, so a badge counting everything would sit there forever and stop
  meaning anything. An urgent notice expires and the badge goes with it. Adding
  `announcement_reads` for a true unread badge is a reasonable follow-up but was
  not needed to make the feature useful.
- **Trilingual with a real fallback:** the reader picks the Dhivehi or Arabic
  title and content when the author supplied one, and falls back to English when
  the field is blank. A blank translation must not blank the notice — tested
  both ways.
- **Urgent sorts first in the UI.** A notice marked urgent that sits below three
  general ones has been marked urgent for nothing.
- **Rule 3 held.** `ComposePortalHomeAction` gained a `roleNames` parameter
  rather than reaching for the user model; `PortalHomeController` passes them
  in. Arch tests confirm the baseline did not grow.
- **Tests:** 12 action tests (audience matching in both directions, the blank
  = everyone inversion, publish/expiry boundaries, class targeting for pupils
  and for the teacher of that class, badge arithmetic, both translation paths)
  and 5 HTTP tests including **the CSV export not leaking what the page hides**.
- **Browser-verified** with the §5ag mirror harness: urgent notice renders above
  the newer general one, the emergency type and expiry line render, the CSV link
  is hidden when there is nothing to export, and the empty state reads honestly.
  Zero page errors.
- `makeNotice()` went into `tests/Support/AcademicsTestHelpers.php` — the same
  shared-helper lesson as §5ai.
- **Not fixed here, flagged instead:** `EnhancedDashboardController` is full of
  stub methods returning `0` (`getRecentAnnouncements` is one of several). The
  route `/enhanced-dashboard` is reachable but nothing links to it. That
  controller wants an audit of its own — it is the kind of file that makes a
  plan claim a feature exists.

## 5ak. Enhanced dashboard removed (2026-09-10)

- **Follow-up to §5aj, where this file was flagged.** It is the controller that
  made EDUPAGE_FEATURES_PLAN believe E4 was already built.
- **What it was:** a third dashboard beside `portal.home` (families) and
  `portal.overview` (staff), 335 lines plus a 181-line Blade view. **Nothing in
  any nav linked to it** — the only references anywhere were its own route, an
  entry in `TrackUserActivity`'s skip list, and two tests asserting the route
  name resolved. It was reachable only by typing the URL.
- **Roughly thirty of its methods returned bare literals**, and several of those
  literals were **invented figures**: `getMonthlyRevenue()` → `'$5,250'`,
  `getRevenueGrowth()` → `'+12%'`, `getPendingPayments()` → `3`,
  `getStorageUsage()` → `'2.5 GB / 10 GB'`, `getSystemUptime()` → `'99.9%'`,
  `getUserGrowth()` → `'+15%'`, `getProfileCompletion()` → always `100`, and
  `getSystemHealth()` reporting `'healthy'` unconditionally.
- **Stated precisely, because the distinction matters:** the current Blade view
  rendered only two of those — the database status and the storage figure. The
  fabricated revenue was **computed into the payload but not printed**. So it
  was one template edit away from showing a Maldivian school invented revenue
  in **dollars**, not actively showing it. Worth removing on that basis alone;
  not worth overstating.
- **Deleting it lost nothing real.** Every genuine number it computed —
  `pending_applications`, `unread_inquiries`, user and content counts — is
  already produced by `AnalyticsService` with the identical queries, feeding
  `analytics.index`. Checked before deleting, not assumed.
- **The architecture baseline shrank by 9 entries** — 1 under rule 1 and 8 under
  rule 2 — because the file imported `Admissions`, `Courses`, `Identity`,
  `Settings` and four `Website` models directly. The arch tests failed until the
  baselines were updated, which is the ratchet working as designed: a fixed
  violator must leave the baseline, not linger in it.
- **A test pins the removal** (`NoFabricatedDashboardTest`): the route name must
  not resolve, the URL must 404, and neither file may come back. A dashboard
  that makes up financial figures should not return by accident.
- Rule 11 (single sources of truth) is the underlying argument: two working
  dashboards already cover families and staff, and a third that duplicated them
  with placeholder data was never going to be the one that got maintained.

## 5al. E10a — lateness, aggregated (2026-09-10)

- **Audit first, and this time the plan was mostly right in the other
  direction:** attendance is genuinely well built. Recording, reporting,
  chronic-absence detection, unexcused listing, CSV export and a configurable
  `attendance_chronic_threshold` all ship and work. E10's list of gaps is
  shorter than it reads.
- **The real gap is lateness.** `class_attendance.minutes_late` has been written
  since 2026-08 and **never aggregated anywhere**. `chronic()` counts full
  absences only, so a pupil ten minutes late every day for a term appears in no
  report at all.
- **New `ListTardySummaryAction`** — per pupil: late marks, total minutes,
  early departures, absent days over the same window, and the tardy-derived
  figure.
- **New setting `attendance_tardies_per_absence`, defaulting to 0 = off.** A
  school that has not chosen a number must not have one applied to its reported
  attendance behind its back. EduPage's documented example is 3; the default
  here is still off.
- **The design decision worth challenging if you disagree: the rule is reported,
  not applied.** `chronic()` returns exactly what it returned before. Folding
  tardy-derived absences into it would move a number the school already reads
  and reports, with nothing on screen to explain why it moved. The combined
  figure appears as its own `effective_absences` column, and the panel says so
  in words. A test asserts `chronic()` is unchanged even under the most
  aggressive possible rule (1 late = 1 absence).
- **Integer division on purpose:** two lates under a three-per-rule are not
  two-thirds of an absence, they are not yet an absence. Tested.
- **Both halves of a row cover the same date window** — lateness and absences
  are filtered identically, or the two numbers on one line would describe
  different periods. Tested.
- **Surfaced on the existing report page** rather than a new screen: a fourth
  panel plus a `kind=tardies` CSV export, alongside the chronic and unexcused
  exports that were already there.
- **Tests:** 11 covering the sums, early departures separated from lateness,
  rule off by default, conversion once set, no rounding up, `chronic()` left
  untouched, window scoping on both halves, class scoping, sort order, and a
  null `minutes_late` treated as zero rather than failing.
- Still open in E10: custom absence types (the status enum would have to become
  data), a rounding policy for part-lessons, and the integrity story for
  parent-submitted absence notes that EduPage's docs address explicitly.

## 5am. E22a — notifications reach a human, and messages emit them (2026-09-10)

- **The finding is the biggest of the session.** `user_notifications` has been
  written for months by **five features** — `NotifyUnfilledRegistersAction`,
  `NotifyRequestDecisionAction`, `NotifySubstituteAssignedAction`,
  `NotifyExpiringDocumentsAction`, `NotifyAdminDailyDigestAction` — and **no
  human could read a single one**. `/notifications` returns JSON that nothing
  calls, and `resources/views/notifications/index.blade.php` was rendered by no
  route at all. Every notification those features have ever raised went into a
  table and stopped there.
- **The plan calls E22 "ALREADY BUILT".** The models and the writers are real;
  the reading half never existed. Fifth time the plan and the code disagreed.
- **The mirror-image gap in my own recent work:** E2a and E2b shipped delivery
  with no announcement. A teacher could broadcast to thirty families and none
  of them would be told — the only hint was the portal-home unread badge, which
  requires visiting first. A notice about tomorrow is no use found next week.
- **What shipped:** `ListUserNotificationsAction` and
  `MarkUserNotificationsReadAction`, a `Portal/Notifications` page, an **unread
  count in the AppShell chrome** so notifications are reachable from any screen,
  and `NotifyMessageRecipientsAction` wired into thread start and reply.
- **The announcement can never be wider than the delivery.** Both thread actions
  already compute their exact audience — including the `author_only` case — and
  the notifier is handed that list rather than re-deriving it. A test asserts a
  parent's reply under `author_only` notifies the teacher and no other family;
  re-deriving would have leaked the reply to five households.
- **In-app only, deliberately.** SMS costs real money per message and is gated
  on `APP_ENV=production` plus an explicit `SMS_LIVE` (#86). A class broadcast is
  exactly the shape of feature that turns a wiring mistake into a bill, so
  whether messages send SMS stays an **owner decision**, not a default.
- **`read_at` is the source of truth**, not `status` — the model's own `unread()`
  scope uses it and `status` carries delivery state. Marking read is scoped by
  user id **inside the query**, so a hand-posted id belonging to someone else
  matches nothing rather than being found and then rejected. Tested.
- **The existing `/notifications` JSON route is untouched**, so any future API
  caller keeps working; the new page sits at `/portal/notifications`.
- **The orphan Blade view was deleted** — same reasoning as §5ak. It called
  `/api/notifications*` endpoints and was rendered by nothing; leaving it is how
  the next reader concludes a notification centre exists.
- **Notifications carry an href where the writer supplies one**, and render as
  plain text where none exists rather than as a dead link. Message notifications
  deep-link to their thread.
- **Tests:** 11 action tests (ordering, cross-user isolation both for listing and
  for the count, mark-one vs mark-all, the hand-posted-id case, message
  dispatch, author not told about their own message, class fan-out, the
  author-only leak case, preview truncation) and 6 HTTP tests.
- **Browser-verified:** the chrome shows "Alerts 1" with a badge and plain
  "Alerts" at zero; the unread row deep-links to its thread while the read row
  with no href stays plain text; "Mark read" appears only on unread rows and
  "Mark all read" only when something is unread. Zero page errors.
- `seedFamilyAndTeacher()` moved to `tests/Support/MessagingTestHelpers.php` —
  third time this lesson has come up, now applied without being caught by CI.
- **Still open in E22:** per-category channel preferences ("which categories
  reach me, on which channel"), and the daily digest that EduPage pairs with a
  "what is due tomorrow" list — it would compose E1's next-day strip with E3's
  homework list and is the most parent-friendly idea in their design.

## 5an. E22b — the family evening digest (2026-09-10)

- **A correction to something I said earlier in this session.** I claimed the
  project has "no scheduler, so nothing recurring runs reliably", and used it as
  an argument for moving off shared hosting. **That was wrong.** I grepped
  `app/Console/Kernel.php`, which does not exist in Laravel 11. The schedule
  lives in `routes/console.php` and is comprehensive: BML reconciliation every
  ten minutes, expected-register generation, register locking, invoice overdue
  and reminders, HR document expiry, daily content, prayer times, the staff
  nudges — **and an `akuru:scheduler-heartbeat` command that exists precisely to
  verify cron is running.** Whether cron fires on the host is still a fair
  question; whether the app defines scheduled work is not.
- **This also makes §5am worse, and worth restating:** those five notification
  writers are scheduled daily. If cron has been running, the system has been
  producing notifications every single day that no human could open.
- **What shipped:** `NotifyFamilyDailyDigestAction`, a
  `family:notify-daily-digest` command, and a schedule entry at **19:00
  school-local** — deliberately later than the 17:00/17:30 staff nudges, because
  tomorrow's registers and homework should be settled before families are told
  what is coming.
- **It composes; it computes nothing.** E1's `ListDayTimetableForStudentAction`,
  E3a's `ListHomeworkForStudentAction`, E4's `ListAnnouncementsForUserAction`,
  delivered through E22a's notification centre. Because every number comes from
  the reader that backs the corresponding page, the digest cannot disagree with
  what the parent sees when they follow it.
- **Off by default**, as `family_daily_digest` alongside the two existing
  toggles. A school opts in before every family starts getting a nightly
  message.
- **Silence is a feature.** A family with no lessons, no homework and no notices
  gets nothing — a digest that says "nothing" every evening teaches people to
  ignore it, and the next one that matters goes unread too.
- **The skip does not burn the daily slot.** The once-per-day cache key is
  released when a family is skipped, so a digest still lands if something
  appears later the same day. That is the bug this pattern invites and there is
  a test for it.
- **The Spatie morph value is derived, not hardcoded** — `getMorphClass()` via
  the auth-config model, matching `AdminUserController`. Writing `'user'`
  literally would work today and break the day the alias changes.
- **Tests:** 9 covering the setting gate, pupil and guardian delivery, the
  nothing-to-say skip, homework-only content, once-per-day, the skipped-slot
  case, non-family accounts ignored, and the command itself.
- Still open in E22: per-category channel preferences, and SMS as a digest
  channel — which stays an owner decision for the cost reason in §5am.

## 5ao. E2b-b — a thread can carry a question (2026-09-10)

- **"Will your child attend the trip?" is what schools actually want from
  messaging.** The plan puts simple polls here rather than in E6's form builder,
  and that is right: a thread already has an audience, a delivery mechanism and
  a reply policy, so a poll is a question attached to one — not a second system.
- **Tallies go to the author only.** This was the genuinely close call. On a
  class of twelve an aggregate is close to naming people — *"1 of 12 said no"*
  identifies someone. The teacher needs the count to act on it; a parent does
  not. A recipient sees their own answer and nothing else, and a test asserts
  the recipient payload carries no `tallies` key at all rather than a zeroed one.
- **Options are frozen once asked.** There is deliberately no update path:
  rewording an option after answers exist would silently change what people
  answered, and a response stores the *index* into that list.
- **Answering again replaces, never adds** — a parent who mis-taps can correct
  it and the tally still means what it says. Enforced by a unique constraint,
  not only by the action.
- **Two options minimum, ten maximum.** One option is not a choice, and an
  unanswerable question sent to thirty families is worse than no question.
- **One poll per thread**, unique-constrained: two questions in one conversation
  is how answers get attributed to the wrong one.
- Additive migration (rule 9); both tables are time-scoped so both carry
  `academic_year_id` (rule 10), taken from **when the question was asked** rather
  than whichever year is current when somebody answers.
- The compose form offers a question only for class messages — a poll of one is
  just a message — and a blank question box attaches nothing.
- **Tests:** 12, including both refusal branches on option count, out-of-range
  choice, closed poll, non-participant, replace-not-duplicate, and the
  author-vs-recipient payload difference.
- **Known limitation, deliberately deferred:** one answer per **user**, not per
  **child**. A parent with two children in the same class answers once. Doing it
  per child means resolving which of a user's children are in the thread's
  context class, which is real work and belongs in its own slice.
- **Process note:** this slice was committed without its STATUS entry and the
  omission was caught afterwards, not by CI — the definition of done is not
  machine-checked, which is exactly why it is written down.

## 5ap. E22c — which categories reach me (2026-09-10)

- **Why this closed E22 rather than opening something new:** E22b's digest
  fires **nightly to every family** once enabled, and E22a put a notification on
  every message. The only control was the global on/off switch, so a parent who
  wanted trip notices but not a nightly summary had to mute everything — which
  kills the notices that mattered.
- **A bug in my own E22b, fixed here:** the family digest was written with
  `category => 'message'`, which conflated the nightly summary with a teacher
  writing to you. They are different things and must be separately mutable, so
  the digest now has its own `digest` category. A test pins it — muting the
  digest must not mute messages.
- **Absence of a row means opted in.** Storing only the choices people actually
  make keeps the table small and, more importantly, means a category added later
  reaches everyone by default rather than silently reaching nobody. That is the
  same inversion E4's blank-audience rule had to avoid, and it fails in the
  direction where nobody notices.
- **Enforced at the single choke point.** Every writer in the app goes through
  `SendUserNotificationAction`, so the check lives there once instead of six
  call sites each remembering. Its return type became nullable; no caller uses
  the return value, which was verified rather than assumed.
- **A category with no toggle is always delivered.** Nobody has opted out of
  something they were never offered, and dropping it would make an
  un-configurable notification vanish. A hand-posted category is ignored rather
  than stored, so it cannot silently suppress something nobody can re-enable.
- **Muting a notification never loses the message.** A teacher who mutes message
  notifications still gets the message in their inbox — the notification is the
  announcement, not the delivery. Tested.
- **No `academic_year_id`** — rule 10 covers things that happen in time, and a
  preference is standing state.
- **Tests:** 9 covering the default-on behaviour, delivery and suppression,
  digest-vs-message separation, cross-user isolation, un-toggleable categories,
  the hand-posted category, opting back in without duplicate rows, and the
  message-still-delivered case.
- Still open in E22: **channels** — every preference here is in-app, because SMS
  remains an owner decision for the cost reason in §5am.

## 5aq. E6a — sign-up sheets and surveys (2026-09-10)

- **First new domain in a long time: `Domains/Forms`.** A poll (E2b-b) answers
  one question inside a conversation; a form is what a school actually sends for
  a trip — several questions, a window, and a results table somebody works from.
- **The new domain was added to `ViolationScanner::DOMAINS`.** Without that a
  new domain is **invisible to every architecture rule** — boundaries, model
  imports, the lot. Easy to miss, and the tests would have stayed green while
  enforcing nothing.
- **Audience matching was deduplicated, not copied.** E4's noticeboard and E6's
  forms ask the same question — "is this aimed at me?" — so the logic moved into
  Academics' new `ResolveAudienceContextAction` and E4 was refactored onto it.
  Two copies is how the noticeboard and the sign-up sheet end up disagreeing
  about who is in Grade 5. The blank-means-everyone rule moved with it.
- **Questions freeze once anyone answers.** The title stays editable; the field
  list does not, because rewording or reordering questions would silently change
  what past answers meant. Each field carries a **stable key** so fixing a typo
  in a label does not orphan the answers stored under it.
- **Anonymous means no person id at all**, not a hidden one. The consequence is
  that an anonymous form cannot say whether you have answered, and cannot stop a
  second submission. Both are **asserted in tests** rather than left as
  surprises — the promise is only worth making if the schema can keep it.
- **Answers are validated against the form's own frozen field list**, so a
  hand-posted key or an option that was never offered cannot land in the results
  table where somebody would act on it. Tested for select and multi-select.
- **The respondent column is absent on anonymous results, not blank.** A column
  of dashes invites someone to go looking for the answer in the database.
- **`file` is deliberately not a field type in v1.** Uploads need Media plumbing
  and a retention answer; a half-built upload on a permission slip is worse than
  a text box. It arrives with the slice that gives homework attachments a home.
- New permission `forms.manage` (teacher and headmaster; admin and super_admin
  hold everything). Both tables carry `academic_year_id` — rule 10 for responses,
  and because "the trip sign-up" means a different sheet each year.
- **Tests:** 17 covering validation of the form itself, audience scoping,
  answer correction, required fields, discarding un-offered options on both
  choice types, closed and not-for-you refusals, all three anonymity
  consequences, question freezing, and both results shapes.
- Still open in E6: **E6b parent confirmation** — a pupil's answer staying
  unconfirmed until a guardian confirms from their own account, which must
  preserve the rule that acting *as* a pupil never grants guardian confirmation
  (it interacts with E7's switcher). And **E6c fees**, which must raise invoices
  through existing Commerce actions (rule 11) with money rule 12 still applying.

## 5ar. E6b — a pupil's answer waits for a guardian (2026-09-10)

- **Why the distinction is not pedantry:** a child ticking "yes, I am going on
  the trip" is not the same fact as their parent agreeing to it. A results table
  that conflates the two sends children on coaches their families never
  approved.
- **The rule that gives the feature its point, and the one easiest to lose:**
  **acting as the pupil never grants the confirmation.** EduPage blocks
  confirming while signed in as the child, and so does this. If the same
  identity can both answer and confirm, the confirmation records nothing.
- **Enforced on identity, not on session**, so **E7's account switcher cannot
  route around it**: the confirming user must be a *different* user who is a
  registered guardian of that pupil, checked through People's existing
  `GuardianCanAccessStudentAction`. A switcher that changes which identity you
  are acting as still cannot make you your own guardian.
- **A bug caught by writing the test, not by CI:** re-answering after
  confirmation left `confirmed_at` set, carrying a guardian's approval across to
  an answer they never saw. Submission now withdraws any confirmation. That is
  the whole failure mode of the feature and it would have shipped silently.
- **Anonymous and requires-confirmation are refused together.** An anonymous
  answer has nobody to confirm for, and allowing the pair would create forms
  that quietly never become confirmable.
- **"Unconfirmed" is made into a task, not a silent state.** The guardian gets a
  pending queue showing the child's actual answers; the pupil is told their
  answer is *waiting for a parent to confirm* rather than seeing it marked
  answered. Without both halves the parent never learns there is anything to do.
- **Staff see the two apart:** the results table gains a Confirmed column and a
  confirmed count, only on forms that asked for it. Acting on unconfirmed
  answers is the mistake this prevents, so they are never presented as
  equivalent.
- **Nobody is ever recorded as confirmed with nobody accountable** —
  `confirmed_by_user_id` is stored alongside the timestamp.
- **Additive migration (rule 9):** both columns nullable, every existing form
  behaves exactly as before.
- **Tests:** 12 covering the anonymous conflict, the unconfirmed default, both
  the pupil-side and guardian-side surfacing, successful confirmation,
  **the pupil-confirming-themselves refusal**, another family's guardian, an
  unrelated account, a form that never asked, the staff view before and after,
  the re-answer withdrawal, and a guardian with no children.
- Still open in E6: **E6c fees** — a sign-up with a cost must raise invoices
  through existing Commerce actions (rule 11), with money rule 12 intact:
  access depends on the BML **webhook**, never the return URL.

## 5as. E6c — a sign-up that costs money (2026-09-10)

- **I asked for a decision on the semantics and was told "Next", so these are my
  calls, stated plainly and cheap to change.** The money rules themselves were
  never in doubt; the product questions underneath them were.
- **Money rule 12 holds by construction.** Nothing in Forms confirms a payment.
  The invoice is raised through Finance and paid through the portal's **existing**
  BML flow, so access still follows the webhook and never a return URL. E6c does
  not build a second checkout.
- **Rule 11 held:** the new `RaiseAdHocInvoiceAction` lives in **Finance**, not
  Forms. A sign-up sheet that grew its own money tables would be a second
  invoice system. `meta.source` records what raised it, so an invoice is always
  traceable rather than appearing from nowhere on a family's statement.
- **Paid state is never copied onto the response.** It is read from the invoice.
  Two records of whether a family has paid is one more than a school can
  reconcile — a test changes the invoice and asserts the form view follows.
- **Signing up is not gated on paying.** The answer is recorded and an invoice
  is raised; whether an unpaid family has a seat is a school decision, not one
  the schema should make silently. The existing scheduled
  `invoices:send-reminders` already chases them, so nothing new was built for
  that either.
- **The genuinely ambiguous part, handled rather than guessed:** invoices are
  student-scoped, so a fee needs to know *which pupil*. A pupil answering for
  themselves is unambiguous. A guardian with exactly one child the form is
  aimed at is unambiguous — narrowing by the form's own class targeting is what
  makes the common case silent. A guardian with **several** must say which, and
  the named child is checked against their own children rather than trusted.
  Guessing is how the wrong family gets billed.
- **Raised once.** Re-answering does not bill a family twice, and the existing
  invoice is left alone rather than cancelled and re-made — a family may already
  be part-way through paying it.
- **The price freezes once anyone has been invoiced**, alongside the questions
  and the anonymity flag. Changing it would bill later families differently for
  the same trip.
- **Anonymous and a fee are refused together**, for the same reason as anonymous
  and confirmation: there is nobody to bill, and raising an invoice against a
  pupil the form promised not to identify would break the promise.
- **Tests:** 11 covering the anonymous refusal, single invoice with correct
  student and traceable meta, free forms raising nothing, no double billing,
  the unambiguous-guardian case, the **refuses-to-guess** case, the named-child
  case, a child that is not theirs, an account with no pupil, the frozen price,
  and paid state following the invoice.
- **Open questions I would still put to the owner**, none of which block what
  shipped: whether an unpaid sign-up should expire, whether a paid form should
  enforce a seat limit (the events module has one; forms do not), and whether a
  confirmed-but-unpaid answer needs its own treatment. All three are additive.

## 5at. E13a — a material a teacher can reuse (2026-09-10)

- **The finding that justified the slice:** `lesson_logs.materials` is a
  free-text JSON array, typed into the register as a comma-separated string.
  There is **no reusable materials table anywhere in the codebase**, so a teacher
  retypes "Textbook p.12, worksheet" every lesson, nothing is searchable, and
  nothing can be attached to homework. E13 was recorded as not built and that is
  correct — the first plan row in a while that survived the audit.
- **`teaching_materials` + `lesson_log_material`.** Title, optional body,
  optional subject, JSON tags, `created_by`. The pivot carries a unique
  constraint: attaching the same material twice to one lesson is the same fact.
- **No `academic_year_id` (rule 10 does not apply).** A material is standing
  content, not something that happens in time — the same worksheet is the same
  worksheet next year. The lesson it is attached to already carries the
  backbone, which is where the time-scoped fact lives.
- **The legacy free-text column is untouched (rule 9).** Existing registers keep
  their strings and keep displaying them; the structured picker sits beside the
  old field rather than replacing it. Migrating the old strings is a later
  slice's job and needs a human to decide which strings are the same material.
- **Owned by the author, visible to all staff.** A library one teacher can see
  is a notebook. Only the author can edit, so shared visibility never means
  somebody else rewriting your wording under your name.
- **The picker syncs rather than appends**, so unticking removes — the register
  records what the lesson actually *used*. That made two things load-bearing:
  the picker must show everything already attached whatever the subject filter
  says (a hidden attachment is an attachment silently deleted on the next save),
  and a bundle that posts no `material_ids` at all must not be read as "the
  teacher unticked everything" — §5t, `public/build` is committed, so an old
  bundle is a real client. Both have tests.
- **A material with no subject is offered in every lesson.** "Class rules
  handout" belongs everywhere, not nowhere.
- **`AttachMaterialsToLessonAction` reuses the register's own edit rule** — your
  own lessons unless you hold `registers.manage` — rather than inventing a
  second one. Without that, a locked or someone else's register could be edited
  sideways through the materials picker.
- **Reachable:** `Materials` added to the app nav, and `Manage materials` links
  out of the register. CSV export honours the filters, so a teacher exports what
  they were looking at.
- **Tests: 20** (12 action, 8 HTTP), covering tag normalisation, the
  no-title refusal, the author-only edit, search by title/body/subject/tag/mine,
  general materials appearing alongside subject ones, an attached material
  surviving every filter, sync-not-append, a dangling id ignored, the
  someone-else's-register refusal, the admin override, the legacy column left
  alone, cascade on delete, and the full HTTP walk including the 403, the CSV
  and the register picker round trip.
- **Run locally for the first time in this run of slices.** MariaDB was
  installed in the session container, so pest ran against MySQL rather than
  being handed to CI unseen. It **immediately caught a bug the review had
  missed**: both register-picker tests used `makeLessonLog()`'s default
  back-dated `2026-08-24`, which auto-locks, so the submit was refused with
  "This register is locked." and the attach never ran. One test failed honestly;
  the *other* passed for the wrong reason. Both now use today's date and assert
  `assertSessionHasNoErrors()` before the count. SQLite cannot substitute — the
  migrations are MySQL-specific (`SHOW INDEX`).
- **The full suite, also run locally, caught a second one:** the nav-IA guard
  counts `<Link href=` in `AppShell.jsx` and the `Materials` entry took it 86 →
  87. Bumped with a note rather than worked around — that guard exists to stop a
  nav *redesign* arriving unannounced, not to freeze the list.
- **Still owed:** the browser walk on `test.akuru.edu.mv`, which needs the
  deploy. Nineteen merged slices remain unexecuted there.

## 5au. E13b — a material goes home with the homework (2026-09-10)

- **Closes the loop E13a opened.** E13a records which materials a lesson
  *used*; that is not the same fact as which ones a pupil needs at home.
  "Whiteboard" and "mushaf" stay in the room, "Alphabet worksheet — print double
  sided" goes home. Showing a family everything the lesson touched would bury
  the one thing they need, which is why this is a choice and not a rename.
- **One flag on the existing pivot, not a second table (rule 11).** The homework
  lives on the lesson log, so its materials belong on the same link. A material
  can be both — used in the lesson *and* sent home — which a boolean expresses
  and two tables would not.
- **Additive and defaulted false (rule 9).** Every attachment E13a already
  created stays exactly what it was. Defaulting the other way would have
  published every register's materials to every family in one deploy.
- **Absent is not empty.** `public/build` is committed (§5t), so a bundle
  predating E13b is a real client and posts no homework key at all. That leaves
  the teacher's existing choice alone; an explicitly empty list un-sends. The
  two must not collapse into each other, and a test holds each apart.
- **Un-attaching also un-sends.** A family should never be handed a material the
  lesson no longer uses, so the flag follows the attachment rather than
  outliving it — enforced in the action, not only in the checkbox.
- **Drafts stay private.** The materials follow the homework text they belong
  to: only submitted or locked registers reach a family, unchanged from E3a.
- **This is the first time a teaching material becomes family-visible.** Until
  now the library was staff-only. Worth stating plainly because it is a
  visibility change, not just a feature: whatever a teacher ticks "send home"
  is readable by that class's pupils and their guardians, title and body both.
- **Tests: 7**, covering the chosen-only send, the send-nothing default, the
  follow-the-attachment rule, absent-vs-empty in both directions, the draft
  register, and an HTTP walk that posts from the register and then reads the
  result as the pupil.
- **Run locally against MySQL before pushing**, all green first time, plus the
  Academics, Portal and Architecture suites (227) to catch regressions in E13a
  and E3a.
- **Still owed:** the browser walk. Twenty merged slices are now unexecuted on
  `test.akuru.edu.mv`.

## 5av. E13c — files on a material (2026-09-10)

- **Finishes E13's v1 scope**, which read "title, body, **attachments**, subject,
  tags". A worksheet a teacher *describes* is not a worksheet a pupil can print.
- **Media owns the bytes; Academics owns the link (rules 3, 4, 11).** Files go
  through `StorePrivateMediaAction` and come back through
  `ReadPrivateMediaAction`. `teaching_material_files.media_file_id` is an
  **opaque handle**, deliberately not a foreign key into Media's table — a
  cascade there would let one domain's cleanup silently rewrite another's rows.
  Name, mime and size are copied at upload time so listing a material never
  reads Media's table.
- **Removing a file deletes the link, never the stored file.** Media owns its
  own lifecycle; a domain reaching across to delete another's bytes is how a
  file still referenced elsewhere disappears.
- **The authorisation is the slice.** These files are private and the download
  route is reachable by families, so `ServeMaterialFileAction` is the only place
  the rule exists and the controller is four lines. Two ways in and no others:
  register staff see any material file (matching E13a's staff-wide library), and
  a family sees a file **only where the material was sent home (E13b) on a
  submitted register for a class their pupil is actually on**.
- **Attaching a material to a lesson is deliberately not enough to download it.**
  E13b's distinction is enforced a second time here, so the download route
  cannot become a way around it. Same for drafts.
- **"Logged in" is not "entitled",** and there is a test that says so. Without
  that check the route would hand every uploaded worksheet to anyone with an
  account — the single most likely way this slice could have gone wrong.
- **Types and size are capped** at what a teacher actually hands out (PDF,
  Office, images, audio) and 20 MB — large enough for a scanned worksheet, small
  enough for cPanel. The mime list is enforced inside Media's action rather than
  restated per caller.
- **Tests: 16**, weighted towards refusals — someone else's material, a
  disallowed type, an oversized file, a removal by a non-author, an account with
  no pupil, an anonymous visitor, a pupil whose teacher did not send the
  material home, a pupil on a draft register, a pupil from another class
  entirely. The allow and refuse cases **share a seed**, so a refusal cannot
  pass for the wrong reason: flipping only the send-home flag flips 200 to 403.
- **Run locally against MySQL**, green first time, plus Academics, Portal,
  Courses and Architecture (314) and the morph-map guard.
- **Still owed:** the browser walk — and this slice wants one more than most,
  because a real upload through cPanel's PHP limits is not something a fake
  `UploadedFile` proves. Twenty-two merged slices are unexecuted on
  `test.akuru.edu.mv`.

## 5aw. E1b — a teacher's own home (2026-09-10)

- **The audit contradicted the plan for the seventh time, in the usual
  direction.** E1 is recorded as "~1–2 weeks, not started". In fact
  `ComposePortalHomeAction` already ships the whole family half: tiles with live
  badges and status lines, `nextSchoolDay`, prayer,
  `ListDayTimetableForStudentAction`. Its acceptance criteria are met. The only
  unbuilt part of E1 was the **teacher** half — which is exactly the open
  decision the plan said must be answered before starting.
- **Answered as "their own home, not the school's report".** A teacher had no
  home at all: `/dashboard` redirected them into `/academics/registers/today`, a
  task queue that answers "what do I owe" and nothing else. No glanceable view
  of their day, their unread messages, or a notice aimed at them.
- **`ComposeStaffOverviewAction` is deliberately left alone.** It is school-wide
  — every teacher's fill rate, every unfilled register — which is right for a
  head and noise for a class teacher. "One grid for everyone" would have had to
  be one or the other, so there are two composers and each is honest about who
  it serves.
- **One genuinely new read: `ListDayTimetableForTeacherAction`,** the mirror of
  the student one. Reading the day off `lesson_logs` instead would have been
  wrong — expected registers are generated in batches, so a day nobody had
  generated yet would report a free morning to someone teaching four periods.
- **Cover cuts both ways for a teacher, which it does not for a pupil.** A
  period of mine that somebody else is covering still appears, marked, because I
  need to know it is handled; a period somebody else owns that **I** am covering
  appears on my day, because a cover that does not show on the substitute's own
  timetable is how a class sits unattended. Only *assigned* cover counts — an
  open request would tell someone they are teaching a class nobody gave them.
- **"Next" scans forward, it is not "tomorrow".** A strip that says "nothing
  tomorrow" on a Thursday while staying silent about a full Sunday is worse than
  useless.
- **The badge counts what is owed, not what exists.** A count that never reaches
  zero stops being read. Scoped per teacher — `ListUnfilledRegistersAction`
  already took a teacher argument, so no new read was needed.
- **The landing changed, and that is the part that makes it real.** `/dashboard`
  now sends the `registers` kind to `portal.teacher`. Without it the slice would
  be CI-green and unreachable — the rehearsal lesson. **E7's precedence is
  untouched**: staff still outranks family, only the staff destination moved.
  Two E7 tests asserted the old URL; both were rewritten to guard the
  *precedence* rather than the address, which is what they were always for.
- **Tests: 11** — period ordering, a day not taught, a holiday named plainly,
  cover in both directions, unassigned cover ignored, the owed-only badge,
  next-teaching-day lookahead, a staff account with no teacher record getting a
  partial home rather than an error, the 403, and the landing itself.
- **Full suite run locally against MySQL: 950 tests, zero failures.**
- **Still owed:** the browser walk. Twenty-three merged slices are unexecuted on
  `test.akuru.edu.mv` — and this one changes where **every teacher lands**, so
  it is the slice most worth seeing before the next.

## 5ax. E11b — the school calendar families can see (2026-09-10)

- **Eighth plan contradiction, and the plan's own note was wrong twice over.**
  It says E11 is half-built because "`CalendarDay` covers only holiday/exam day
  types". The enum has had **five** types since it shipped — holiday, event,
  exam_day, closure, special_schedule — with full CRUD, a month grid, CSV
  export and trilingual titles. The staff calendar is done.
- **The real gap was underneath it.** `ListCalendarHolidaysAction` returns
  `holiday` and `closure` only, so a sports day, an exam week or a half-day
  timetable was entered by the office and **read by nobody**. Same defect as
  E4's noticeboard and E22's notifications: captured, never surfaced.
- **The trap I nearly walked into.** The obvious fix is to widen that action.
  It is used by **HR** — `AutoFillHolidayStaffAttendanceAction` and
  `RecordStaffAttendanceAction` read it to decide which days staff are not
  expected in. Widening it would have marked every teacher on holiday for a
  sports day. Two questions that happen to read one table are two actions, so
  E11b adds `ListPublicCalendarAction` and leaves the holiday read untouched.
  A test now pins that: the holiday read must still return holidays only.
- **An audience, not a wider whitelist.** `calendar_days` had no audience, so
  publishing every type would have pushed internal entries — a staff meeting, a
  note to the office — to every parent in one deploy. `is_public` is explicit,
  because a school meeting and a parents' evening are both `event` and only one
  is anybody's business outside the office.
- **Backfilled, not defaulted (rule 9).** `is_public` is true exactly where the
  portal already published the row, so **today's behaviour is preserved to the
  row** and nothing new appears until somebody ticks it. On save, closed days
  publish by default — a family that is not told the school is shut turns up at
  the gate — and everything else stays internal until chosen.
- **`notes` is never published.** It is the office's working field on a shared
  row; publishing the row must not publish the margin.
- **`no_school` is stated, not implied.** "special_schedule" tells a parent
  nothing about whether to send their child in.
- **The public website is unchanged.** `public.events.index` reads the holiday
  action, so the open internet still sees closures only — which is right: an
  internal parents' evening does not belong on a public page. The signed-in
  portal seeing more than the public site is the intended asymmetry.
- **The URL is kept, the page renamed.** `/portal/holidays` still works because
  families may have bookmarked it; the component is now `Portal/SchoolCalendar`
  and the nav says "School calendar", because "Holidays" became a lie.
- **Tests: 10 new, 1 existing updated.** Default-publish for closed days,
  default-private for everything else, all published types listed, `no_school`
  stated, notes withheld, upcoming/past split newest-first, **the HR read left
  alone**, the signed-in read, the anonymous refusal, and the admin save.
- **Two of my own test errors, caught locally.** `actingAs` persists for the
  rest of a test, so an anonymous assertion tacked onto a signed-in test is not
  anonymous and passes for free — it is now its own test, with a comment saying
  why. And the existing calendar fixtures straddle today, so the upcoming/past
  split moved one of them; that assertion now covers both buckets.
- **Full suite run locally against MySQL: 960 tests, zero failures.**
- **Still owed:** the browser walk. Twenty-five merged slices are unexecuted on
  `test.akuru.edu.mv`.

## 5ay. E10b — who is not in today (2026-09-10)

- **The question a school office asks at 08:30 and could not ask here.**
  Attendance is recorded per lesson; absence notes sit in a separate review
  queue. Answering "which children are missing, and which of those has nobody
  heard about" meant reading two screens and doing the join in your head.
- **The join is the feature.** An absence with a note is administration. An
  absence with **no** note is a telephone call, and it was the one thing neither
  existing screen made visible. The list sorts unexplained first — it is a call
  sheet, not a report — and the row is tinted, because a list that makes you
  read a column to find the urgent ones is a list you stop reading.
- **One child, however many periods.** Marks are per lesson, so a child absent
  for four periods was four rows. Collapsing that is the difference between a
  list you act on and a list you scroll; the periods are still listed.
- **A pending note counts as an explanation.** Somebody told the school; the
  office should not be telephoning them while it waits for a review. A
  **rejected** note does not — that is what rejecting it meant. Where both an
  approved and a pending note exist, the approved one is shown.
- **Excused is included, late is not.** An excused child is still a child who is
  not there, which is what the list is for. Late is a different problem and E10a
  already reports it.
- **No new tables.** Everything read already existed; this is the read that was
  missing. Same permission pair as the attendance screens it draws from, since
  it is that data rearranged.
- **Tests: 15**, mostly about the note semantics — the four-period collapse,
  unexplained flagged, pending counts, rejected does not, approved outranks
  pending, a note for another day does not count, a note for another child does
  not leak, present/late excluded, excused included, unexplained sorted first,
  the class and unexplained-only filters, the render, the 403, the CSV, today's
  default, and an empty day.
- **Three fixture faults caught locally, all mine.** `AttendanceSource` has no
  `manual` case; `class_attendance.marked_by` has no default; and the unique
  index is on **`period_key`**, not `period_id` — `RecordClassAttendanceAction`
  sets it and a raw fixture must too, or four periods collide at the default 0.
  Worth recording: writing that table directly bypasses an invariant the real
  writer maintains.
- **Full suite run locally against MySQL: 975 tests, zero failures.**
- **Still owed:** the browser walk. Twenty-six merged slices are unexecuted on
  `test.akuru.edu.mv`.

## 5az. Emergency contacts — who to ring (2026-09-10)

- **The sharpest audit finding of the session.** `emergency_contacts` shipped in
  `s11a_unified_student_schema` in August. `EmergencyContact` appeared in
  exactly two files — its own model and a relation on `Student`. **Zero**
  controllers, actions, routes, UI or tests. There was no way to enter an
  emergency contact and no way to see one.
- **It was worse than unused.** `StudentDirectoryController::show` already
  eager-loads `emergencyContacts` and then drops the relation before
  serialising, so the query has been running on every student page view since
  August and the answer reached nobody.
- **Found by asking a different question.** After eight slices of "the plan says
  X, the code says Y", I stopped reading the plan and grepped for domain models
  with no controller, action or JSX reference. `EmergencyContact` came back with
  zero of each. That check is cheap and worth repeating — it is the same defect
  family as E4, E22 and E11b, just further along: not merely unread, unwritten.
- **Priority means "ring first", so the list is ordered by it.** A list in
  insertion order is a list you have to think about while a child is hurt. A new
  contact defaults to priority 1 rather than 0, so the column keeps meaning what
  it says.
- **Name and phone are both required.** A contact you cannot ring is not a
  contact; an unnamed number tells whoever dials it nothing about who answers.
- **Every write is checked against the student it belongs to.** Editing or
  deleting by id through another child's page would rewrite the wrong family's
  details, so both actions verify ownership rather than trusting the route.
- **Surfaced where it is needed, not only where it is stored.** E10b hands the
  office a list of children nobody has heard from; this puts the number on that
  row, as a `tel:` link, with "+N more" so nobody assumes one contact is all
  there is. Where there is none it says **"No contact on file"** rather than
  leaving a blank the eye skips.
- **Rule 3 held across that seam.** Academics asks People through
  `ListEmergencyContactsAction::firstForStudents()`, arrays out — no
  `People\Models` import, no new baseline entry.
- **Tests: 12** — the record, both refusals, priority ordering, cross-student
  edit and delete refused, the bulk "who to ring" read including the `others`
  count and a student with no contact, the absence-list join in both directions,
  and the HTTP save/show/remove walk.
- **Full suite run locally against MySQL: 987 tests, zero failures.**
- **Still owed:** the browser walk. Twenty-seven merged slices are unexecuted on
  `test.akuru.edu.mv`. **This one deserves a data question too:** the table is
  empty, so the feature is correct and useless until somebody enters contacts.
  Whether they come from the existing guardian records, an import, or a term of
  data entry is an owner decision, not a code one.

## 5ba. Push notifications stop claiming to be delivered (2026-09-10)

- **A notification recorded as sent that was never sent.**
  `NotificationService::sendPushNotification()` wrote a log line — *"Push
  notification would be sent"* — and returned. The caller then called
  `markAsSent()`. **Every push in the system was recorded as delivered while
  nothing left the building.** That is worse than an unimplemented channel:
  nobody goes looking for a message the audit trail says arrived.
- **Everything needed already existed and was ignored.** The domain has
  `PushSenderInterface`, a `NullPushSender` returning `false`, and a container
  binding (rule 4, done properly). The legacy service bypassed all three.
- **Now routed through the contract.** `SendPushNotificationAction` resolves the
  user's active devices, sends through the bound sender, and reports
  `{devices, delivered}`. No devices → nothing delivered; the service throws and
  the existing catch marks the row **failed** with a reason, instead of sent.
- **Nothing registers a device**, so "no active device" is the normal path
  today. It now says so. `Device` has a table and a model and no route, action
  or controller anywhere — the same unused-table family as the emergency
  contacts above.
- **Device registration is deliberately NOT in this slice.** With the sender
  bound to null, a registration endpoint would collect tokens nothing can send
  to — the exact "captured, never used" defect the last several slices existed
  to fix. It belongs with a real provider binding and a client to register from.
- **One device failing is not the notification failing.** A person with a dead
  tablet and a working phone has been reached, and the count says so.
- **Tests: 8** — no device, all devices, an inactive device skipped, partial
  delivery counted as reached, another user's device excluded, and the three
  status outcomes: failed with no device, failed when every device refuses, sent
  only when one actually took it.
- **Full suite run locally against MySQL: 995 tests, zero failures.**
- **How it was found:** the same scan that found the emergency contacts —
  domain models with no controller, action or JSX reference. It also produced
  two false positives (`FeeStructureItem`, `LibraryItemAuthor`) because my first
  pass excluded every `Models/` file, hiding relations like
  `FeeStructure::items()`. Excluding only the model's own file fixed it. Worth
  recording so the check is re-run correctly rather than trusted blindly.

## 5bb. Password reset by email could not find staff-created accounts (2026-09-10)

- **The defect.** `OtpPasswordResetController` resolves a person by looking up
  `user_contacts`, not `users.email`. Accounts created through the People
  screens — **every teacher and student account made by staff** — have a
  `users.email` and no contact row, so the lookup found nothing. The flow
  deliberately does not reveal whether an account exists, so the person is told
  *"if that contact is registered, a code has been sent"* and simply never
  receives one. No error, no log, no support signal.
- **The fix already existed and was called by nothing.**
  `EnsureVerifiedEmailContactAction` does exactly this job. Only the public
  enrolment paths ever created contact rows, by hand, inline.
- **It also had a latent bug, found while making it live.**
  `user_contacts(type, value)` is **globally unique**, and the action used
  `firstOrCreate` on those two columns — so where the address already belonged
  to somebody else it returned *their* row and the caller would have believed it
  had ensured a contact for this user. That would point one person's password
  reset at another person's account. It now returns null instead, and leaves the
  other row alone: two accounts contesting an address needs a human.
- **Wired into the three account-creation paths** — `CreateUserAction` and the
  teacher and student controllers.
- **`identity:backfill-email-contacts`** for accounts that already exist,
  idempotent and additive (rule 9), with `--dry-run`. It **reports** contested
  addresses rather than resolving them, listing the accounts that still cannot
  reset by email.
- **Tests: 8**, including the end-to-end walk that is the actual point: backfill
  an existing account, post the reset form, and assert the flow now resolves it
  to the right user.
- **Two fixture faults of my own, both instructive.** `users.email` is itself
  unique, so my first "two accounts share an address" test was impossible to
  build — the conflict is only reachable the way it happens in practice, via a
  **secondary** contact row. And the reset route is `password.otp.send`, not the
  name I guessed. The first of those changed the test into a better one.
- **Full suite run locally against MySQL: 1003 tests, zero failures.**
- **How it was found:** scanning for Actions with **no caller anywhere**. Two
  came back. This was one; the other is `ResolveDefaultPrayerIslandAction`,
  which is a **real and unfixed** duplicate-logic problem — three call sites
  re-implement "default island, else fall back" with *different* fallbacks, and
  the dashboard's differs from the action's (first-active vs Malé). Prayer times
  for the wrong island are wrong times. **Next slice.**

## 5bc. One rule for which island's prayer times to show (2026-09-10)

- **Four implementations of one question, and the one written to be the answer
  was called by nothing.** `ResolveDefaultPrayerIslandAction` was the second
  uncalled Action the caller-scan found.
  - it: setting (validated) → Malé by `name_latin` → first active;
  - `ImportController::ensureDefaultIsland`: setting (validated) → Malé by its
    **Dhivehi** name `މާލެ` → first active, and it *writes* the setting;
  - `ComposeDashboardPrayerAction` and the public `PrayerTimesController`:
    setting **unvalidated** → `listIslands()->first()`.
- **Two consequences, both real.** `ListPrayerIslandsAction` orders by
  `atoll_latin` then name, so the readers' "first island" is the
  alphabetically-first *atoll* — never Malé. The Maldives is wide enough that
  another atoll's times are wrong by minutes, and for prayer times minutes are
  the whole point. And because neither reader validated the setting, an island
  that had been deleted or deactivated left `resolveForIsland()` holding a dead
  id: prayer times **silently blanked** on the dashboard and the public page
  instead of falling back.
- **The two Malé matchers were each half right.** The importer knew it as
  `މާލެ`, the action as `Malé` — so each found it only on datasets the other
  would have missed. The consolidated rule matches both.
- **Deactivated now counts as unusable, not just deleted.** An island switched
  off has no current times, so treating it as configured-and-fine was the same
  bug wearing a different hat.
- **All three readers now call the one action (rule 11)**, and the importer uses
  it to decide what to write rather than re-deriving it.
- **Tests: 9** — Malé over an earlier atoll, Malé by Dhivehi name, an explicit
  choice honoured, fallback on deleted and on deactivated, first-active when
  there is no Malé, inactive never chosen, null when there are no islands, and
  the dashboard agreeing with everything else.
- **A fixture fault worth noting:** `prayer_islands.category_id` is required —
  islands belong to a B&G timing category. The fixture creates one rather than
  pretending the column is optional.
- **Full suite run locally against MySQL: 1012 tests, zero failures.**
- **The caller-scan is now exhausted:** both Actions it found are fixed, and no
  domain model is unreferenced. The two cheap structural scans (models with no
  reader, Actions with no caller) have produced four real defects between them
  and are worth re-running after any large slice.

## 5bd. The known-issues UI backlog (2026-09-10)

Asked to "complete all coding", I worked `docs/KNOWN_ISSUES.md` rather than
inventing scope. Four entries closed, one half-closed, and the rest are
genuinely not coding.

- **#13 Shared Add-term form.** One `useForm` was shared by every year card, so
  typing a term name on one year filled the boxes on all of them and "Add term"
  never made clear which year it would hit. Each card now owns its form
  (`YearCard`). Empty years say "No terms yet" instead of showing nothing.
- **#19 Exam schedule defaults wander.** The term and class dropdowns listed
  **every year's** rows and defaulted to the first, so the form opened on
  "Extra / Term 2 / Arabic Beginners" while the table below showed Pilot Grade
  5 A. Both dropdowns are now scoped to the form's own selected year; the year
  defaults to the one being viewed, else the **active** one; and changing the
  year clears a term or class that no longer belongs to it.
- **#21 Report-card publish defaulted to Term 2 with Term 1 on screen.** Same
  family. Generate and publish now default to the term being viewed, else the
  active term.
- **#20 Cosmetics.** The create-year form carried a `description` key with
  nowhere to type it — the controller had validated it since the screen shipped
  — so the field exists now. Every date input on that screen is labelled. No
  `2025` copyright remains anywhere in `resources/`.
- **#8 Report card without a template — half fixed, and it was already done.**
  `resolveTemplate` raises a named validation error rather than blowing up. I
  checked before changing anything and left it alone. The queue half is
  operator config, not code.
- **Tests: 2 new**, pinning the payload the pages reason over — every term and
  class must carry its `academic_year_id` and `status`, or scoping the dropdowns
  by year is impossible in the first place. The page-level behaviour is React
  and is honestly not covered by these; it needs the browser walk.
- **Full suite run locally against MySQL: 1014 tests, zero failures.**

**What is left in KNOWN_ISSUES is not coding**, and should not be recorded as
if it were: staging staff login (#1, needs SSH), the unification staging gate
(#5), Deploy 3 / Track B (#9, marked "do not execute"), the AppShell nav IA
(#11, marked "do not implement until Accept / Reject"), and the report-card
queue worker (#8, operator config). Three smaller entries (#15 teacher grid
statuses, #16 taught-summary vs plan topic, #22 Blade counters) are judgement
calls about intended behaviour rather than defects, and I have not guessed at
them.

## 5be. SECURITY — the legacy /students and /teachers routes had no role guard (2026-09-10)

- **The hole.** `Route::resource('students', ...)` and
  `Route::resource('teachers', ...)` sat in the `['auth', 'trackActivity']`
  group with **no role or permission guard**, and neither controller carried an
  `abort_unless`. The modern `people.*` screens that duplicate them require
  `role:super_admin|admin|headmaster|supervisor`.
- **What any signed-in account could do** — a parent reading their child's
  portal, a pupil: list every student and teacher, open any record, edit one,
  **delete** one, and `POST /students` to create a student **and a `User`
  account with a password of their choosing**. That last one is privilege
  escalation, not just disclosure.
- **Fixed** by wrapping those routes in the same role middleware as the screens
  they duplicate. The legacy Blade screens themselves are untouched.
- **Tests: 8**, weighted to refusals — no role, parent, student, teacher, a
  parent creating an account, a parent deleting a student, anonymous — plus one
  that the four privileged roles still get in, so the guard cannot pass by
  locking everyone out.
- **How it was found:** a fourth structural scan — controllers with no
  authorization signal at all. It produced ~24 hits, and **almost all were false
  positives** because the guard lives in route-group middleware rather than the
  controller. Checking the enclosing group before believing any of them is what
  separated the two real hits from the noise; I had already published one bad
  scan today and did not want a second.
- **Full suite run locally against MySQL: 1022 tests, zero failures.**

### ⚠ Left deliberately unfixed, needs an owner decision

`Route::resource('quran-progress', QuranProgressController::class)` and
`POST /quran-progress/{student}/update` are in the **same unguarded group**, so
any signed-in account can write Quran progress for any pupil.

I did not fix it: **rule 7 freezes Hifz** — "namespace/route changes only; no
behaviour change" — and adding a guard changes who can reach it. CLAUDE.md says
to stop and ask when an instruction conflicts, and this one does. ADR-021 also
records that there are no live Hifz users, so the practical risk today is low.

**The one-line fix is the same as the one above.** Say the word and it ships.

## 5bf. The EduPage plan, corrected against the code (2026-09-10)

- **The plan has now been wrong 16 times across two audits**, always in the same
  direction: work recorded as missing that already shipped. Eight more rows were
  found wrong this session, each caught by checking the code before starting the
  slice rather than after.
- **Corrected inline with evidence**, and the stale "verified still missing"
  line is kept rather than deleted, marked out of date — the audit trail is
  worth more than a tidy document.
- **E1 is the sharpest example.** Filed as "~1–2 weeks, not started"; in fact
  the entire family half shipped long ago and met E1's own acceptance criteria.
  Only the teacher half was missing. **E13 was the one row that held.**
- **E11's note was wrong twice over.** It says `CalendarDay` "covers only
  holiday/exam day types"; the enum has had five cases since it shipped, with
  full CRUD, a month grid, CSV export and trilingual titles.
- **One dismissed "grep artefact" was real.** The 2026-09-04 audit discarded
  three false hits, one in `emergency_contacts`. That table is real, shipped in
  August, and had **never been written or read by anything**. Dismissing it cost
  a month of a school having no way to record who to ring for a hurt child.
- **Recorded how to audit it**, since reading a row and believing it has failed
  16 times: grep the model and table rather than the feature name; check for a
  **reader**, not just a writer, because the dominant defect here is data
  captured and never surfaced; scan structurally (models with no reader, Actions
  with no caller) rather than feature by feature; exclude only each file's own
  path when scanning, because excluding whole directories hides relations and
  manufactures false positives; and remember route guards usually live in
  route-group middleware, not in the controller.
- **`EDUPAGE_FEATURES_PLAN.md` is still not in CLAUDE.md's document map.** I
  have not added it: CLAUDE.md is the governing rules file and editing my own
  rules unasked is not mine to do. It is a one-line addition whenever the owner
  wants it.

## 5bg. Promotion — the most destructive route had no test (2026-09-10)

- **Found by a fifth scan:** mutating routes (POST/PUT/PATCH/DELETE) that no
  test exercises, by route name **or** by URI stem. **185 came back.** The first
  pass, matching only `route('name')`, said 217 — inflated, because many tests
  hit raw paths. Cross-checking both ways is what made the number trustworthy;
  this is the third time today a first-pass scan needed that correction.
- **Promotion was the one worth covering first.** `academics.promotion.commit`
  moves **every active pupil** from one academic year into the next, and had no
  route-level test at all.
- **The dry-run gate is real and correctly placed.** `PromoteStudentsAction`
  refuses a commit with no prior dry run, in the Action rather than the
  controller (rule 5). No defect — but nothing stopped a refactor removing it
  silently, and then one POST would promote a whole school with nobody having
  seen a preview. Now it cannot be removed without a test going red.
- **The confirmation is `cache()->pull()`, not `get()`** — spent on use — so an
  accidental double submit cannot promote twice. That is a good decision that
  was undocumented and untested; it is both now.
- **Tests: 8** — commit refused with no preview, preview moves nobody, commit
  works after a preview, a second commit refused, same-year rejected, a teacher
  refused the wizard entirely, repeat behaviour, and a pupil who has left not
  being carried forward.
- **A test of mine asserted behaviour I had not verified**, and I corrected the
  test rather than the code: I assumed "repeat" creates a row in the target
  year. It does not — `repeat()` touches the row and leaves it in place.

### ⚠ Question for the owner, raised rather than assumed

Because `repeat()` leaves the roster row on the **source** year, a repeating
pupil ends the promotion holding a row whose `academic_year_id` is the old year.
Anything scoped to the current year — attendance, homework, the absence list,
the teacher's register — will therefore not see them until somebody re-assigns
them by hand.

That may be intended (the class is re-created for the new year and they are
re-enrolled deliberately), or it may be a gap that silently drops repeating
pupils out of the new year. **The test records the behaviour; it does not bless
it.** I have not changed it either way.

- **Full suite run locally against MySQL: 1030 tests, zero failures.**
- **181 mutating routes remain untested.** Worth working down, highest-risk
  first — the money routes (`admin/commerce/*`) and enrolment activation are the
  next tier.

## 5bh. The money routes, tested at the route (2026-09-10)

- **Second tier of the untested-routes list.** `CommerceCoreTest` covers the
  actions well — append-only ledger, hashed gift cards, discount resolution —
  but **nothing exercised the three admin endpoints that create money**.
- **Motivated by #218.** Having just found `/students` and `/teachers` guarded
  by `auth` alone, a money endpoint is the last place to assume a guard holds
  because it looks like it should.
- **The guards are right and now pinned.** `admin/commerce/*` requires
  `role:super_admin|admin` **and** `can:commerce.manage`. The half-privileged
  case has its own test: an account with the admin **role** but not the
  permission is refused, so the role alone can never mint money.
- **What the tests assert beyond authorization:** a gift card stores only
  `code_hash` — there is no `code` column at all, so leaking the table does not
  leak spendable cards — and the plain value is flashed exactly once (§43.19); a
  wallet credit writes a ledger row carrying `balance_before`/`balance_after`
  rather than a bare balance update (rule 12); and a zero or negative "credit"
  is refused, because removing money is a reversal and not this endpoint's job.
- **Tests: 10** — four refusals (no role, parent, admin-without-permission,
  anonymous), the three happy paths, and three validation refusals.
- **A fixture fault of mine:** I asserted `amount` and `code` on `gift_cards`.
  The columns are `original_amount`, `balance_amount` and `code_hash`. Reading
  the schema rather than assuming it turned a wrong test into a better one — the
  hashed-code assertion only exists because I had to look.
- **Full suite run locally against MySQL: 1040 tests, zero failures.**
- **~171 mutating routes remain untested.** Enrolment activation
  (`admin/enrollments/*`) and the library payout decisions are the next tier by
  risk: both move money or grant access.

## 5bi. Enrolment activation, tested at the route (2026-09-10)

- **`AdminRouteNamesTest` proved the routes exist; nothing tested what they do
  or who may do it.** Activating an enrolment grants somebody a place on a
  course, so "the name is registered" is not coverage.
- **My untested-routes list is thinner in this tier than I claimed.** Refunds
  and manual payments already have route coverage — they showed as untested
  because the test used a raw path rather than `route()`. Said plainly because I
  quoted "185" twice: the real number of *high-risk* uncovered routes is smaller,
  and the list is a starting point rather than a score.
- **Tests: 10** — four refusals (no role, parent, teacher, anonymous), all four
  admissions roles activating, `enrolled_at` stamped, the original join date
  preserved on a re-activation (`enrolled_at ?? now()`), rejection, and a parent
  refused rejection.
- **SMS stays off the wire** because `phpunit.xml` sets `SMS_LIVE=false`. Both
  endpoints message a family on success, and a suite that texted real people
  would be a worse defect than the one being covered — so it is asserted in the
  file header rather than assumed.

### ⚠ Two things recorded rather than changed

**Activating does not require payment.** That is a **manual override** and does
not contradict money rule 12: that rule governs *automatic* access following the
BML webhook, never the return URL. A named member of staff admitting an unpaid
pupil is a different thing from the system doing it by accident. The test
records the behaviour without blessing it.

**A supervisor can grant a place on a paid course.** `admin/enrollments/*` is
guarded by role alone, while the money endpoints next door additionally require
`can:payments.refund` / `can:payments.record`. Tightening this would change who
can do their job during admissions, so it is raised for the owner rather than
changed.

- **Full suite run locally against MySQL: 1050 tests, zero failures.**

## 5bj. SECURITY — the legacy /quran-progress routes, and a bigger question (2026-09-10)

- **I declined this twice and was wrong to.** Rule 7 freezes Hifz to
  "**namespace/route changes only**; no behavior change, no refactor". A route
  middleware guard **is** a route change — the exact category the rule permits —
  and the freeze is explicitly *scope discipline*, not production-safety
  (ADR-021). My earlier reading treated "no behavior change" as covering who may
  reach a route; re-reading the rule properly, guarding it is in scope.
- **The hole.** `Route::resource('quran-progress', ...)` and
  `POST /quran-progress/{student}/update` sat in the same `auth`-only legacy
  block as `/students` and `/teachers` (§5be), so **any signed-in account could
  write Quran progress for any pupil** — a parent, or the pupil themselves.
- **Fixed** with `role:super_admin|admin|headmaster|supervisor|teacher`.
  **Teachers are admitted here**, unlike the `/students` block: recording a
  pupil's memorisation is a teaching task, not an administrative one.
- **Tests: 6** — no role, parent, a pupil writing their own record, a parent
  posting an update, anonymous, and all five legitimate roles still admitted.
- **Full suite run locally against MySQL: 1061 tests, zero failures.**

### ⚠ The larger finding, not settled here

`app/Domains/Hifz/routes.php` declares the **entire Hifz module** under
`['auth', 'trackActivity']` with **no role or permission guard anywhere** —
milestones, sessions, mistakes, enrolments, the dean/supervisor/teacher/parent
dashboards, the mushaf and page admin. Every one of those is reachable by any
signed-in account.

That is a module-wide design question, not a two-route slip, and fixing it means
deciding who each Hifz screen is for — dean, supervisor, teacher, parent, pupil
— which is a product decision I should not invent. ADR-021 records no live Hifz
users, so nothing is exposed today.

**Recommended:** settle the Hifz role matrix as part of the §2b migration phase
the freeze is waiting for, rather than bolting guards on individually.

## 5bk. Two documentation gaps closed, and one deletion refused (2026-09-10)

**The document map now lists what sessions actually read.**
`EDUPAGE_FEATURES_PLAN.md` and `KNOWN_ISSUES.md` were both absent from
CLAUDE.md's map — STATUS has flagged the first for weeks. The plan's entry
carries the warning it has earned: **verify every row against the code before
starting a slice**, because it has been wrong 16 times across two audits. I had
declined to touch CLAUDE.md as "the rules file"; adding two documents to a map
is not changing a rule, and leaving sessions to rediscover the plan's
unreliability was the worse outcome.

**`/inertia-test` is kept, and the checklist item is answered rather than left
open.** `PHASE_0_CHECKLIST.md` said "delete after verification" and marked the
verification done, so this looked like tidy-up I owed. It is not:

- **§5t commits `public/build`.** A stale or unbuilt bundle therefore ships
  silently and renders a **blank page with no console error** — the exact
  failure this project has already hit.
- **`/up` proves Laravel booted. It does not prove the built assets render
  React.** Only this route does, and that is a different question.
- **`scripts/deploy-staging-phase0.sh` and `docs/STAGING.md` both smoke-test it**
  unauthenticated, immediately before the deploy that is already overdue.

Deleting it would have removed a real check to satisfy a line written before
§5t existed. The checklist now records the decision, and two tests pin the
route: that it serves without a session, and that it **exposes no props** —
because a public unauthenticated page must stay a static string, and props
appearing there later would be a leak rather than a feature.

Reversible in one commit: delete the route, the page, and those two references
together.

- **Full suite run locally against MySQL: 1063 tests, zero failures.**

## 5bl. RTL-safety — a stated rule that was quietly not kept (2026-09-10)

- **CLAUDE.md says every screen is "trilingual-ready (EN/DV/AR) and RTL-safe".**
  It was not. Tailwind's physical utilities do not mirror: `text-left` stays
  left in Dhivehi and Arabic, `ml-2` puts the gap on the wrong side, and the
  page looks subtly broken to **two of this school's three languages**.
- **The codebase was half converted** — 50 `text-start` against 80 `text-left`.
  The intent was there; the rule had drifted.
- **I wrote several of the offenders myself** this session, in the materials
  library, the absence list, the teacher home and the school calendar. Every
  new screen I added used `text-left` on its table header. That is why this
  shipped as a **test** rather than a tidy-up.
- **72 files converted**, mechanically and completely: `text-left`→`text-start`,
  `text-right`→`text-end`, `ml-`→`ms-`, `mr-`→`me-`, `pl-`→`ps-`, `pr-`→`pe-`.
  Every occurrence was checked first: all but one `text-left` was a table
  header, the exception is a card that should mirror too, and the six
  margin/padding cases are ordinary spacing. The `dir="ltr"` inputs in the
  glossary use no directional classes and are untouched.
- **The guard was verified by breaking it.** I injected a single `text-left`,
  confirmed the test failed, and restored — because a test that scans for
  something and finds nothing looks identical whether it works or not.
- **Full suite: 1064 tests, zero failures.** Bundle rebuilt (§5t).

**Not claimed:** this fixes *mirroring*, not translation. Whether the Dhivehi
and Arabic strings are complete and correct is a separate question, and one the
browser walk would answer better than any grep.

## 5bm. Translation coverage — measured, ratcheted, not invented (2026-09-10)

The other half of §5bl. Every screen now mirrors; almost none of them speak
Dhivehi or Arabic. This slice measures that precisely, stops it getting worse,
and deliberately translates nothing.

- **The counts, from the files: 557 English keys, 357 Dhivehi, 357 Arabic.**
  200 English keys have no entry in either. Dhivehi and Arabic are exactly in
  step with each other — no key is in one and not the other.
- **152 of the 200 are referenced from Blade or JSX**, so a Dhivehi or Arabic
  visitor reads English on a live page today. **Every one of them is
  `public.*`**: the marketing site, admissions and checkout — the most
  language-sensitive surface this project has, on the public website of a
  Maldivian institute.
- **48 have no reference anywhere**, including all 18 snake_case `common.*`
  dashboard keys (`avg_accuracy`, `student_growth`, …). Left in the baseline
  rather than deleted — proving a key unreachable needs a sweep for dynamic
  lookups, which is its own slice.
- **A defect found on the way, and fixed:** `resources/lang/en/public.php` had
  `'Join thousands of students in their journey to learn Islam',` with the
  `=> '...'` left off, so PHP indexed it as `0`. English rendered correctly *by
  accident* — the key it fell back to was the sentence — while the file grew a
  `public.0` no locale could match. It survived long enough for both Dhivehi
  and Arabic to translate a key English did not have. The third test in the new
  file catches this whole class.
- **`tests/Architecture/TranslationParityTest.php` + a 200-key baseline.** Three
  guards: Dhivehi and Arabic must move together; no new English key may ship
  without both; no language line may be numerically indexed. **All four failure
  branches verified by breaking them** — a new key, a one-language key, a
  missing `=>`, and a *translated* key that must shrink the baseline.
- Severity is not uniform, and the test header says so. A missing `public.*`
  key degrades to English, because that group is keyed by its English sentence.
  A missing snake_case `common.*` key would render the literal
  `common.avg_accuracy` on screen. None of those 18 is reachable today; if one
  is ever wired up, the baseline is where somebody finds out it needs a
  translation first.

**I did not write any Dhivehi or Arabic, and that was the point.** Inventing
school and religious terminology for this institute is a job for someone who
speaks the language. A machine-made string that reads *almost* right is worse
than an obviously English one, because nobody goes back to check it. The
ratchet makes the debt visible and bounded; closing it is a native speaker's
commit, and each one shrinks the baseline by hand.

**Two things for the owner, neither fixed here:**

- ~~**Arabic has no editor.**~~ **Fixed in §5bn**, immediately below.
- **Only 18 of 136 Inertia pages read `props.i18n` at all.** The rest are
  hardcoded English in JSX and are not even *reachable* by a translation key,
  so they are outside the 557 and outside this baseline. The internal app is
  therefore substantially further from trilingual than the file counts suggest.
  Sizing that is a slice of its own; this entry records it so the 200 is not
  mistaken for the whole debt.

## 5bn. Arabic is editable too (2026-09-10)

The gap §5bm recorded, closed. `ListTranslationCatalogAction::LOCALE` was a
hardcoded `'dv'`, so an operator could correct a Dhivehi string from the admin
screen and have it live immediately, while the *same* Arabic string needed a
file edit, a commit and a deploy. CLAUDE.md asks for EN/DV/AR equally.

- **Nothing in the data layer had to change.** `translation_overrides` is keyed
  `(locale, group, key)` and `DatabaseOverrideLoader` takes a locale — the
  migration that created the table says so in its own docblock: *"Schema
  supports any locale; the admin UI exposes dv only."* One constant was the
  whole obstacle. No migration in this slice.
- `LOCALE` becomes `DEFAULT_LOCALE` plus a `locales()` list of `['dv', 'ar']`
  and a shared `assertEditableLocale()`, so the catalog, the save and the
  suggest actions all refuse an unknown language identically.
- **English is refused as an editable locale, deliberately.** It is the
  reference the whole catalog is built from — its key set defines the rows —
  so correcting English is a code change, not an override. Accepting one here
  would let the editor quietly fork the key set.
- **An absent `?locale=` still means Dhivehi**, on the index, the save and the
  export alike, so links and habits from before Arabic existed land where they
  did. A junk locale in a hand-edited URL falls back rather than 500ing.
- The page gains a language switcher, the CSV exports per language
  (`arabic-translations.csv`, header `file_ar`), and `file_dv` in the Inertia
  payload becomes the locale-neutral `file_value`.
- **4 tests.** The one that matters asserts one language moved and the other
  did not, that both can hold a correction for the same key at once, and that
  clearing Arabic restores the Arabic file string while leaving Dhivehi alone.
  **Verified by breaking it**: hardcoding the locale back to `dv` fails that
  test, so it is testing the plumbing and not the happy path.

**Two test faults caught and fixed rather than shipped.** The Arabic fixture I
first wrote was byte-identical to the shipped `ar/common.php` string, so the
assertion would have passed whether or not the override applied — the same
class of "passes for the wrong reason" as the back-dated register fixtures in
§5at. And I asserted that `locale: ''` is rejected; it is not, because
`nullable` normalises it to absent, which then means Dhivehi. That is the
behaviour I want and the rule the rest of the slice follows, so **the test was
wrong, not the code**, and the test now pins the defaulting instead.

**Still not translated.** This makes Arabic *fixable without a deploy*; it does
not fix anything. The 200-key baseline from §5bm is unchanged, and closing it
is still a native speaker's work.

## 5bo. SECURITY/DEPLOY — three screens nobody could open (2026-09-10)

Found by a structural scan, not by the plan: **every permission the code
checks, against every permission the database creates.**

- **The defect.** `events.manage`, `forms.manage` and `messages.broadcast` were
  created **only by `RoleSeeder`**. `scripts/pull-deploy-test.sh` runs
  `php artisan migrate --force` and **never `db:seed`**, so a permission added
  to the seeder after a deployment was set up never reaches it. All three were
  added after the repo started: `events.manage` in August,
  `messages.broadcast` on 2026-09-08, **`forms.manage` on 2026-09-10 — the same
  day this was found.**
- **It fails closed, silently, for everyone.** `->can('forms.manage')` on a
  permission with no row returns false for **every account including
  super_admin** — Spatie's gate check swallows `PermissionDoesNotExist` and
  falls through to a Gate with no matching ability, and this app defines no
  `Gate::before` super-admin bypass. Verified by probe, not assumed. So the
  website events admin, the sign-up-sheet results and export, and staff
  broadcast messaging return 403 to everybody, with nothing in the log to say
  why. Broadcast is worse than a 403: `canBroadcast()` also decides whether the
  compose-to-a-class affordance renders at all, so the feature is invisible
  as well as refused.
- **31 of the 34 permissions the code checks are already created by a
  migration** — including `custom_fields.manage` and `translations.manage`.
  These three were the outliers. The fix follows the project's own convention
  and the `add_hifz_permissions` precedent: one additive, idempotent migration
  (`2026_09_10_000010_seeder_only_route_permissions`).
- **The role matrix is transcribed from `RoleSeeder`, not invented.**
  super_admin and admin receive everything there via `Permission::all()`;
  headmaster/supervisor/teacher are copied line for line. This migration is the
  delivery vehicle for a decision already made.
- **Why no test caught it:** `actingPeopleAdmin()` calls
  `Permission::findOrCreate`, so every existing test manufactures the very row
  whose absence is the defect. The new tests give a user a role and nothing
  else, so the migration is the only possible source.
- **`tests/Architecture/RoutePermissionsExistTest.php`** now requires every
  checked permission to be created by a *migration*, hard, with no baseline —
  all 34 pass. It carries a floor assertion so it cannot pass by finding
  nothing if the scan patterns drift. **Verified by breaking it**: removing the
  migration names exactly the three offenders.
- **4 behavioural tests**, 25 assertions. 3 of the 4 fail without the
  migration; the roleless-account test is the control and passes either way.

**Correction to my own first pass.** The scan initially reported four
permissions, including `custom_fields.manage`, because it only looked for
`Permission::firstOrCreate(['name' => …])` and missed `RoleSeeder`'s
array-and-loop form. `custom_fields.manage` is fine. Rescanned against both
migrations and seeders before claiming anything — the count went 4 → 0 → 3 as
the question got sharper.

**Related, recorded not fixed — for the owner:**

- **Only three of the nine roles are created by a migration** (`super_admin`,
  `reviewer`, `writer`). `admin`, `headmaster`, `supervisor`, `teacher`,
  `student` and `parent` exist only if `RoleSeeder` has run. Every
  permission-granting migration in the repo, mine included, therefore no-ops
  its role grants on a migrate-only database. That is the established pattern
  and it works on a seeded deployment, but it means role changes cannot be
  shipped by deploy at all. Moving role creation into a migration touches the
  role matrix, which is a product decision.
- **`admin` is granted `Permission::all()`, identical to `super_admin`**, while
  the comment directly above it says "most permissions (school operations, not
  system-level)". The code and its comment disagree; one of them is wrong.
- **Operator check before the next deploy:** on `test.akuru.edu.mv`, confirm
  the `permissions` table holds all 34 dotted names and that the six
  seeder-only roles exist. This migration fixes the three rows going forward;
  it cannot tell you what the existing database currently holds.

## 5bp. SECURITY — the BML webhook confirmed payments for free (2026-09-10)

**The most serious defect found this session.** Rule 12 says access to paid
anything depends on BML **webhook** confirmation. `POST /webhooks/bml` is
anonymous, the IP allowlist is empty by default (`Empty = no allowlist`), and
the signature check was the only thing between the open internet and a
confirmed payment. It failed open two different ways.

**Both proven against the running app before anything was changed** — a probe
posted an unsigned webhook and read the payment status back:

- **No secret configured** → the whole check was skipped. Unsigned POST of
  `{"localId":"<ref>","state":"success"}` → payment `confirmed`, 5000 MVR.
- **Secret configured, header simply absent** → `if ($signature && …)`
  short-circuited to false and the check never ran. **An operator who had done
  the right thing was still defenceless.** This is the worse of the two.
- The *only* case ever rejected was a **wrong** signature — the one thing an
  attacker has no reason to send.

Anyone who could guess or observe a `merchant_reference` could mark someone's
payment paid, which then activates the enrolment through the normal listener
chain. `PaymentService::applyVerifiedResult` carries the comment "authoritative
since signature was verified"; that invariant was false, and is now true.

**Fixed by failing closed.** A missing signature is refused when a secret is
configured. With no secret, the webhook is refused unless
`BML_WEBHOOK_ALLOW_UNSIGNED` is explicitly set — a sandbox escape hatch that
**never weakens a deployment that has a secret**: once one is configured a valid
signature is always required, regardless of the flag. Also dropped a dead
`?? config('bml.callback_secret')` — that key does not exist in `config/bml.php`
and `webhook_secret` already falls back to the `BML_CALLBACK_SECRET` env.

**7 tests** (`tests/Feature/Finance/BmlWebhookSignatureTest.php`): both fail-open
paths, a wrong signature, a signature valid for a *different* body (what
verifying the raw body buys), the custom header name, the sandbox opt-out, and
a correctly signed webhook that does confirm — without that last one the suite
would pass on a webhook that rejects everything, which would be its own defect.
**Verified by restoring the old logic**: exactly the two fail-open tests fail,
the other five pass either way as controls.

**Why the suite never caught it.** `BmlWebhookTest` posts unsigned and asserts
the payment confirms — the existing tests *documented the vulnerable behaviour
as correct*. And `test_webhook_accepts_valid_raw_body_signature` mocks
`verifyCallback` wholesale, so it never exercised the real branch logic.

**`phpunit.xml` now sets `BML_WEBHOOK_ALLOW_UNSIGNED=true`**, because the suite
fakes BML and shares no secret with it, and 14 call sites across 7 files post
unsigned. The refusal paths are pinned by tests that set a secret and clear the
flag explicitly, so the guarantee is asserted rather than assumed.

**⚠ OPERATOR — before any real payment:** set `BML_WEBHOOK_SECRET` in `.env`
and confirm BML signs with HMAC-`sha256` over the raw body under
`X-BML-Signature` (adjust `BML_WEBHOOK_SIGNATURE_HEADER` /
`BML_WEBHOOK_HMAC_ALGO` if their docs differ). **With no secret and no opt-out,
no payment will confirm** — that is deliberate, and better than the alternative.
Setting `BML_WEBHOOK_IP_ALLOWLIST` is worth doing as defence in depth.

**Found by scanning `config()` keys referenced in code against the config files
that exist** — 80 referenced, 5 unresolved. Two of the other four are real and
recorded below; `payments.providers.` is dynamic concatenation and
`permission.testing` is Spatie's own.

## 5bq. Two config keys that silently do not exist (2026-09-10) — FIXED in §5br

From the same scan, neither fixed in the security PR, so it stayed reviewable.

- **`services.bml.api_key` is always empty, so the admin settings screen
  permanently reports BML as not configured.** `SettingsController` reads
  `! empty(config('services.bml.api_key')) || ! empty(env('BML_API_KEY'))`.
  There is no `bml` block in `config/services.php` — BML config lives in
  `config/bml.php` — so the first half is always null. The second half saves it
  locally but **not on a deployment**: both `pull-deploy-test.sh` and
  `deploy-staging-phase0.sh` run `php artisan config:cache`, and Laravel skips
  loading `.env` entirely when config is cached, so `env()` returns null outside
  config files. An operator asking "is BML set up?" gets a permanent "no".
  (Unless the host exports them as real server environment variables rather
  than via `.env`.) Fix: read `config('bml.api_key')`.
- **`academics.attendance_tardies_per_absence` is missing from
  `config/academics.php`.** Its three siblings — `attendance_mode`,
  `attendance_notify`, `attendance_chronic_threshold` — are all there.
  `ResolveAttendanceSettingsAction` passes an explicit default of `0`, so it
  degrades safely, but a school cannot set this one globally the way it can set
  the other three. Fix: add the key with an env fallback.

## 5br. Both integration badges lied, in opposite directions (2026-09-10)

The §5bq follow-up. The admin settings screen shows a green ✅ "Configured" or
amber ⚠️ "Not Configured" badge for SMS and BML. Both read keys that could not
answer the question they were asked.

- **SMS was always green.** It checked `services.sms_gateway.url`, which carries
  a non-empty default (`https://akuru.edu.mv/api/v2`), so `! empty()` could
  never be false. An operator saw "Configured" with **no API key at all**.
  This is the dangerous direction, and worse than the BML half: this school
  texts families when a child is absent, and false reassurance means nobody
  goes looking when those texts silently fail.
- **BML was always amber on a deployment.** It checked
  `services.bml.api_key`, which does not exist, then fell through to
  `env('BML_API_KEY')`. Both deploy scripts run `config:cache`, after which
  Laravel never loads `.env`, so `env()` returns null. **It read correctly on a
  developer machine, which is exactly why it survived.**

Each badge now reads what the code it describes actually requires:
`SmsGatewayService` needs the Dhiraagu credentials or the gateway `api_key`
(it has two send paths, and the badge honours both), and
`BmlPaymentProvider::initiate` needs `bml.api_key` and `bml.base_url`.

**A new warning, from the trap §5bp created.** An `api_key` is enough to send a
family to the BML payment page, but since the webhook now fails closed, a
deployment with no `webhook_secret` will **take the money and never grant
access**. The card now says "⚠️ No webhook secret — payments will not confirm"
in that exact state. Better to say it on the screen the operator is already
looking at than only in a STATUS entry.

`academics.attendance_tardies_per_absence` added to `config/academics.php`
alongside its three siblings, with an `ATTENDANCE_TARDIES_PER_ABSENCE` env
fallback. It degraded safely before (the Action passes an explicit `0`), but it
was the only one of the four attendance settings a school could not set
globally.

**5 tests**, 20 assertions, including the two "always wrong" regressions
directly. **Verified by restoring the old logic**: 4 of the 5 fail, the tardies
test being independent of the controller.

## 5bs. Every runtime key must resolve — and the owner's list, in one place (2026-09-10)

Three defects on 2026-09-10 were the same bug wearing different clothes: **a
string looked up at runtime that nothing defined**, resolving to null or false
instead of throwing. Permissions checked but created by no migration (§5bo). A
config key that did not exist, sitting in the fallback of the payment webhook's
signature check — pulling that thread found the webhook confirming payments for
free (§5bp). Config keys that could not answer the question they were asked, so
both settings badges were wrong (§5br). **None of them threw.** That is the
whole problem: `config()` returns null, `->can()` returns false, and the feature
is simply not there.

`tests/Architecture/RuntimeKeysResolveTest.php` closes the family. The
permission half already had `RoutePermissionsExistTest`; this adds the other two:

- **Config keys** — every `config('a.b')` the code reads must resolve. Hard
  assertion, **no baseline**: all resolve today, because §5bp and §5br fixed the
  ones that did not. Keys built by concatenation (`config('payments.providers.'
  .$name)`) and Spatie's published `permission.*` are excluded, with reasons.
- **Route names** — every `route('name')` must be registered, against a
  4-entry baseline.

**Four `route()` calls name no registered route** and would throw
`RouteNotFoundException` the moment they were reached. All four are in code
nothing currently calls — **verified rather than assumed**, which is the whole
difference between "latent" and "live":

- `recitation-practices.index` / `.show` — `RecitationPracticeController` is not
  routed at all. A dead controller; Hifz is frozen and deleting it is a scope
  change.
- `public.events.qr` — `EventRegistration`'s QR url builder. Nothing calls it,
  and the model has **no `$appends`**, so serialisation never fires it.
- `media-galleries.show` — `MediaGallery::getUrlAttribute()`. Same shape, same
  check, same answer.

Fixing the last two means *adding routes* — a feature, not a repair — so they
are baselined rather than papered over.

**One fixed:** `auth/verify.blade.php` posted to `route('verification.resend')`;
the registered name is `verification.send`. That view is an orphan (the live
prompt renders `auth.verify-email`), so it was never reachable, but the right
name was one word away.

**Both guards verified by breaking them**, and both carry a floor assertion so
they cannot pass by scanning nothing if the patterns drift. Two false-positive
classes are excluded explicitly, because both appeared in the first draft:
`Notification::route('mail', …)` sets a channel, and `$request->route('id')`
reads a route *parameter*. Neither is a url. A first pass then over-corrected
and missed `redirect()->route('x')`, which *is* — the pattern now matches both
shapes deliberately.

**A scan that found nothing, recorded so it is not re-run:** events dispatched
without a listener. 17 dispatched, and every apparent orphan was a false
positive — Laravel framework events (`Registered`, `PasswordReset`, `Verified`,
`Lockout`), `Job::dispatch()` calls caught by an event-shaped regex, and
`InvoiceIssued`/`InvoiceReminderDue`, which are wired with `Event::listen()` in
a service provider rather than a `::class =>` array. No defect.

**`docs/KNOWN_ISSUES.md` now carries "Decisions only the owner can make"** — all
14 items raised across autonomous sessions and deliberately not decided,
collected from the STATUS sections they were scattered through. Each is phrased
as a question with a default, so "do nothing" is a legible choice. Grouped:
before any deploy (browser walk, `BML_WEBHOOK_SECRET`, verify the permission
rows, rotate the super-admin password, branch protection), product scope (Wave 4
— all seven slices verified genuinely unbuilt, ≈7½–8½ weeks, the entire
remaining feature backlog), security and permissions (the unguarded Hifz module,
`admin` == `super_admin`, roles that cannot ship by deploy, supervisor and paid
courses), the repeating-pupil roster row, and the 200 untranslated strings.

**The agent-buildable backlog is empty.** What remains is decisions and a deploy.

## 5bt. Two guards deliberately not written, with the evidence (2026-09-10)

After §5bs the buildable backlog was empty, so the remaining CLAUDE.md rules
without a mechanical guard were assessed as candidates. **Both were rejected**,
and the reasoning is recorded here so no future session re-runs the same dead
end. A guard that cries wolf is worse than no guard: it teaches people to grow
the baseline, which is the exact opposite of a ratchet.

**Rule 10 (`academic_year_id` on time-scoped tables) is not mechanically
checkable.** A keyword scan over 244 created tables flagged 37 as time-shaped
and missing the column. Checking them by hand showed the scan was wrong in
three separate ways:

- **`term_id` also satisfies the rule** — it says "`academic_year_id` (and
  `term_id` where relevant)", and a term belongs to a year. The first scan
  looked only for `academic_year_id` and so flagged `competency_assessments`,
  which carries `term_id`.
- **Child rows inherit the backbone.** `exam_marks` → `exams`, which carries
  *both* columns. `register_unlocks` → `lesson_logs`. `invoice_lines`,
  `payment_items`, `fee_structure_items` and `lesson_log_material` are the same
  shape. Each is compliant in substance; none carries the column itself.
- **Lookup and non-academic tables match the keywords.** `exam_types` and
  `grade_scales` are reference data, not events. `sessions` is Laravel's HTTP
  session table.

Deciding compliance needs transitive FK reachability *plus* a judgement about
whether a table records an event or describes one. A static scan can produce
neither, and would fire on every future lookup table containing "grade" or
"fee". **Rule 10 stays a review-time rule, not a CI rule.**

**CSV export on every listing needs no guard: the convention is already
over-satisfied.** 42 Inertia `*/Index` pages render against **86** named
`*.export` routes — exports outnumber listings two to one, because several
screens export more than one view of their data. There is no gap to hold.

Contrast with what *did* justify a guard: rules 3, 5 and ADR-005, plus the
runtime-key family in §5bs, are all decidable from a single file's text with no
judgement call, which is exactly why they work as tests.

## 5bu. WALKED IN A BROWSER — and it found a defect (2026-09-10)

**I was wrong to call this operator-blocked.** I had been reporting the browser
walk as impossible from an agent session because `test.akuru.edu.mv` needs
cPanel access. But CLAUDE.md's definition of done says *"walked in a browser"*,
not *"walked on production"*, and `docs/PILOT_REHEARSAL.md` records rounds 1–3
being walked **locally**. This container has Chromium at
`/opt/pw-browsers/chromium-1194` and Playwright installs against it. The walk
was available the whole time.

Local instance: `akuru_walk` database, `migrate:fresh --seed` (which runs
`PilotRehearsalSeeder` — 2026-2027 Pilot, Grade 5 A, 15 students), committed
`public/build` assets with `public/hot` removed, `php artisan serve`.

**13 pages loaded, all 200, none blank, no page errors.** Verified as an
admin, a super_admin, a teacher and a parent:

- The **three screens §5bo made reachable** — `/academics/events`, `/forms`,
  `/portal/messages` — all render. That was the verification I had said I could
  not give.
- **§5bn Arabic editor, driven end to end**: typed a correction, saved,
  reloaded, **it persisted**; the row showed "Override active"; and the
  **Dhivehi box stayed empty**, which is the exact claim the test makes.
- **§5br settings badges**, read off the page. SMS shows "Not Configured" with
  the corrected `SMS_GATEWAY_API_KEY` hint — correct, since this .env has no
  key, and *the opposite of what the old code would have shown*. Setting a
  `BML_API_KEY` with no webhook secret produced "Configured · ⚠️ No webhook
  secret — payments will not confirm", the branch invented in §5br and never
  before seen.
- **§5aw teacher landing** confirmed: `teacher@akuru.edu.mv` lands on
  `/portal/teacher`, the redirect that slice changed.

**The defect the walk found, which no test caught.** The translation editor
showed **`notifications (0)`** and **`documents (0)`**.
`ListTranslationCatalogAction` did `if (! is_string($reference)) continue;`,
and both of those files are **entirely** nested arrays — so every line was
silently dropped and **both groups were editable in neither language**. That
includes `notifications.attendance.marked`, the SMS text sent to a family when
their child is marked absent.

Fixed by flattening to dotted keys. Nothing downstream needed changing:
`SaveTranslationOverrideAction` validates with `Lang::get($group.'.'.$key)` and
`DatabaseOverrideLoader` writes with `Arr::set()` — both already spoke dot
notation. The catalog total went 525 → **557**, which is exactly the English key
count the §5bm parity baseline arrived at independently. Two separately written
flatteners agreeing is a better check than either alone.

Verified in the browser after the fix: tabs read `notifications (21)` and
`documents (11)`, 21 rows render, and a nested key edited from the UI persisted
through a reload.

**The suite asserted the catalog *renders*; it never asserted it contained
anything.** That is precisely the gap the "walked in a browser" clause exists to
catch, and it went unnoticed through the two slices that touched this screen
today.

**Two observations for the owner, not fixed:**

- **The AppShell nav is worse rendered than described.** KNOWN_ISSUES top-five
  item 2 records ~90 wrapping links awaiting a decision. On screen it occupies
  **ten rows and roughly a third of the viewport above any content**, on every
  page. The screenshot makes the case the issue text does not.
- **No `super_admin` account is seeded.** `admin@akuru.edu.mv` holds `admin`,
  and `/admin/settings` is `role:super_admin`, so **the settings screen cannot
  be reached with any documented seed login** — a walker has to create an
  account first, as this walk did. Either seed one or relax the guard.
- Subresources blocked in this sandbox, all external and none an app fault:
  `fonts.bunny.net`, `fonts.googleapis.com`, `translate.google.com`. Worth
  knowing that the UI reaches for three third-party origins on every page.

## 5bv. The daily loop, completed in a browser end to end (2026-09-10)

§5bu loaded pages. This **completed the task**, which is what CLAUDE.md's
definition of done actually asks for: *"a user can complete the task in a
browser, not only when tests pass."* One continuous run, three roles, zero page
errors.

**As the teacher** (`teacher@akuru.edu.mv`):

1. `/academics/registers/today` correctly offered **"Generate my registers for
   this date"** — the fresh seed has none for today. Clicked it; one register
   appeared.
2. The register opened on *Arabic Language · Grade 5 A · 2026-09-10*, status
   **EXPECTED**, with the plan-topic dropdown populated ("1. Sun and moon
   letters").
3. **Homework due was pre-filled to 2026-09-11**, captioned "Defaults to the
   next lesson for this class" — §5ar's next-lesson default, working. Not
   "tomorrow"; the next day this class actually meets this subject.
4. The materials picker showed the empty state written for E13a: *"Nothing
   saved for this subject yet. Write a material once and it is reusable in
   every lesson."*
5. Filled what was taught and the homework, marked the first of **15 pupils**
   absent, submitted. → **"Register submitted."**, status **SUBMITTED**.

**As the admin**, `/academics/attendance/absences` (§5ay) then showed the
consequence, unprompted: *"**1** not in on 2026-09-10 · **1** with no note"*,
with Fatima Yoosuf / PIL-01 / Grade 5 A / Period 1 / **"No note — call home"**
and, in the Ring column, **"No contact on file"** — the emergency-contact
lookup from §5az rendering its empty state.

**As the family** (`parent@akuru.edu.mv`), both consequences arrived:

- Portal home tiles updated to **Attendance 1 · 0% present** and **Homework
  1 · 1 to do**, above tomorrow's real timetable with subjects, teachers and
  rooms.
- `/portal/homework` showed exactly what the teacher had typed minutes earlier:
  *Fatima Yoosuf · Arabic Language · Ustadh Mohamed · **Due 2026-09-11** ·
  "Revise lines 1-10." · Set on 2026-09-10.*
- `/portal/holidays` (§5ax, the family calendar) rendered its broadened
  wording — *"Holidays, closures, events and exam days for the current school
  year"* — with an honest "Nothing published…" empty state.

**Verified working end to end this way: S2.6 registers, §5ar homework due
dates, E13a materials picker, §5ay absence list, §5az emergency contacts,
§5ax family calendar, §5aw teacher landing.** Every one of those was
previously "CI-green and never executed".

**Three things for the owner, none a code defect:**

- **The pilot seeder gives no student an emergency contact**, so the Ring
  column can only ever show "No contact on file" on a fresh seed. The real path
  has still never been seen. One seeded contact would fix that for every future
  walk.
- **The AppShell nav, measured rather than described.** ~90 links across
  **eleven rows**, filling roughly the top quarter of a 1200px viewport on
  *every* page before any content begins. KNOWN_ISSUES top-five item 2 has this
  awaiting a decision; the screenshot settles what the prose could not.
- `/portal/timetable` does not exist — the family timetable lives on the portal
  home. Noted only because it is a natural URL to guess.

## 5bw. E13 walked end to end, including its security rule (2026-09-10)

The materials chain (E13a→E13b→E13c) was the largest thing built this session
and, until now, entirely unexercised beyond an empty state. **E13c is a security
surface**, and after §5bp — a fail-open money path that unit tests had declared
correct — security logic that has only ever been unit-tested does not deserve
much confidence. So it was driven through the UI.

**E13a — write once, reuse.** Created *"Sun and moon letters — practice sheet"*
against Arabic Language with tags. **"Material saved."** It then appeared in the
register's library picker, correctly scoped to that subject — the same picker
that had shown *"Nothing saved for this subject yet"* an hour earlier.

**E13b — sending it home.** Ticking the material revealed **"Send home with the
homework"**, exactly as designed (the option only exists once attached). Ticked
both, resubmitted. The family homework page then showed a **"What you need"**
block carrying the material's title *and* its detail text.

**E13c — the access matrix, with a real uploaded file.** A `.txt` was uploaded
through the UI and fetched as five different identities:

| identity | result |
|---|---|
| anonymous | **302** → `/en/login` |
| teacher (`registers.fill`) | **200**, real file contents |
| admin | **200** |
| parent of a Grade 5 A pupil | **200** |
| an unrelated pupil account | **403** |

**Then the decisive negative.** The teacher unticked *"Send home"* — leaving the
material still **attached** to the lesson — and resubmitted. The same parent,
same file, same class: **403**.

That is the exact distinction the Action's docblock claims and the one that
mattered: *"Attaching a material to a lesson is deliberately not enough."*
Proven through the UI rather than asserted in a unit test.

**A false alarm I caught before reporting it.** An early probe showed anonymous
requests returning **200** on the file endpoint while an authenticated parent
got 404 — which reads like a serious hole. It was neither: Playwright's
`page.goto()` follows redirects, so the "200" was the **login page** it had been
redirected to, and the 404s were simply because no file existed yet. Re-probed
with `request.get(..., { maxRedirects: 0 })` and with raw `curl -D-`: the real
answer is 302 to `/en/login`. **Measure the thing you think you are measuring**
— had this been reported it would have been exactly the kind of false finding
this session has spent so long correcting in the feature plan.

**Now verified in a browser across §5bu, §5bv and this entry:** S2.6 registers,
the homework due-date default, E13a, E13b, E13c and its full access matrix,
the absence list, emergency contacts, the family calendar, the teacher landing,
the Arabic translation editor, and the settings badges in both directions.

## 5bx. The messages page called a teacher a parent (2026-09-10)

Continuing the walk into the **three screens §5bo made reachable**. Loading them
proved they no longer 403; it did not prove they work. Using them found this.

**Events works end to end.** An admin created a trilingual event — Title
EN/DV/AR, location, start/end, type, status, registration, year, seats — got
**"Event saved."**, and the family then saw it at `/portal/events`. A screen that
returned 403 to *every account including super_admin* from August until today
now demonstrably does its job.

**Forms renders its own empty state** correctly: *"Forms you have sent, and what
came back. · New form · No forms yet."*

**Messages had a real defect.** The page greets a teacher with:

> Conversations with your child's teachers.

A teacher has no child here. The whole page is written for families, and the
staff half had **never been seen by anyone** — `messages.broadcast` did not
exist as a permission row until §5bo created it in a migration, so
`canBroadcast()` was false for every account since the feature shipped on
2026-09-08.

**`canCompose` could not fix it**, which is the interesting part. It is true for
a family too — their personal directory of teachers is non-empty — so it does
not separate staff from families. The real discriminator is *"can this person
address a class"*, which the controller already computed **inside** the
`canCompose` expression and then threw away. It is now returned as
`canBroadcast` and the subtitle branches on it: staff get *"Message a class, or
reply to a family."* Same query count — the `classes()` result is computed once
and reused rather than resolved twice.

Verified in the browser after rebuilding the bundle: teacher → the staff line,
parent → the family line.

**Not fully solved, and worth saying so.** An **admin** still sees the family
line, because they teach no class so `canBroadcast` is correctly false — but an
admin has no child either. A third string would be over-engineering a screen
that is fundamentally the family messaging inbox; the accurate fix would be
deciding whether staff who teach nothing belong on this page at all, which is a
product question. Flagged, not invented.

**2 tests.** One pins that a teacher gets `canBroadcast: true` while a family
gets `false` *and both get `canCompose: true`* — the assertion that would have
caught the conflation. The other pins that `messages.broadcast` alone is not
enough: an admin holding the permission but teaching nothing gets `false`.

## 5by. SEVERE — every validation message in the app read as a raw key (2026-09-10)

**The worst defect the browser walk has found, and it was one `Save` click
away the entire time.**

Walking E6a forms: an admin filled a title, clicked **Save**, and *nothing
happened*. No save, no message, composer still open. Two separate defects
stacked on top of each other.

**Defect 1 — the error was never rendered.** `fields.*.label` is `required`
server-side, and the composer rendered errors for `title`, `fields`,
`fields.*.options`, `requires_parent_confirmation` and `fee_amount` — every
rule except that one. It sat on **the field most likely to be blank**, because
"Add question" creates one empty. Fixed by rendering `fields.N.label` (and
`fields.N.type`) beside each question.

**Defect 2 — and this one is app-wide.** With the error finally rendering, it
read **`validation.required`**. The raw key.

Laravel registers its translation loader with **two** paths:

```php
new FileLoader($app['files'], [__DIR__.'/lang', $app['path.lang']])
```

— the framework's own messages *and* the application's.
`TranslationOverrideServiceProvider` (T1) replaced that loader with
`new DatabaseOverrideLoader($app['files'], $app['path.lang'])`, passing the app
path **alone** and silently dropping the framework half. This app ships no
`lang/en/validation.php` of its own, so **everything Laravel provides resolved
to its raw key**:

| key | what a user saw |
|---|---|
| `auth.failed` | `auth.failed` on every failed login |
| `validation.required` | on every required field, everywhere |
| `validation.email`, `validation.max.string`, … | likewise |
| `passwords.sent` | on password reset |
| `pagination.next` | on every paginator |

Fixed by inheriting `paths()` from the loader being replaced rather than naming
them — which also carries `jsonPaths()` and `namespaces()` across, and means a
future Laravel registering a third path keeps working without anyone
rediscovering this file.

**Why nothing caught it.** `assertSessionHasErrors('field')` asserts the *key a
rule failed under*; it never reads the sentence a person sees. Over a thousand
tests could pass with every message in the product rendering as
`validation.required`. **Only opening the screen shows it.**

Also set friendly attribute names on the forms controller, so the message reads
*"The question field is required."* rather than *"The fields.0.label field is
required."* The rule was right; only the name it used was written for a
developer.

**4 tests.** Two on the loader — framework messages must resolve *and* a DB
override must still win, since the whole point of replacing the loader is the
override — and two on the forms endpoint, that a blank question returns an error
under exactly the key the composer reads, and that a filled one saves.
**Verified by reverting the provider**: both loader tests fail.

Verified in the browser at each step: raw key → *"The fields.0.label field is
required."* → *"The question field is required."*

**E6a otherwise works end to end**: with a question filled, **"Form saved."**,
listed as *"Ramadan iftar — headcount · OPEN · 0 responses"*.

## 5bz. The app walked in Dhivehi and Arabic — mechanics sound, one bidi defect (2026-09-10)

§5bl shipped RTL-safety and §5bm/§5bn the translation layer, and **the app had
never once been looked at in Dhivehi or Arabic**. Given §5by — a defect in the
translation layer that a thousand tests missed — that was the obvious place to
look next.

**The mechanics are right.** `/en`, `/dv` and `/ar` on the staff overview:

| locale | `lang` | `dir` | computed | horizontal scroll |
|---|---|---|---|---|
| en | `en` | `ltr` | ltr | none |
| dv | `dv` | `rtl` | rtl | **none** |
| ar | `ar` | `rtl` | rtl | **none** |

No page errors in any locale. The layout genuinely mirrors: nav, brand, stat
cards and table headers all move to the right edge, and **nothing overflows
horizontally** — which is §5bl's logical-utility conversion doing its job.

**One real rendering defect, and only a browser could show it.** An English
sentence inside an RTL page has its trailing full stop moved to the **front**:

> `.No exams still in marks entry after the exam date`

The DOM string is correct — `"No exams still in marks entry after the exam
date."` — and the element resolves `direction: rtl; unicode-bidi: isolate`. This
is the Unicode bidi algorithm placing a neutral character at the visual left of
an RTL paragraph. It affects **every untranslated English sentence on a Dhivehi
or Arabic page**, which today is most of them.

**The fix is one CSS rule, and it is deliberately not applied here.**
`unicode-bidi: plaintext` on text-bearing elements under `[dir="rtl"]` resolves
each paragraph's direction from its first strong character. Tested by injecting
it live: the sentence renders correctly, period at the end.

But `plaintext` also resolves `text-align: start` against the *paragraph's* new
direction, so English text becomes **left**-aligned on an RTL page. With ~87% of
the interface still English (§5bm: 18 of 136 Inertia pages read `props.i18n`),
that would left-align nearly every line on every Dhivehi and Arabic screen.

That is a visible design change across the whole RTL experience, not a bug fix,
and it is the owner's call — the same category as the nav. It also **shrinks to
nothing as translation progresses**: once a string is Dhivehi, its first strong
character is Thaana and it aligns right on its own. Deciding it now, against a
mostly-English UI, would be optimising for a state the project is trying to
leave.

Before/after screenshots captured. Whoever takes this decision should look at
both rather than the description.

## 5ca. SEVERE — two public pages returned 500 to every visitor (2026-09-10)

The public marketing site had never been walked. Eight pages fetched; **two
threw 500**. This is the front door of a school — the first thing a prospective
family sees.

**`/about` — `SQLSTATE[42S22]: Unknown column 'is_active'`.**
`AboutController` queries `Testimonial::where('is_active')->orderBy('sort_order')`.
`Testimonial` has **`is_public`** and **`order`**; `Instructor`, queried three
lines above it *in the same method*, has `is_active` and `sort_order`. The
Instructor query shape was copied onto the wrong model. Fixed by using the
model's own scopes — `Testimonial::query()->public()->ordered()` — exactly as
`ListCoursePageTestimonialsAction` already did, which is why `/courses`
rendered fine while `/about` did not.

**`/apply` — `Undefined variable $step`.** In the Blade view:

```php
'desc'=>'We'll reach out via mobile or email…'
```

An **unescaped apostrophe** in a single-quoted PHP string. It closes the string
early, breaks the array literal, breaks the `@foreach`, and `$step` never
exists. The admissions funnel entry — the page a family uses to apply to the
school — has been throwing for every visitor.

**Why nothing caught either.** `PublicRouteNamesTest` asserts these route
**names are registered**. It never issues a request. `public.about` and
`public.apply` both "passed" the entire time the pages were broken. Name-only
coverage is the same trap recorded in §5bi, and it hid two 500s on the most
public surface in the product.

`tests/Feature/Website/PublicPagesDoNotCrashTest.php` now fetches **every
parameterless `public.*` GET route** and asserts it does not 5xx. Not "is 200":
a page may legitimately 404 on an empty database, but it may never throw.

**Three drafts of that guard, and the first two were worthless.** Draft one
issued a plain `get()` and saw the locale middleware's **302** — never 5xx — so
it passed against the very bug it was written for. Draft two followed redirects
and landed somewhere that was not the controller. Only
`withoutLocalizationMiddleware()`, the helper the rest of the suite already
uses, actually reaches these controllers.

I nearly shipped a green test that checked nothing, twice — which is precisely
what §5bt argued against when it declined to write a rule-10 guard. So the file
now carries **a self-test**: a deliberately throwing route that the same
mechanism must report as 5xx. If middleware or a redirect ever swallows the
check again, that test fails and says so.

**Verified by restoring each bug in turn**: the guard names
`public.about (about) -> 500`, then `public.apply (apply) -> 500`. Both live
URLs confirmed 200 after the fix.

## 5cb. E15 — lost and found (2026-09-11)

**Wave 4 un-gated by the owner**, who asked for all remaining coding after
being told twice that most of it was decision-blocked rather than unbuilt. That
is their call to make, and it is recorded here as the reason these slices start.
Built one per PR, per rule 1.

`found_items` — title, description, location, `found_at`, who is holding it,
status `listed|returned`, and a private photo media id. Staff log what turns up;
**families browse what is still on the shelf**, which is the half that makes the
feature worth having.

Design decisions worth stating:

- **Carries `academic_year_id` (rule 10), stamped not chosen.** An item found is
  something that happens on a date, and last year's shelf must not join this
  year's list. Nobody logging a lost water bottle should have to think about
  which school year it is, so `SaveFoundItemAction` reads the active year and
  refuses if none is active.
- **Any member of staff may edit any item — deliberately unlike E13a
  materials.** A material carries its author's wording; a lost jumper does not.
  The person who finds a bag is often not the person who later learns whose it
  is, and making them chase the finder is the kind of rule that gets worked
  around by logging a duplicate. Editing never reassigns authorship.
- **Returning is its own Action**, not a status flip, because the only fact
  anyone asks afterwards is *who took it away*. Returning twice is refused: two
  people believing they collected the same item is worth an error.
- **Two photo reads, and the family one is narrower.** Staff see the photo of a
  returned item because that is their record; for a family a returned item stops
  being a notice on the board. Portal reaches it through
  `ReadListedFoundItemPhotoAction` rather than the model — Portal importing
  `Academics\Models\FoundItem` would have been a new rule-3 violation, and the
  baseline may only shrink.

**10 tests, 46 assertions**, most of them the boundary between the two
audiences. **Walked in a browser end to end**: staff logged an item → the family
saw it with description, place and holder → staff marked it returned to a named
person → the family list went empty. Zero page errors.

**Two things the process caught, both worth recording:**

- The **morph-map test failed** on first run — `FoundItem` was not registered in
  `config/morph-map.php`. ADR-005 and the arch test did exactly their job.
- The **walk found a duplicate flash banner**: AppShell already renders one, and
  the page rendered a second, so "Item logged." appeared twice. Invisible to
  every test; obvious on screen in one second.

**Fixture correction:** the first draft hand-rolled an `AcademicYear` with
`starts_on`/`ends_on`. The columns are `start_date`/`end_date`, and the repo
already has a `makeYear()` helper. Used the helper.

## 5cc. E17 — clubs, with no clubs table (2026-09-11)

**There is no `clubs` table and no club-membership table**, and that is the
whole slice. A club is a `Course` with `course_type = 'club'`; its members are
ordinary `CourseEnrollment` rows. The plan asked for precisely this — *"the
course engine can model these already … resist a parallel enrolment system"* —
and rule 11 says the same thing. **No migration.**

Built as `Courses/Components/Clubs`, following rule 6: subject behaviour is a
component, and the engine never branches on `course_type`. The component
touches no model of any domain, its own included, reaching everything through
Action seams — `ComponentsIsolationTest` enforces that and passes.

Two engine seams were involved:

- `ListEngineCoursesAction` gained an **optional** `?string $courseType`. The
  value is supplied by the caller — `'club'` from this component, `'hifz'` from
  Quran — exactly as `ListEnrollmentTargetsByCourseTypeAction` already did for
  enrollments. Data, not a branch.
- `CancelEnrollmentAction` is new, and is the mirror of
  `EnrollUnifiedStudentInOfferingAction`. Components need a way to remove a
  member and rule 3 forbids them touching `CourseEnrollment`, so the engine
  owns the verb. It **cancels rather than deletes**: an enrollment is the
  record that somebody was in a club last term, and the status column already
  exists to say it ended.

**"Members from outside the enrolled roll" needed no code — only the absence of
a check.** A `CourseEnrollment` wants a student id and never asks whether that
student sits on a class roster. What it *did* need was
`ListStudentsOnActiveRosterAction` (new, Academics, batched to avoid N+1), so a
club leader taking a register can tell a pupil from a visitor. A register that
cannot make that distinction is one nobody trusts.

Adding a member to a club with no offering raises an operator-facing sentence
rather than failing quietly — *"nothing happened"* is the failure mode this
session spent all day fixing.

Also: a **printable attendance sheet**, deliberately eight blank weeks. A club
leader takes a register on a clipboard in a hall with no screen.

**8 tests, 52 assertions**, most asserting that nothing new was invented —
including that `clubs` and `club_members` do not exist as tables. **Walked in a
browser**: added a member, the visitor flag rendered, the sheet printed with
eight blank columns. Zero page errors.

## 5cd. E8 — student pick-up, as a protocol (2026-09-11)

**This slice releases a child**, so almost all of it is refusals. The plan calls
it *"a protocol, not a button"* and says in terms: *do not ship steps 1–5
without step 2*. Step 2 is the PIN.

Three tables, all additive: `pickup_pins` (hashed, one per guardian),
`pickup_windows` (the school opens pick-up per day — a family cannot ask at
2am), `pickup_notices` (`academic_year_id` per rule 10). Morph aliases
registered in the same slice per ADR-005.

The five steps: staff open the window → a guardian says *"I am ten minutes
away"* → the office is told → staff send the child to reception → **the
guardian confirms they have the child**. Step 5 is what makes it a loop rather
than a notification.

**Every gate lives in `RequestPickupAction`**, not the controller (rule 5), so
there is one place to read when somebody asks what stops the wrong adult taking
a child. The order is deliberate: window open → may collect → PIN. **The PIN is
checked last on purpose** — a wrong PIN is the only failure that tells an
attacker the earlier answers were right, so a stranger never reaches the
credential.

`can_pickup` on `guardian_student` gets its **first ever reader** here. Being
allowed to see a child's attendance was never the same permission as being
allowed to take them out of the building; the column has existed unread since
the table was created.

### Verifying the refusals rather than trusting them

A passing test on this module proves nothing by itself, so each gate was
**deliberately broken and the suite re-run**: window check, `can_pickup` check,
PIN check, idempotency, the state machine, the requesting-guardian check, the
verifier's fail-closed branch when no PIN exists, the weak-PIN rule, and the
hashing. **Nine mutations, nine detections.** Every gate is load-bearing.

### What the browser found that 1,129 tests did not

The child dropdown listed **every** linked child — including one this guardian
may not collect — and **every option rendered blank**, because the shared
`ListGuardianChildrenAction` returns `first_name`/`last_name` and the page read
`name`. The gate would still have refused the extra child, so this was never an
opening; it was a parent at a school gate being told "no" with no way to know
why, choosing from a list of empty rows.

Fixed with `ListCollectableChildrenAction` — a **new** reader rather than a new
argument on the shared one, which has thirty-odd callers whose shape must not
move. The dropdown and the gate now read the same column. A guardian listed for
no child gets a sentence saying the record is the thing to correct, not an
empty select.

**11 tests, 67 assertions. Walked in a browser: 27 checks, clean.**

Two harness lessons worth keeping, since both produced false failures first:
`artisan serve` needs `--no-reload` plus `PHP_CLI_SERVER_WORKERS` or two
browser contexts deadlock the single-process dev server; and `networkidle`
never settles in this sandbox, because blocked Google Fonts and Translate
requests retry forever. **The first walk reported six defects that were all the
harness checking before the response arrived.** Waiting on text is the only
honest signal.

## 5ce. E18 — arrivals and departures, built against the warning (2026-09-11)

The plan says **"do not build the software until the hardware question is
answered, or it will be a manual log nobody fills."** That warning shaped the
slice rather than blocking it: **the hardware decision picks what presses the
button, not what the row looks like.** A card reader and a member of staff
record the same fact — this child was at the gate, going this way, at this
time.

So `student_movements` carries `source` from its first row and
`RecordStudentMovementAction` is the single writer (rule 11). A card or QR
adapter calls that action with a different source and needs no new table and no
migration. `recorded_by` is nullable precisely so an unattended reader has
somewhere to be: a turnstile has no user id, and the console then names the
device rather than blaming a member of staff who was not there.

**What was deliberately not built is an empty `GateReader` interface with no
implementation.** Rule 4 puts SDKs behind contracts; there is no SDK, and a
contract for a device nobody has purchased is speculative generality.

### Keeping the log fillable

The plan's real risk is a log nobody fills, so the action **never dead-ends the
person at the gate**. Two arrivals in a row are allowed: a child who goes to
the dentist and comes back is in/out/in/out, and a log that starts at 10am has
an `out` with no `in`. Refusing the second would leave somebody unable to
record what they are looking at, and staff who cannot record what they see stop
using the system. The console shows the child's **current state** instead, so
the operator sees the oddity and decides. The one thing refused is an accident:
the same direction inside two minutes is a double tap, not a child who left and
returned.

Search-driven rather than a roster of the whole school — somebody at a gate
deals with one child at a time, and three letters beat nine hundred names.
Reuses `SearchRosterCandidatesAction` (rule 11), which already flags
**indistinguishable names**: two pupils called Ibrahim Nasir at a gate is
exactly when a system must say *check the number*.

A mistake is **taken back, not deleted**. A family told their child left at
13:40 and later told they did not is owed an explanation a deleted row cannot
give. Staff see the correction struck through; families see nothing, because a
list of retracted times answers a question they never asked.

**9 tests, 58 assertions. Full suite 1138 green. Walked in a browser: 17
checks, clean.**

### A harness fault worth recording

The walk assertions read `page.textContent('body')`, which **includes the
Inertia props JSON in the page's script tag**. A name can therefore "appear on
screen" while being only in the payload. It produced one false failure here —
and could have produced a silent false **pass** on any check for data-derived
text. Switched to `innerText`, and **E8's walk was re-run under the corrected
method to confirm none of its passes were spurious**: still clean.

## 5cf. E21 — student work showcase, built around the wrong parent (2026-09-11)

Photograph paper work, route it to the right parent. EduPage reads the pupil's
handwritten name with AI; the plan's judgement is that a v1 without that is
*"photo + pick the pupil, which is most of the value at a fraction of the
cost"* — and rule 8 forbids AI here anyway.

**The plan also names the failure mode EduPage's own documentation records:
work sent to the wrong parent.** That is what the slice is built around, not
the photo store.

Because nothing reads the handwriting, **picking the pupil is the only thing
standing between a photo and the wrong family**, so it is required rather than
defaulted, and the upload form names the family before you save: *"this will go
to Mariyam Hassan's family."*

Correcting it is a first-class verb, not an edit form:

- `ReassignStudentWorkAction` moves the photo and the first family stops seeing
  it **at once** — list and image alike; the old family gets a 404 on the image
  they could fetch a second earlier.
- `student_work_reassignments` records every move with who and when. *"Which
  family saw my child's work, and for how long?"* is a question a school will
  be asked, and a silently-updated `student_id` cannot answer it.
- The staff card shows **how many times a photo has been moved**. One that has
  bounced twice is worth a second look before anybody trusts it.
- A no-op move is refused rather than writing an audit row that makes the
  history harder to read.

Separately, `HideStudentWorkAction` covers the *other* urgent case — a photo
that caught another child's work or face in frame. Hidden, not deleted, and
reversible; families lose both the row and the image immediately, staff keep
both because it is their record.

The photo is a private media id (rule 4, as E13c and E15 already do): a
photograph of schoolwork carries a child's handwriting and usually their name.
The family scope comes from **who the viewer is, never from the request** — a
guessable id in a URL would be a second way for work to reach the wrong parent.

**7 tests, 66 assertions. Walked in a browser: 24 checks, clean** — including
the full wrong-parent correction with two real family accounts open at once,
and a check that the images are actually rendered pixels rather than broken
icons.

## 5cg. E19 — sensitive information, with the decisions left open (2026-09-11)

**The plan is blunt about this one:** *"Needs a policy decision before a
schema: who may read, who may write, retention, and whether it is exportable.
This is the one module where building first and deciding later is actively
wrong."*

That warning is respected by making every undecided question **fail closed and
stay visible**, rather than by picking answers quietly.

### ⚠ One decision was made on the Institute's behalf — please confirm it

`RoleSeeder` grants `admin` `Permission::all()`. **Adding a `sensitive.read`
permission and doing nothing else would have handed every admin account every
child's health note by accident** — the blanket grant would have decided the
policy before anybody read the question.

So the route group is narrower than every other admin group in the app:
`role:super_admin|headmaster` **plus** `can:sensitive.read`, and the migration
grants those permissions to `super_admin` and `headmaster` only. **`admin` is
deliberately excluded from both.** A test asserts this holds even for an admin
account that genuinely holds every permission in the system.

**Widening this is one line in a migration. Narrowing it after the fact is a
disclosure.** That asymmetry is the whole reason the conservative default was
chosen, and it is the one thing in Wave 4 that should be confirmed rather than
inherited.

### The other two questions

- **Retention** — `review_on` lets a human say when a note should be looked at
  again, and `archived_at` takes it out of use. **Nothing deletes
  automatically, and there is no job that will.** Auto-expiring a child's
  allergy on a guessed retention rule is far worse than keeping it a term too
  long.
- **Exportable** — no CSV, deliberately departing from the repo convention that
  every listing gets one. A convention that exists to help an office move data
  is the wrong default for the one table where *"somebody exported it"* is the
  incident. **A test asserts the route list contains no export and no
  `portal.*` route**, so a later slice cannot add one for consistency without
  someone noticing.

### What is recorded regardless of the policy

`sensitive_note_views` logs **every read** — including reads that find nothing,
because *"somebody went looking"* is itself worth knowing. The log is rendered
**on the same screen as the notes**, not in an audit page nobody opens: someone
about to read a child's welfare record should see that their own name will join
that list. Opening the search box logs nothing; choosing a pupil does.

Editing is narrow: only the author may change their own note. A welfare note is
a professional observation with a name on it, and a second reader who disagrees
adds their own, so the disagreement becomes part of the record rather than
replacing it.

**10 tests, 41 assertions. Walked in a browser: 15 checks, clean** — including
an admin account holding every permission being refused with a 403.

## 5ch. E16 — Circulation, and why it is not the Library (2026-09-11)

Physical lending: books and textbooks with labels on them. **Not the L-track
Library**, which is a digital reader and bookstore. The plan asks that the two
*"never get confused in code or nav"*, so Circulation has **its own domain**,
its own tables and its own nav entry — and a test asserts the separation rather
than trusting it.

`book_titles` (the work) → `book_copies` (the object on the shelf, each with an
**accession number**) → `loans`. Loans attach to a **copy, never a title**:
*"who has our second copy"* is the question a librarian actually asks.

### Decisions worth recording

- **Accession numbers are allocated, never reused.** The next number continues
  past the highest that ever existed, including withdrawn copies. Reissuing one
  makes an old loan record point at a different book.
- **`LendCopyAction` is the only writer of a loan** (rule 11), and it locks the
  copy row inside the transaction. Two people scanning at once on the first day
  of term is exactly how a book ends up on two borrowers' records.
- **Return is by accession number, not loan id.** Somebody hands over a book and
  you scan it; nobody at a return desk knows which loan row it is, and asking
  them is how a return desk stops being used.
- **Bulk issue succeeds partially, on purpose.** Forty textbooks are forty
  independent facts, and one pupil who already holds a copy must not leave the
  other thirty-nine unissued. The result names who missed out and why, so the
  librarian acts on a short list instead of re-running the class.
- **Overdue is derived from `due_on`**, so nothing has to run overnight to keep
  a flag honest.
- **Borrowers are two nullable columns, not a morph** — no type column to
  disagree with the id, real foreign keys on both, and rule 11's single student
  record still holds.

### Code 39 rather than QR

The plan says *"QR or barcode label"*. At a desk the reader is a cheap USB wedge
scanner, not a camera: every one reads **Code 39** and types the accession
number straight into the focused field. QR would have meant adding a dependency
to draw it and a camera to read it, for a worse desk workflow. Rendered as
inline SVG in ~60 lines of pure PHP — **no package added**.

Two defects the tests caught in that renderer, both mine: the human-readable
number on the label was taken from the *input* rather than from what the bars
actually encode, so a value containing the `*` sentinel would have printed a
number the scanner disagreed with; and `days_overdue` used Carbon's signed diff
and reported negative days. **A label whose printed number differs from its
barcode is worse than no label.**

**13 tests, 104 assertions. Full suite 1168 green. Walked in a browser: 22
checks, clean** — including that the label sheet draws real bars (50 rects per
barcode) whose `aria-label` matches the printed accession number, and that a
second issue of an already-out copy is refused at the desk.

## 5ci. E7 — linked accounts and the switcher (2026-09-11)

A teacher who is also a parent has two real accounts here, and until now had to
log out and back in to see their own child's attendance.

**The plan's other half was already built.** It said to *"fix the ordering bug
first, separately"* — `DashboardController` checking `isTeacher()` before
`isParent()`. That `elseif` chain is long gone: `ResolveDashboardLandingAction`
replaced it and shares the unchosen identity as `auth.alternate`. **That was
the seventeenth time `EDUPAGE_FEATURES_PLAN.md` recorded shipped work as
missing**, and it is corrected in this slice.

### The link is a claim that two accounts are one human

So it is only ever created by proving both: you are signed into the first and
supply the second's credentials. **No administrator can create one**, because
an administrator cannot know that two accounts are the same person — and a link
they could create would be an impersonation tool under a friendlier name.

Rows are reciprocal, and unlinking removes both. A one-way link would let an
account reach one that cannot reach back, which is impersonation again.

Switching is a **real login, not impersonation**: the target's own roles apply
in full. That is what keeps the E6 rule the plan asks to preserve — somebody who
switches into a pupil account holds a pupil's roles, so they cannot confirm
anything as a guardian, and no special-casing was needed to arrange it.

The link form takes a password, so it is **rate limited exactly as login is**,
and every refusal returns the **same sentence** — "no such account" told apart
from "wrong password" is an account-enumeration oracle, and a signed-in attacker
probing identifiers is the likeliest use of that form. Failed attempts are
logged as well as successful ones, since the failures are the interesting ones.

`ResolveUserByIdentifierAction` was extracted from `LoginRequest::authenticate()`,
which resolved the user and logged them in in one breath. Linking needs the
first half without the second, and copying three identifier types and their
hard-won asymmetries would have created a second source of truth for the most
security-sensitive lookup in the app (rule 11). Login's own 25 tests still pass
unchanged.

### Verifying the refusals, and finding three that were not verified

Eight mutations were applied on purpose. Five were caught immediately; **three
survived**, which is the useful part:

- **unverified links were accepted** — `verified_at` had no test at all;
- **session rotation was untested**;
- **the password-confirmation test was vacuous** — it asserted the timestamp
  was absent *without ever setting it*, so it passed against a build that
  cleared nothing.

All three now have real tests. Chasing the session one also found **dead code**:
the explicit `regenerate()` did nothing, because `Auth::login()` already
migrates the session. It is removed, and the comment now credits the guard
rather than a line that was not doing the work.

### What the browser found

The switch worked and **said nothing**. `/dashboard` is a pure router — it works
out where you belong and redirects again — and that hop consumed the flash aimed
at the destination. Every test asserted the redirect; none asserted what the
person reads at the end of it. `DashboardController` now reflashes when it
forwards, which fixes it for every caller, not just this one.

### One change outside the slice

**`phpunit.xml`'s memory limit is raised 512M → 1G.** The suite passed at 1,168
tests and failed at 1,181 — and had already begun failing on `main` before this
slice added anything, so whether a run passed had become a coin toss. Nothing
leaks; the suite has simply grown. Left alone it would have broken CI for
whoever shipped next.

**14 tests, 84 assertions. Full suite 1182 green. Walked in a browser: 17
checks, clean.**

## 5cj. E10c — absence reasons the school defines for itself (2026-09-11)

The five reasons a family could give were a **MySQL enum** —
`illness, medical_appointment, family_emergency, religious, other` — hardcoded
in the column, again in `PortalAbsenceNoteController`'s list, and a third time
in its validation rule. Adding "bereavement" meant a migration and a deploy.

More than naming, each reason now carries **what it does**:

- **`excuses_absence`** — does approving the note clear the register? That was
  `absence_notes.affects_attendance`, a per-note boolean defaulting to true,
  which meant the policy was decided one note at a time by whoever filled the
  form. It belongs to the type.
- **`requires_evidence`** — must a document be attached? This is the honest
  half of the plan's *"can't they be falsified?"*: you cannot stop a parent
  writing what they like, but you can require a document and record who
  accepted it. The family is told **before** they type, not after.

A used reason is **retired, not deleted**, and its code never changes — old
notes point at it, and rewriting it would restate why a child was away last
term.

### Three things the tests and the browser found

1. **The legacy enum could not hold a custom code.** Keeping `type` in step
   (rule 9 — this is the deploy that stops *reading* it, not the one that drops
   it) silently truncated `unauthorised_holiday`, so the old column would have
   described the note as a reason nobody chose. Widened to `varchar(40)`:
   lossless, nothing dropped, every existing value still valid.
2. **`absence_type_id` was silently dropped on create** — missing from
   `AbsenceNote::$fillable`, so mass assignment discarded it and the id came
   back null with no error anywhere.
3. **A new reason was spliced into the middle of the list**, because
   `sort_order` defaulted to 0 and tied with *illness*. The browser showed
   "Illness, Unauthorised holiday, Medical appointment" — an order nobody
   chose. New reasons now go to the end.

**The suite also caught a contract break I had made**: requiring
`absence_type_id` stopped `AbsenceNoteTest`, which posts the old `type` code.
That break would have silently stopped any client not yet redeployed — the
mobile scaffold included. **Both fields are accepted** for the transition, and
that now has its own test.

**8 tests, 49 assertions. Full suite 1190 green. Walked in a browser: 17
checks, clean.**

### Audit note

A full re-audit of `EDUPAGE_FEATURES_PLAN.md` against tables, routes and
actions found **E1, E2, E3, E6, E11 and E13 all built** while the plan's
summary listed them as missing. E11's own header still said "⚠ HALF BUILT"
while a correction lower in the same document said it was done — the document
was contradicting itself. Both are corrected.

**What remains on the EduPage track is the E10 rounding policy, and nothing
else.** That one needs a design decision first: `attendance` carries
`check_in_time`/`check_out_time` but no lesson duration, so there is no
part-lesson for a rounding rule to round.

## 6. Out of scope (unchanged)

Hifz behaviour frozen. Deploy 3 not executed. Track B leftovers B1–B4 merged (#102–#105). Phase 3 C1–C3 merged (#106–#108). D1–D3 portal composition merged (#109–#111). W1.1–W1.6 merged (#112–#117). W2.1–W2.5 merged (#118, #119, #121, #124, #126). W3 prayer times is this PR (#128). After merge: **Phase E complete**.

**Phase F (Hifz → engine) is built through F4** (2026-08-27, #131–#134, ADR-025)
— F0 components, F1 halaqa mirror gate, F2 structure mapping, F3 engine-keyed
§52.19–52.22, F4 non-AI dashboards. **F5 (retirement) is gated by ADR-025** and
cannot start until the frozen Blade app is replaced: it remains the only UI for
three-lane session-record entry, assignments (§52.18) and milestone approval, so
the Quran dataset models move in the same slice that deletes the Blade app,
never before. This line previously read "next is F1 (Hifz → engine)", which was
stale by a week.

The EduPage track is renamed **E1–E22** in `docs/EDUPAGE_FEATURES_PLAN.md` to
end a genuine collision: "F1" meant both ROADMAP Phase F slice 1 (shipped) and
the EduPage portal home (not started).

**⚠ That plan is not yet trustworthy and must be audited before any E-slice
starts.** Attempting E9 (staff absence → substitution wiring, estimated 3 days)
on 2026-09-04 found it **already built end to end**: `SchoolRequest` →
`ReviewSchoolRequestAction` → `RequestHandlerRegistry` →
`HandleStaffLeaveApprovalAction`, which calls HR's `ApproveStaffLeaveAction`
and then `RecordApprovedTeacherLeaveAction` to create the `TeacherAbsence` and
generate `SubstitutionRequest` rows per affected period, idempotently. Spot
checks then found the same error on more slices: **E5** (requests/approvals —
generic engine, 5 types, submit/review/export routes, permissions and pluggable
handlers all exist), **E4** (`Announcement` model + routes), **E11**
(`CalendarDay`), **E22** (`UserNotification`, `NotificationTemplate`, `Device`).
Cause: the plan was written from the EduPage documentation with only a partial
codebase audit — the slices that were checked (`Message`, `LessonLog`,
`Timetable`, `ComposePortalHomeAction`) are sound; the rest were inferred. The
parity doc's "no general request/approval workflow and no chaining into
substitutions" is flatly wrong.

**The audit ran on 2026-09-04.** Result: **8 of 22 slices were already built**,
all mis-marked in the same direction (shipped features recorded as missing) —
E4 (`AnnouncementController`, surfaced in the portal dashboard), E5 (the whole
requests engine), E9 (the full chain), E11 room booking, E12
(`MeetingSlotController`, 10 routes), E14 (report-card templates), E20
(`CompetencyController`), E22 (`NotificationController` + `UserNotification`).
Seven parity rows corrected inline; the remaining eight ❌ rows were
re-verified and are sound. The check was run **in both directions** — three
false "found" hits were discarded as grep artefacts, so the missing list is as
trustworthy as the built list.

Two findings change design rather than status: **`assignments` /
`assignment_submissions` exist** (the legacy module behind #184's dead code), so
E3's assumption that `lesson_logs.homework` is the only homework concept is
wrong and which to extend is now an owner decision; and **E11 is half-built**
(room booking ships, the staff calendar does not).

**Wave 4 is now built out.** E15 (#241), E17 (#242), E8 (#243), E18 (#244),
E21 (#245), E19 (#246) and E16 all shipped on 2026-09-11, each with tests, a
browser walk and its own PR. The plan's gating notes were respected in design
rather than used to defer: E18 carries `source` so a card reader is a binding
and not a rewrite, E19 fails closed on every question the policy has not
answered, and E16 is named Circulation so it never merges with the L-track
Library. **The family-facing core that remains is E1 + E2 + E3**, and only
E1's teachers-or-not decision blocks starting.

**Operator:** apply branch protection (`docs/BRANCH_PROTECTION.md`). Confirm or reject `docs/migrations/s11-deploy-3-cleanup-proposal.md`.

**Qur'an A.4b (later):** switch offering-session reads to `offering_halaqa_session_links` after operators confirm dual-write. Then Hifz cleanup (deploy 3). Keep `QURAN_HALAQA_DUAL_WRITE` off until verified.

**One thing to confirm rather than inherit (E19):** health and welfare notes
are readable by `super_admin` and `headmaster` only. `admin` is deliberately
excluded, because `RoleSeeder`'s blanket `Permission::all()` would otherwise
have granted every admin account access by accident. Widening it is one line in
a migration; narrowing it later is a disclosure.

*(This closing section previously listed pronunciation AI, Capacitor, W1–W3 and
the L-track as later work. All five have shipped; the line was stale by
several weeks and is removed rather than corrected in place.)*
