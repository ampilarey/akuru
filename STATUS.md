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

## 6. Out of scope (unchanged)

Hifz behaviour frozen. Deploy 3 not executed. Track B leftovers B1–B4 merged (#102–#105). Phase 3 C1–C3 merged (#106–#108). D1–D3 portal composition merged (#109–#111). W1.1–W1.6 merged (#112–#117). W2.1–W2.5 merged (#118, #119, #121, #124, #126). W3 prayer times is this PR (#128). After merge: **Phase E complete**; next is **F1** (Hifz → engine). Do not start F in the same turn as the Phase E report.

**Operator:** apply branch protection (`docs/BRANCH_PROTECTION.md`). Confirm or reject `docs/migrations/s11-deploy-3-cleanup-proposal.md`.

**Qur'an A.4b (later):** switch offering-session reads to `offering_halaqa_session_links` after operators confirm dual-write. Then Hifz cleanup (deploy 3). Keep `QURAN_HALAQA_DUAL_WRITE` off until verified.

**Arabic B / Qur'an B / later:** pronunciation AI, Capacitor, W1–W3, L-track.
