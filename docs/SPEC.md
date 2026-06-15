# Project Specification: General Learning Platform and Course Builder

> **Updated version:** This Markdown file includes the later amendments for Phase 1A/1B split, revised block types, immutable lesson revisions, offering content pinning, enrollment concurrency, domain boundary enforcement, and replaceable service interfaces, the merged Arabic Skills + Local AI module, and the future Qur’an/Hifz Recitation module. The Arabic teaching plan and Qur’an/Hifz plan are merged as platform modules, not as separate duplicate websites.


## 1. Project Purpose

Build a **general learning platform and course builder** for Akuru Institute or any education/training business.

This platform is **not only for Arabic** and **not only for self-learning**.

It must support different subjects and different delivery modes, including:

- Self-learning courses
- Face-to-face courses
- Live online courses
- Blended courses
- Hybrid courses
- Short courses
- Certificate courses
- Kids courses
- Adult courses
- Staff training
- Professional training
- Exam preparation

The platform should support courses such as:

- Arabic language
- Dhivehi language
- English language
- Qur’an understanding
- Islamic studies
- Fiqh
- Seerah
- School subjects
- Umrah training
- Customer service training
- Food safety training
- Business training
- Any other admin-created subject

These are examples only.

The system must not hardcode any subject, curriculum, lesson plan, vocabulary, question, assessment, delivery mode content, or fixed course structure.

The goal is to build a **reusable learning platform engine** where admins and course creators can create many different courses and offer them in different modes.

---

## 2. Core Design Principle

Use this core model:

```text
Course = reusable learning content / course template
Course Offering = how, when, where, and in what mode that course is delivered
```

Example:

```text
Course:
Arabic Foundation

Offerings:
1. Arabic Foundation - Self Learning
2. Arabic Foundation - Face-to-Face Batch 1
3. Arabic Foundation - Live Online Evening Batch
4. Arabic Foundation - Blended Weekend Batch
5. Arabic Foundation - Hybrid Batch
```

This means one course can be reused many times without duplicating the full curriculum.

The course contains:

- Modules
- Lessons
- Content blocks
- Activities
- Assessments
- Glossary/terms
- Completion rules

The course offering controls:

- Delivery mode
- Batch
- Schedule
- Teacher
- Location
- Online meeting link
- Seat limit
- Start/end date
- Enrollment window
- Price override
- Attendance rules
- Access rules
- Certificate rules

---

## 3. Platform Scope

The codebase should provide:

- User management
- Roles and permissions
- Course builder
- Course offering management
- Delivery mode support
- Session scheduling
- Attendance foundation
- Content block system
- Media handling
- Enrollment
- Lesson player
- Student dashboard
- Progress tracking
- Unlock rules
- Completion rules
- Activities
- Assessments
- Teacher review
- Question bank
- Glossary/term bank
- Certificate-ready structure
- Payment-ready structure
- Reports-ready structure
- PWA-ready web app
- Mobile app path using Capacitor later
- Future Arabic Skills module
- Future local/offline pronunciation AI module
- Future Qur’an/Hifz Recitation module

The Arabic teaching plan must be merged as a module inside this platform, not built as a second duplicate learning website.

The Qur’an/Hifz plan must also be merged as a separate future module inside this platform, not mixed into the Arabic Skills module and not built as a second duplicate LMS.

The dashboard should allow admins/course creators to create the actual learning content.

All content must come from the database.

Do not hardcode course content in:

- Controllers
- React components
- Views
- Seeders used in production
- Domain services
- Tests
- Frontend constants
- Config files

---

## 4. Main Learning Structure

Use this structure:

```text
Course
  → Module / Section
    → Lesson
      → Content Blocks
      → Activities
      → Assessments
      → Progress Rules
      → Completion Rules

Course
  → Course Offerings
    → Sessions
    → Enrollments
    → Attendance
    → Offering-specific access/certificate rules
```

A course creator should be able to build a complete course from the dashboard.

An admin should be able to offer that course in different modes.

A student should be able to:

- Register/login
- Enroll in eligible offerings/courses
- Open course lessons
- Study content
- Attend sessions if required
- Complete activities
- Submit assignments
- Take assessments
- Receive teacher feedback
- Track progress
- Receive certificate if eligible

---

## 5. Technology Stack

### Backend

Use **Laravel 12**.

### Database

Use **MySQL**.

### Architecture

Use a **modular monolith**.

Do not build microservices.

### Frontend

Use **Inertia.js with React** on top of Laravel 12.

Do not build the system as Blade-only views.

Blade may be used only for the base Inertia shell/layout where necessary.

Interactive features must be React components.

Required React-based features:

- Student dashboard
- Lesson player
- Course builder
- Content block builder
- Drag-and-drop block reordering
- Module/lesson ordering
- Media upload UI
- Audio recording UI
- Activity player
- Assessment player
- Course offering manager
- Session scheduler
- Attendance UI later
- Teacher review screens

Use **Vite** for asset bundling.

### Mobile

The web app must be **PWA-ready from day one**.

A future mobile app should be built by wrapping the existing web app with **Capacitor**.

The interface must work well in:

- Desktop browser
- Mobile browser
- Installed PWA
- Capacitor app shell later

### API Readiness

Even though Inertia does not consume a normal JSON API for every page, all business logic must live in domain services/actions.

Controllers must be thin.

Inertia controllers and future API controllers must call the same services.

Adding `/api/v1` endpoints later should require only thin controllers, not duplicated business logic.

---

## 6. Frontend and Mobile Strategy

### 6.1 Web Frontend

Use:

- Laravel 12
- Inertia.js
- React
- Vite

Do not use Blade-only views for interactive features.

The following must be React components:

- Lesson player
- Student dashboard
- Course builder
- Content block builder
- Drag-and-drop reordering
- Audio recorder
- Activity player
- Assessment player
- Course offering builder
- Session schedule manager
- Teacher review interface

### 6.2 PWA Requirements

The web app must be PWA-ready from day one.

Implement:

- Service worker
- Web manifest
- Installable app behavior
- Responsive layouts
- Mobile-first lesson player
- Mobile-friendly student dashboard
- Touch-friendly controls
- Graceful handling of temporary connection drops

The lesson player and student dashboard must work well on small screens.

### 6.3 Mobile App Path

The mobile app will be built later by wrapping the existing web app using Capacitor.

To keep this path clean:

- All UI must be fully responsive.
- All UI must be touch-friendly.
- Avoid browser-only APIs without fallbacks.
- Keep web/native differences behind a single platform abstraction layer.
- Do not scatter browser/native detection logic across components.
- Audio recording must use a replaceable recorder interface.
- MediaRecorder can be used on web.
- A Capacitor native audio plugin can replace it later behind the same interface.

### 6.4 Platform Abstraction Layer

Create a small frontend platform abstraction layer for environment-specific features.

Example responsibilities:

- Audio recording
- File picking
- Download handling
- Sharing later
- Push notifications later
- Device capability detection
- Storage/cache helpers later

Do not spread platform-specific logic through React components.

### 6.5 React Native Fallback Path

If Capacitor later becomes limiting, the upgrade path should be React Native consuming the Laravel API.

This is one reason React is mandatory instead of Vue.

The team should be able to reuse:

- React thinking
- Component structure patterns
- State management approach
- API contracts
- Validation behavior
- UI design language

Do not assume Capacitor is the only possible future app path.

### 6.6 RTL on Web, PWA, and Mobile

React components must use logical CSS properties throughout.

Use:

- `margin-inline`
- `padding-inline`
- `inset-inline`
- `border-inline`
- `text-align: start`
- `text-align: end`
- `inline-size`
- `block-size`

Avoid hardcoded:

- `left`
- `right`
- `margin-left`
- `margin-right`
- `padding-left`
- `padding-right`

Arabic and Dhivehi/Thaana must render correctly in:

- Browser
- PWA
- Capacitor shell later

Test Arabic and Dhivehi/Thaana rendering inside the Capacitor shell early.

Bundle proper Thaana-capable fonts instead of relying only on device defaults.

### 6.7 State and Data

Use Inertia shared props for:

- Auth user
- Roles/permissions summary
- Locale
- Direction
- Platform settings
- Flash messages

For complex client-side state, use lightweight React state management.

Allowed:

- React `useState`
- React `useReducer`
- Zustand where useful

Do not add Redux.

Use local state or Zustand for:

- Lesson player block navigation
- Activity attempt state
- Assessment attempt state
- Audio recorder state
- Draft answer state
- Temporary upload state
- Offering/session builder state

Persist in-progress activity and assessment answers to the server periodically so a mobile connection drop does not lose student work.

Autosave should be server-backed for important work, not only browser local storage.

---

## 7. Supported Languages and Direction

Support from day one:

- English
- Dhivehi
- Arabic

Arabic and Dhivehi/Thaana must support RTL from the first component.

The application must store all user-facing strings in translation files.

Do not retrofit localization or RTL later.

Each course may have its own course language.

The platform UI language and course content language are separate concepts.

Example:

- A user may use the UI in Dhivehi.
- The course may be Arabic.
- Another course may be English.
- Another course may be staff training in Dhivehi.

---

## 8. User Roles

### 8.1 Super Admin

Full system control.

Can manage:

- System settings
- All users
- Roles and permissions
- All courses
- All course offerings
- All sessions
- All enrollments
- All reports
- All payments
- All media
- All certificates
- All platform settings

### 8.2 Admin

Manages daily operations.

Can manage:

- Students
- Parents/guardians
- Teachers
- Course creators
- Courses
- Course offerings
- Sessions
- Enrollments
- Attendance
- Reports
- Certificates
- Announcements
- Manual payments later

### 8.3 Course Creator

Creates learning content.

Can manage:

- Assigned courses
- Modules
- Lessons
- Content blocks
- Activities
- Assessments
- Question bank items
- Glossary/term items
- Draft course content

Course creators should not publish courses directly unless permission is granted.

### 8.4 Dean / Supervisor

Quality-control role.

Can:

- Review submitted courses
- Approve courses
- Reject courses
- Request changes
- Review assessments
- Review academic/training quality
- Review course offerings where needed
- View academic reports

### 8.5 Teacher / Instructor / Reviewer

Teaches or reviews student work.

Can:

- View assigned course offerings
- View assigned sessions
- View enrolled students
- Mark attendance
- View pending submissions
- Review written work
- Review audio/voice recordings
- Review uploaded files
- Give written feedback
- Upload correction audio
- Give score
- Mark passed/failed
- Request resubmission

### 8.6 Student

Learns through the platform.

Can:

- Register/login
- View available courses/offerings
- Enroll in eligible courses/offerings
- Study lessons
- View schedule if course is scheduled
- Join online session if allowed
- View physical location if face-to-face
- Complete activities
- Submit assignments
- Take assessments
- View progress
- View attendance
- View teacher feedback
- Download certificates

### 8.7 Parent / Guardian

For children’s courses.

Parent dashboard can ship later, but relationship structure must be built in Phase 1.

Later, parents can:

- View child courses/offerings
- View child progress
- View attendance
- View scores
- View feedback
- View certificates
- View payment status

---

## 9. Parent-Child Relationship

Create the relationship model in Phase 1.

Use a `guardian_student` pivot table.

It must support:

- Multiple children per guardian
- Multiple guardians per child
- Relationship type
- Consent status
- Verification status
- `verified_at`
- `created_by`
- Notes

Example relationship types:

- Father
- Mother
- Guardian
- Sponsor
- Other

Do not assume one parent has only one child.

Do not assume one child has only one guardian.

---

## 10. Course Management

## 10.1 Course Meaning

A course is the reusable learning content/template.

It should not be treated as only one scheduled batch.

One course can have many offerings.

### Example

```text
Course:
Food Safety Training

Offerings:
- Self-learning course for all staff
- Face-to-face training for new kitchen staff
- Live online refresher session
```

### 10.2 Course Fields

Each course should include:

- Title
- Slug
- Description
- Short description
- Cover image
- **Subject** (hierarchical — leaf or, if admin allows, non-leaf node; see §10.4)
- Course language
- Course type
- Default access type
- Default price
- Estimated duration
- Difficulty level
- Status
- Published date
- Created by
- Approved by
- Certificate enabled/disabled
- Default completion rules
- Default unlock rules
- Default enrollment rules
- Metadata JSON
- Timestamps
- Soft deletes

**Audience** and **Level** (§10.5–10.6) are **not** duplicated on every course row when the same template is offered to different groups. The reusable **course** holds content; each **`course_offering`** (§11) carries its own `audience_id` and `level_id` — e.g. one *Arabic Nahw* course with offerings *Level 1 / Kids* and *Level 2 / Adults* without creating two course records.

### 10.3 Course Slugs

Course slugs must be unique platform-wide.

Examples:

```text
basic-arabic
food-safety-training
umrah-guide-course
```

### 10.4 Subjects (hierarchical)

> **Merge note:** Earlier drafts split **Categories** (§10.4) and **Subjects** (§10.5) as two flat admin lists. That overlap is removed. **Subject** is now the single hierarchical browse axis (parent/child tree). **Audience** (§10.5) is a separate flat dimension. **Level** (§10.6) stays flat and attaches to **offerings**, not to duplicate courses.

Subjects must be **admin-managed**, **trilingual** (`name_en`, `name_dv`, `name_ar`), with `slug`, nullable `parent_id` (self-referencing tree), `sort_order`, and `active`. A course attaches to a **leaf** subject by default; admins may allow attachment to a non-leaf node.

**Example tree** (seed examples only — admin-editable, never hardcoded):

```text
Quran
  ├── Hifz
  ├── Tajweed
  ├── Qira'ah
  └── Tafseer
Arabic
  ├── Nahw
  ├── Sarf
  ├── Balagha
  └── Conversation
Islamic Studies
  ├── Fiqh
  ├── Aqeedah
  ├── Seerah
  └── Hadith
Dhivehi
English
```

### 10.5 Audience

Audience is a **flat**, admin-managed dimension — separate from the subject tree. Trilingual (`name_en`, `name_dv`, `name_ar`), `slug`, `sort_order`, `active`.

**Example values** (seed examples only — admin-editable, never hardcoded):

- Kids
- School children
- Adults
- All

Audience is stored on **`course_offerings`** (§11), so the same course template can run for different audiences without duplicating content.

### 10.6 Course Levels

Levels must be admin-managed.

Examples:

- Foundation
- Beginner
- Intermediate
- Advanced
- Level 1
- Level 2
- A1
- A2
- B1

These must not be hardcoded.

**Offerings:** `level_id` on `course_offerings` combines with `audience_id` (§10.5) to describe *who* and *how advanced* a batch is — e.g. Nahw Level 1 for Kids vs Nahw Level 2 for Adults on the same course template.

### 10.7 Course Status Workflow

Courses must support:

```text
Draft → In Review → Published → Archived
```

Rules:

- Draft courses are editable.
- In Review courses are waiting for approval.
- Published courses can have visible offerings.
- Archived courses are hidden from new offering creation unless admin allows.
- Archived courses must not break existing student records.
- Invalid transitions must be rejected.

---

## 11. Course Offerings and Delivery Modes

### 11.1 Why Offerings Are Needed

A course may be delivered in different ways.

Do not store delivery mode only on the course table.

Use course offerings.

```text
Course = reusable content
Offering = delivery mode, batch, schedule, teacher, access, price, and attendance rules
```

Students should normally enroll into an offering when the course has multiple delivery modes or scheduled batches.

For evergreen self-learning courses, the platform may create a default self-learning offering automatically.

---

## 11.2 Delivery Modes

The platform must support:

1. Self-learning
2. Face-to-face
3. Live online
4. Blended
5. Hybrid

### Self-Learning

Student studies independently using the lesson player.

Features:

- No fixed class time required
- Can start anytime if access is allowed
- Progress is based mainly on lessons, activities, assessments, and completion rules
- Teacher review only if teacher-marked submissions are included

### Face-to-Face

Course is delivered physically at a location.

Features:

- Physical classroom/location
- Batch schedule
- Class sessions
- Attendance tracking
- Seat limit
- Teacher assigned
- Optional lesson player support for materials and homework

### Live Online

Course is taught live online.

Features:

- Scheduled live sessions
- Meeting link support
- Teacher assigned
- Attendance tracking
- Optional recording link
- Optional lesson player support for materials and homework

### Blended

Course combines self-learning and teacher-led sessions.

Features:

- Self-learning lesson player
- Face-to-face and/or live online sessions
- Attendance tracking
- Student progress can include lessons, assignments, and attendance

### Hybrid

Course allows students to attend the same scheduled session either physically or online.

Features:

- Physical location
- Online meeting link
- Attendance mode: physical or online
- Teacher can mark how student attended

---

## 11.3 course_offerings Table

Create a `course_offerings` table.

Fields:

- ID
- Course ID
- Audience ID (§10.5)
- Level ID (§10.6)
- Title
- Slug
- Delivery mode
- Batch code nullable
- Status
- Teacher ID nullable
- Supervisor ID nullable
- Starts at nullable
- Ends at nullable
- Enrollment opens at nullable
- Enrollment closes at nullable
- Timezone
- Location name nullable
- Location address nullable
- Online meeting URL nullable
- Online meeting provider nullable
- Seat limit nullable
- Price override nullable
- Access rules JSON nullable
- Attendance required boolean
- Attendance minimum percentage nullable
- Certificate rules JSON nullable
- Created by
- Approved by nullable
- Published at nullable
- Timestamps
- Soft deletes

### Offering Slugs

Offering slugs must be unique within the course.

Example:

```text
self-learning
batch-1-saturday
online-evening-batch
```

---

## 11.4 Offering Status Workflow

Course offerings must support:

```text
Draft → Open → In Progress → Completed → Cancelled → Archived
```

Rules:

- Draft offerings are not visible to students.
- Open offerings can accept enrollment if rules allow.
- In Progress offerings are currently running.
- Completed offerings are finished but historical records remain.
- Cancelled offerings keep historical/admin records.
- Archived offerings are hidden from new enrollment.
- Invalid transitions must be rejected.
- Offerings must not be hard-deleted if they have enrollments, attendance, attempts, progress, payments, or certificates.

---

## 11.5 course_offering_sessions Table

For face-to-face, live online, blended, and hybrid offerings, create a `course_offering_sessions` table.

Fields:

- ID
- Course offering ID
- Title
- Description nullable
- Session type
- Starts at
- Ends at
- Timezone
- Location name nullable
- Location address nullable
- Online meeting URL nullable
- Online meeting provider nullable
- Teacher ID nullable
- Is required boolean
- Recording URL nullable
- Materials JSON nullable
- Timestamps
- Soft deletes

Session types:

- Face-to-face
- Live online
- Hybrid
- Workshop
- Exam
- Review class
- Orientation

---

## 11.6 Attendance

Create attendance tracking for scheduled offerings.

### attendance_records Table

Fields:

- ID
- Course offering session ID
- Student ID
- Enrollment ID
- Status
- Attendance mode nullable
- Marked by
- Marked at
- Notes nullable
- Timestamps

Attendance statuses:

- Present
- Absent
- Late
- Excused
- Pending

Attendance modes:

- Physical
- Online
- Not applicable

---

## 11.7 Enrollment and Offering Relationship

Update course enrollments to support offerings.

`course_enrollments` should include:

- Course ID
- Course offering ID nullable
- Student ID
- Enrollment type
- Status
- Access starts at nullable
- Access ends at nullable
- Completed at nullable
- Progress percentage
- Certificate issued at nullable

For self-learning evergreen courses, `course_offering_id` may be nullable only if the course is directly enrollable.

However, if a course has scheduled batches or multiple delivery modes, students should enroll into a specific offering.

Preferred approach:

- Always create at least one offering.
- Even self-learning courses should have a default self-learning offering.
- Enrollments should link to `course_offering_id` whenever possible.

### Enrollment Concurrency and Seat Limits

If an offering has a seat limit, enrollment must respect the available seat count.

Cancelled, suspended, failed, and rejected enrollments should not count as active seats.

Seat limits must be enforced at the database level, not only in application code.

Use one of these safe approaches:

- Row lock on the offering row inside a transaction.
- Atomic conditional insert/update inside a transaction.
- Database-backed counter with transaction locking.

Do not use unsafe count-then-insert logic without a lock.

Required test:

- Simulate two concurrent enrollments against one remaining seat.
- Exactly one enrollment must succeed.
- The other must fail gracefully with a clear validation/business error.

---

## 11.8 Student Experience by Mode

### Self-Learning

Student sees:

- Lesson player
- Course progress
- Activities
- Assessments
- Optional teacher-reviewed submissions

No schedule is required.

### Face-to-Face

Student sees:

- Course materials
- Class schedule
- Physical location
- Teacher name
- Attendance
- Homework/assignments
- Optional lesson player materials

### Live Online

Student sees:

- Live session schedule
- Join link when allowed
- Teacher name
- Attendance
- Recording link if enabled
- Online materials/homework

### Blended

Student sees:

- Self-learning lesson player
- Scheduled sessions
- Attendance
- Assignments
- Progress combined from lessons, attendance, and assessments

### Hybrid

Student sees:

- Physical location
- Online join option
- Session schedule
- Attendance status
- Teacher marks whether student attended physically or online

---

## 11.9 Admin/Course Creator Offering Requirements

Admin/course creator must be able to:

- Create multiple offerings for one course
- Select delivery mode
- Set batch name/code
- Assign teacher
- Assign supervisor
- Set location
- Set online meeting link
- Set session schedule
- Set seat limit
- Set enrollment opening/closing date
- Set start/end date
- Set price override
- Set attendance requirement
- Set certificate rules
- Duplicate an offering
- Cancel an offering
- Archive an offering
- View enrolled students per offering
- Track attendance for scheduled sessions

---

## 11.10 Reports by Offering

Reports should support:

- Enrollment count by offering
- Attendance by session
- Completion rate by offering
- Revenue by offering later
- Teacher workload by offering
- Student progress by offering
- Dropout/inactive student list by offering

---

## 11.11 Certificate Rules by Offering

Certificate eligibility may depend on offering mode.

Examples:

Self-learning:

- Complete required lessons
- Pass final assessment

Face-to-face:

- Minimum attendance percentage
- Complete assignments
- Pass final assessment

Live online:

- Attend required live sessions
- Complete assignments
- Pass final assessment

Blended:

- Complete self-learning lessons
- Attend required sessions
- Submit required assignments
- Pass assessments

Hybrid:

- Attend required sessions physically or online
- Complete required lessons/assignments
- Pass assessments

---

## 12. Modules / Sections

A course can have many modules or sections.

### Module Fields

- Course ID
- Title
- Description
- Position
- Status
- Unlock rule
- Available from
- Created by
- Updated by
- Timestamps
- Soft deletes

Course creators must be able to:

- Create modules
- Edit modules
- Delete draft modules if safe
- Reorder modules
- Publish/unpublish modules depending on permissions

---

## 13. Lessons

A module can have many lessons.

### Lesson Fields

- Course ID
- Module ID
- Title
- Slug
- Description
- Position
- Estimated duration
- Status
- Revision number
- Current revision ID
- Unlock rule
- Completion rule
- Is preview lesson
- Created by
- Updated by
- Published at
- Timestamps
- Soft deletes

### Lesson Slugs

Lesson slugs must be unique within their course.

Two different courses can have the same lesson slug.

The same course cannot have duplicate lesson slugs.

### Lesson Management

Course creators must be able to:

- Create lessons
- Edit lessons
- Reorder lessons
- Add content blocks
- Add activities
- Add assessments
- Mark lesson as preview
- Set unlock rules
- Set completion rules

---

## 14. Content Block System

Lessons must be built using dynamic content blocks.

Use a single `content_blocks` table.

Do not create separate tables for each block type.

### content_blocks Table

Fields:

- ID
- Course ID
- Module ID
- Lesson ID
- Type
- Position
- Title nullable
- Data JSON
- Settings JSON nullable
- Is required boolean
- Created by
- Updated by
- Timestamps

### Content Block Ownership

Content blocks belong to lessons.

`lesson_id` is the source of truth.

`course_id` and `module_id` on `content_blocks` are denormalized for query performance only.

Whenever a lesson is moved to another module or course, the related `content_blocks.course_id` and `content_blocks.module_id` must be synced automatically.

Use a model observer or service method to guarantee this.

No code may rely on `content_blocks.course_id` or `content_blocks.module_id` unless this sync guarantee exists.

### Block Validation

The `data` JSON must be validated per block type.

Use:

- Form requests
- DTOs
- Data validation classes
- Block-specific validators

Do not allow unvalidated arbitrary JSON.

---

## 15. Content Block Types by Phase

Lessons must be built using configurable content blocks.

Do not create separate tables or separate duplicated code paths for each content direction or language.

### 15.1 Phase 1A Block Types

Phase 1A must implement only the core block types needed to build a usable course and lesson player.

Phase 1A blocks:

1. Text Block

For normal explanation text.

2. Rich Text Block

For formatted notes, headings, bullet points, links, and structured content.

3. Image Block

For diagrams, photos, screenshots, charts, or illustrations.

4. Audio Block

For lectures, listening practice, pronunciation, explanation, or instructions.

5. Video Block

For uploaded video or external video embed.

Large course videos should preferably use external video embeds.

6. PDF Block

For notes, worksheets, reading materials, forms, or handouts.

7. Instruction Block

For homework, project instructions, practice instructions, or task guidance.

### 15.2 Phase 1B Block Types

Phase 1B adds the remaining block types after Phase 1A is stable.

Phase 1B blocks:

1. Glossary / Term Block

For terms, vocabulary, formulas, definitions, concepts, or key points.

This must not be Arabic-only.

2. Dialogue Block

For conversation practice, role-play, customer service training, language learning, or scenario-based lessons.

3. Flashcard Block

For memorization and review.

Can be used for:

- Vocabulary
- Definitions
- Formulas
- Rules
- Questions and answers

4. Download Block

For downloadable resources.

5. Quiz Embed Block

To embed a quiz or assessment inside a lesson.

6. Assignment Embed Block

To embed a teacher-reviewed activity inside a lesson.

### 15.3 Text Direction Rule

Remove `RTL Text Block` as a separate block type.

Text direction and content language are settings on every text-capable block, not separate block types.

Text-capable blocks must support settings such as:

- Content language
- Direction: LTR, RTL, or auto
- Text alignment using start/end
- Optional font preference
- Optional script-specific display settings

Never duplicate block logic only because text direction is different.

Arabic and Dhivehi/Thaana support must be handled through block settings, localization, fonts, and logical CSS, not by creating a separate block type.

---

## 16. Content Block Builder UI

The content block builder must be a React component.

It must support:

- Adding blocks
- Editing blocks
- Reordering blocks
- Duplicating blocks where safe
- Deleting draft blocks where safe
- Previewing blocks
- Validating block data
- Uploading/attaching media
- RTL preview
- Mobile preview where practical

Block reordering must use drag and drop.

Reordering must persist correctly.

The lesson player must render blocks dynamically by:

```text
lesson_id → position → block type
```

No content should be hardcoded inside views or frontend components.

---

## 17. Activity System

Do not build many separate activity systems.

All activity types must use 4 base patterns.

New activity types should be added through configuration of these patterns, not through new hardcoded code paths.

### Pattern 1: Auto-Marked Selection

Examples:

- Multiple choice
- True/false
- Listen and choose
- Image to word
- Word to image
- Choose correct meaning
- Choose correct rule
- Select correct answer
- Matching selection

### Pattern 2: Auto-Marked Text Input

Examples:

- Fill in the blank
- Listen and type
- Short answer with answer key
- Translation with answer key
- Type correct word
- Type formula/result
- Type definition

### Pattern 3: Drag / Arrange Interaction

Examples:

- Match pairs
- Arrange words
- Arrange steps
- Sentence builder
- Ordering process
- Sort items into categories

### Pattern 4: Teacher-Marked Submission

Examples:

- Voice recording
- Audio upload
- File upload
- Image upload
- Written answer
- Essay
- Project submission
- Speaking task
- Reading task
- Practical assignment

### activities Table

Fields:

- ID
- Course ID
- Module ID nullable
- Lesson ID nullable
- Title
- Description
- Pattern type
- Activity type
- Data JSON
- Settings JSON
- Max score
- Passing score nullable
- Is required
- Created by
- Timestamps
- Soft deletes

### Activity Settings

Each activity should support:

- Required or optional
- Practice-only or scored
- Retakes allowed
- Retake limit
- Show/hide correct answer
- Show explanation after attempt
- Lock next lesson until complete
- Teacher review required
- Time limit where applicable

---

## 18. Answer Normalization

For auto-marked text input, comparison must be configurable per activity.

General normalization settings:

- Trim whitespace
- Normalize repeated spaces
- Remove punctuation
- Case-insensitive comparison
- Accept multiple correct answers
- Strict mode
- Lenient mode

Arabic-specific normalization settings:

- Strip tashkeel/diacritics
- Normalize hamza variants
- Normalize alef variants
- Optional taa marbuta tolerance

Arabic normalization must not be global.

It should apply only when the activity configuration requires it.

Examples:

- An Arabic haraka activity may require strict diacritics.
- A beginner Arabic typing activity may ignore tashkeel.
- An English answer may use case-insensitive matching.
- A formula answer may use strict matching.

---

## 19. Assessment System

The system must include an assessment builder.

### Assessment Types

Support:

- Lesson quiz
- Module test
- Placement test
- Final exam
- Listening assessment
- Speaking assessment
- Reading assessment
- Writing assessment
- Practical assessment
- Mixed assessment
- Assignment-based assessment

### assessments Table

Fields:

- ID
- Course ID
- Module ID nullable
- Lesson ID nullable
- Title
- Description
- Assessment type
- Status
- Time limit nullable
- Passing score
- Max score
- Retake limit
- Randomize questions boolean
- Show results boolean
- Show correct answers boolean
- Requires teacher marking boolean
- Settings JSON
- Created by
- Timestamps
- Soft deletes

### Assessment Features

Assessments must support:

- Auto marking
- Teacher marking
- Mixed marking
- Passing mark
- Retake limit
- Time limit
- Randomized questions
- Question bank
- Show/hide correct answers
- Lock next lesson until passed
- Save attempts
- Autosave in-progress answers
- Resume incomplete attempt if allowed

---

## 20. Question Bank

Create a reusable question bank for all subjects.

Questions must not belong permanently to one assessment only.

### questions Table

Fields:

- ID
- Subject ID nullable
- Category ID nullable
- Course ID nullable
- Question type
- Title nullable
- Question text
- Secondary text nullable
- Explanation nullable
- Options JSON nullable
- Correct answer JSON nullable
- Acceptable answers JSON nullable
- Normalization settings JSON nullable
- Difficulty
- Skill tag nullable
- Created by
- Timestamps

### Question Types

Support:

- MCQ single answer
- MCQ multiple answer
- True/false
- Fill in the blank
- Audio question
- Image question
- Matching
- Arrange/order
- Short answer
- Essay
- Teacher-marked answer
- File submission

### Question Attachments

Questions may have:

- Audio
- Image
- PDF
- Video reference

All attachments must go through the centralized media system.

---

## 21. Assessment-Question Pivot

Use `assessment_questions` as a pivot table.

Fields:

- Assessment ID
- Question ID
- Position
- Points override nullable
- Is required
- Timestamps

Questions remain reusable across multiple assessments.

### Attempt Snapshot Requirement

Assessment attempts must snapshot question content at attempt start.

The snapshot must include:

- Question text
- Secondary text
- Options
- Correct answer
- Acceptable answers
- Normalization settings
- Points
- Explanation if needed
- Media references or resolved media metadata where appropriate

Editing a question in the question bank must never alter, corrupt, or change an in-progress or completed assessment attempt.

---

## 22. Glossary / Term Bank

Create a reusable generic term bank.

Do not make it Arabic-only.

Use `glossary_items` or `term_bank_items`.

### glossary_items Table

Fields:

- ID
- Subject ID nullable
- Category ID nullable
- Term
- Transliteration nullable
- Meaning primary nullable
- Meaning secondary nullable
- Description nullable
- Example text nullable
- Example translation nullable
- Tags JSON nullable
- Level ID nullable
- Created by
- Timestamps

This should support:

- Arabic vocabulary
- English vocabulary
- Dhivehi vocabulary
- Islamic terms
- Science terms
- Business terms
- Formulas
- Definitions
- Key concepts

### Glossary Media

Glossary items may have:

- Audio
- Image
- Example audio
- Diagram

All media must use the centralized media system.

### Lesson Glossary Pivot

Use `lesson_glossary_items`.

Fields:

- Lesson ID
- Glossary item ID
- Position
- Is required

---

## 23. Student Enrollment

### course_enrollments Table

Fields:

- ID
- Course ID
- Course offering ID nullable
- Student ID
- Enrolled by nullable
- Enrollment type
- Status
- Access starts at nullable
- Access ends at nullable
- Completed at nullable
- Progress percentage
- Certificate issued at nullable
- Timestamps
- Soft deletes

### Enrollment Types

Support:

- Free
- Manual
- Paid later
- Trial later
- Group later

### Enrollment Statuses

Support:

- Active
- Pending payment
- Pending approval
- Suspended
- Completed
- Cancelled

### Offering Seat Limit Rule

If an offering has a seat limit, enrollment must respect the available seat count.

Cancelled/suspended enrollments should not count as active seats.

---

## 24. Student Experience

### Student Dashboard

The student dashboard must be a responsive React/Inertia page.

It must show:

- Enrolled courses/offerings
- Continue learning
- Upcoming sessions
- Current progress
- Completed lessons
- Pending lessons
- Pending assessments
- Scores
- Attendance where applicable
- Teacher feedback
- Certificates
- Access/payment status later

### Course Learning Page

The course learning page must show:

- Course title
- Offering title/mode if enrolled through an offering
- Course progress
- Module list
- Lesson list
- Locked/unlocked lessons
- Completed lessons
- Continue button
- Upcoming sessions if scheduled
- Certificate eligibility status

### Lesson Player

The lesson player must be a React component.

It must:

- Render lesson blocks dynamically
- Support RTL and LTR
- Support text, rich text, image, audio, video, PDF, glossary, dialogue, flashcards, downloads, instructions, activities, quizzes, and assignments
- Track lesson completion
- Respect unlock rules
- Show previous/next lesson
- Work well on mobile/PWA
- Periodically save important in-progress work to the server
- Handle connection drops gracefully

---

## 25. Progress Tracking

Use lesson-level progress in Phase 1.

Do not use block-level progress in Phase 1.

### student_lesson_progress Table

Fields:

- ID
- Enrollment ID
- Course ID
- Course offering ID nullable
- Module ID
- Lesson ID
- Lesson revision ID nullable
- Student ID
- Status
- Started at
- Completed at
- Score summary JSON nullable
- Timestamps

### Progress Statuses

Support:

- Not started
- In progress
- Completed
- Failed if applicable

### Course Progress Formula

```text
Completed required lessons / Total required lessons × 100
```

Unit tests must cover this calculation.

### Offering Progress

If the student is enrolled through an offering, progress should be calculated in the context of that offering.

For blended, face-to-face, live online, and hybrid offerings, completion may also include:

- Attendance
- Assignments
- Assessments
- Teacher approval

---

## 26. Unlock Rules

Admin must be able to configure unlock rules.

Supported unlock rules:

- All lessons open
- Complete previous lesson first
- Complete previous module first
- Pass quiz first
- Submit assignment first
- Teacher approval required
- Date-based unlock
- Offering start date required
- Session attendance required later
- Payment required later
- Manual unlock by admin

Create an `UnlockRuleEvaluator` service.

Do not scatter unlock logic across controllers or React components.

Unlock rules should be stored in JSON settings at course, module, lesson, or offering level.

---

## 27. Completion Rules

Admin must be able to configure completion rules.

### Lesson Completion Examples

- Student clicks complete
- Required activities completed
- Quiz passed
- Assignment submitted
- Teacher approval received

### Course Completion Examples

- Complete all required lessons
- Pass final assessment
- Complete required teacher-reviewed submissions
- Reach minimum score
- Payment completed later

### Offering Completion Examples

Self-learning:

- Complete required lessons
- Pass required assessments

Face-to-face:

- Meet attendance requirement
- Submit required assignments
- Pass required assessments

Live online:

- Attend required live sessions
- Complete assignments
- Pass assessments

Blended:

- Complete self-learning lessons
- Attend required sessions
- Submit required work
- Pass assessments

Hybrid:

- Attend required sessions physically or online
- Complete required course work
- Pass assessments

Use a dedicated completion calculation service.

Do not put completion rules directly inside controllers.

---

## 28. Content Versioning and Offering Pinning

Content versioning is critical.

Editing published content must never corrupt student progress, student scores, assessment attempts, or historical records.

### 28.1 Lesson Revision Policy

A `lesson_revision` is a full immutable snapshot of the lesson at publish time.

The snapshot must include:

- Lesson metadata required for rendering
- Ordered block list
- Block type
- Block data JSON
- Block settings JSON
- Required/optional status
- Attached media references or stable media metadata where needed
- Revision number
- Published timestamp
- Published by

Publishing a lesson creates a new immutable revision.

Draft edits must never affect what currently enrolled students see unless the relevant course/offering is explicitly configured to use the new revision.

Draft lessons can be edited freely.

Published lessons require revision handling.

### 28.2 Student View Rule

Students must view lesson content from a published revision, not directly from draft lesson/block records.

Student progress and assessment attempts must reference the revision ID they were completed against.

Editing, adding, removing, or reordering draft blocks must never change:

- Historical progress
- Completed lesson records
- Scores
- Assessment attempts
- Submitted answers
- Teacher feedback history

### 28.3 Course Content Version

A course content version represents the published set of lesson revisions used by an offering or by self-learning latest mode.

When course content changes, new lesson revisions and/or course content version references must be created rather than mutating historical content.

### 28.4 Offering Pinning

Each course offering pins to a course content version at the time the offering opens.

Enrolled students see the pinned version for the life of that offering.

Admins may explicitly re-pin an offering to a newer course content version, but this must be a deliberate action.

Offering re-pinning must never happen automatically.

If an offering is re-pinned, the system should record:

- Old pinned version
- New pinned version
- Admin who changed it
- Timestamp
- Reason/comment nullable

### 28.5 Self-Learning Offering Version Mode

Self-learning offerings may be configured in one of two modes:

1. Always latest published

Students see the latest published course content version.

2. Pinned

Students see the content version pinned to the offering.

Default for self-learning offerings:

```text
Always latest published
```

Scheduled offerings such as face-to-face, live online, blended, and hybrid should default to pinned mode when the offering opens.

### 28.6 Historical Safety Rule

Editing or reordering blocks must never change historical progress, scores, attempt snapshots, or student-visible history.

If a test or feature depends on mutating historical published content directly, the architecture is wrong.

---

## 29. Soft Deletes and Historical Data

Use soft deletes on:

- Courses
- Course offerings
- Offering sessions
- Modules
- Lessons
- Activities
- Assessments
- Enrollments

Never hard-delete a course, offering, session, module, lesson, activity, assessment, or enrollment if it has:

- Enrollments
- Attendance records
- Attempts
- Progress records
- Student submissions
- Teacher feedback
- Issued certificates
- Payment records

Historical student data must remain intact.

Hard deletes are allowed only for draft content with no student activity and no dependent records.

---

## 30. Media Handling

Use Laravel filesystem abstraction.

### Development Storage

Use local/public disk.

### Production Storage

Use S3-compatible disk.

Never reference storage paths directly in code.

Always use:

- Laravel Storage facade
- Or Spatie Media Library abstraction

### Media Library

Use `spatie/laravel-medialibrary` unless there is a clear technical reason not to.

If Spatie Media Library is used:

- Do not create a second custom media table.
- Use Spatie’s media model.
- Use media collections.
- Use conversions.
- Use disks.
- Use queued conversions.

### Media Uses

The media system must support:

- Course covers
- Offering images if needed
- Lesson images
- Lesson audio
- Lesson video
- Lesson PDFs
- Session materials
- Question attachments
- Glossary audio/images
- Student submissions
- Teacher feedback audio
- Certificate assets

### Upload Validation

Enforce server-side limits:

- Images: max 5MB, jpg/png/webp
- Audio: max 20MB, mp3/m4a/ogg/webm
- Video: max 200MB, mp4/webm
- PDFs: max 25MB
- Student voice recordings: max 10MB

Reject uploads by MIME validation/sniffing, not extension only.

### Audio Recording

Student voice submissions should use the MediaRecorder API where supported.

Requirements:

- Detect supported MIME type using `MediaRecorder.isTypeSupported()`
- Store raw upload
- Do not transcode in Phase 1 or Phase 2
- Fall back to manual audio upload if browser recording is unsupported
- Keep audio recording behind a frontend recorder abstraction so Capacitor native recording can replace it later

### Media Processing

Image thumbnails and conversions must run through queued jobs.

Never process thumbnails inline during the upload request.

All uploads must work behind a queue worker from day one.

### Private Media Security

Student submissions and paid-course media must not be publicly accessible.

Serve private media through:

- Signed URLs
- Temporary URLs
- Authenticated controller route

Free public media may use public URLs only if intentionally marked public.

---

## 31. Timezone Policy

Store all timestamps in UTC in the database.

Display and evaluate user-facing times in the platform timezone.

Default platform timezone:

```text
Maldives Time, UTC+5
```

The platform timezone must be configurable in system settings.

Timezone-sensitive features include:

- Date-based unlocks
- Access windows
- Course available dates
- Offering start/end dates
- Enrollment opening/closing dates
- Session start/end times
- Module available dates
- Assessment start/end windows
- Assessment time limits
- Enrollment access periods

Assessment countdowns and time limits must be computed server-side from `attempt.started_at` and configured time limit.

Never trust client device clocks for time-limit enforcement.

---

## 32. Authentication and Rate Limiting

Build rate limiting into authentication from Phase 1.

Throttle:

- Login attempts
- Registration attempts
- Password reset requests
- OTP send requests
- OTP verification attempts

Rate limits must apply:

- Per IP address
- Per account identifier where applicable
- Per phone number for OTP

### OTP-Ready Rules

Even if SMS is added later, the design must include:

- Maximum 3 OTP sends per phone number per 15 minutes
- Minimum 60-second resend cooldown
- Maximum failed OTP verification attempts before temporary lockout
- OTP abuse event logging for admin review

These limits should be configurable in system settings.

This protects future Dhiraagu SMS integration from cost abuse and spam.

---

## 33. Admin Dashboard

The admin dashboard must use Inertia + React.

### User Management

- Students
- Parents/guardians
- Teachers/instructors
- Course creators
- Supervisors
- Admins

### Course Management

- Course CRUD
- Subject CRUD (hierarchical)
- Audience CRUD
- Level CRUD
- Course publishing workflow
- Course preview
- Archive course

### Offering Management

- Course offering CRUD
- Delivery mode selection
- Batch management
- Teacher assignment
- Supervisor assignment
- Seat limit
- Enrollment dates
- Start/end dates
- Location
- Online meeting link
- Session schedule
- Attendance requirement
- Price override
- Offering status workflow
- Enrolled students per offering

### Course Builder

- Module CRUD
- Lesson CRUD
- Content block builder
- Block reordering
- Media upload
- Glossary/term attachment
- Activity attachment
- Assessment attachment

### Academic / Training Management

- Question bank
- Glossary/term bank
- Assessments
- Teacher-reviewed submissions
- Course approval workflow

### Reports

- Total students
- Active enrollments
- Course completion
- Offering completion
- Lesson completion
- Attendance reports
- Assessment scores
- Pending reviews
- Certificates issued
- Payment reports later

---

## 34. Course Creator Dashboard

Course creators must be able to:

- Create assigned courses
- Add modules
- Add lessons
- Add content blocks
- Upload media
- Attach glossary/terms
- Add activities
- Add assessments
- Submit course for review
- View supervisor comments

The course creator dashboard must be React-based and touch-friendly enough to work on tablets.

Course creators may create offerings only if given permission. Otherwise offering creation is admin-only.

---

## 35. Dean / Supervisor Dashboard

Dean/Supervisor must be able to:

- View courses submitted for review
- Approve courses
- Reject courses
- Request changes
- Review assessments
- Review content quality
- Review offering setup where applicable
- View academic/training reports

---

## 36. Teacher / Instructor / Reviewer Dashboard

Teacher/instructor/reviewer must be able to:

- View assigned offerings
- View session schedules
- View enrolled students
- Mark attendance
- View pending submissions
- Open student submissions
- Play audio/voice submissions
- View uploaded files
- Give score
- Give written feedback
- Upload correction audio
- Mark passed/failed
- Request resubmission

---

## 37. Parent Dashboard

Parent dashboard can ship later.

When built, it should show:

- Child courses/offerings
- Child progress
- Attendance
- Scores
- Teacher feedback
- Certificates
- Payment status

The relationship model must already exist in Phase 1.

---

## 38. Payment-Ready Design

Do not implement full BML payment in Phase 1 unless specifically requested.

But the architecture must support it later.

### payments Table

Fields:

- ID
- Student ID
- Course ID nullable
- Course offering ID nullable
- Enrollment ID nullable
- Amount
- Currency
- Payment method
- Gateway
- Status
- Transaction reference
- Paid at nullable
- Metadata JSON
- Timestamps

### Payment Statuses

Support:

- Pending
- Paid
- Failed
- Refunded
- Cancelled

Course/offering access rules must be able to check payment status later.

Offerings may override course price.

---

## 39. Certificates

Certificate system can be built in Phase 3, but course/offering models must be certificate-ready from Phase 1.

Certificate features:

- Certificate template builder
- Course completion certificate
- Offering completion certificate
- Assessment certificate
- Manual certificate issue
- Certificate number
- QR verification
- Student name
- Course name
- Offering/batch name where applicable
- Completion date
- Grade
- Attendance percentage where applicable
- Signature
- Institute logo

### Certificate Eligibility Rules

Admin should be able to configure:

- Minimum progress percentage
- Required final assessment
- Required teacher approval
- Minimum score
- Minimum attendance percentage
- Payment completed later

Certificate rules may be set at course level and overridden at offering level.

---

## 40. Notifications

Build notification-ready structure.

Future notifications may include:

- Enrollment confirmation
- Course approval/rejection
- Offering opened
- Session reminder
- Online session link available
- Teacher feedback completed
- Assignment resubmission requested
- Certificate issued
- Payment reminder
- Course announcement
- Offering announcement

Possible channels later:

- In-app
- Email
- SMS
- Push notification

Do not overbuild notifications in Phase 1.

---

## 41. Suggested Domain Structure and Boundary Enforcement

Use modular monolith under:

```text
app/Domains
```

Suggested domains:

```text
Auth
Users
Courses
Offerings
Activities
Assessments
Progress
Attendance
Glossary
Media
Certificates
Payments
Reports
Notifications
Settings
ArabicSkills
AiPronunciation
QuranHifz
QuranRecitationAi
```

### Important Domain Boundary Rule

For Phase 1A, keep these inside the Courses domain:

- Courses
- Subjects (hierarchical taxonomy)
- Audiences
- Levels
- Modules
- Lessons
- Lesson revisions
- Content blocks
- Publishing workflow

For Phase 1B, Course Offerings may be either:

- A submodule inside Courses if simpler
- Or a separate Offerings domain if the codebase stays clean

Do not over-split too early.

Only split further if a real boundary emerges.

### Domain Boundary Enforcement

Domains communicate only through their public actions/services, DTOs, interfaces, or domain events.

A domain must never directly query or import another domain's Eloquent models.

Each domain must expose a small public interface:

- Actions
- Services
- DTOs
- Events
- Contracts/interfaces where needed

Everything else inside the domain is internal.

Cross-domain side effects must use events/listeners.

Example:

- Enrollment should dispatch an enrollment-created event.
- Notifications should listen to that event.
- Enrollment code must not directly call notification implementation classes.

Shared concerns must be their own domains and consumed through interfaces.

Shared concerns include:

- Media
- Settings
- Notifications
- Payments later
- Certificates later

### Architecture Test Requirement

Add architecture tests, for example Pest arch tests, to enforce domain boundaries.

The architecture test must fail CI if:

- One domain imports another domain's internal Eloquent models.
- Controllers contain business logic that should be in actions/services.
- A domain uses another domain's internal classes instead of public actions/contracts/events.
- External SDK classes are used directly inside domain business logic instead of wrapped interfaces.

---

## 42. Business Logic Placement and Replaceability

All business logic must live in domain services/actions, not controllers.

Examples:

- `CreateCourseAction`
- `PublishCourseAction`
- `CreateCourseOfferingAction`
- `OpenCourseOfferingAction`
- `ScheduleOfferingSessionAction`
- `MarkAttendanceAction`
- `ReorderContentBlocksAction`
- `EnrollStudentAction`
- `CompleteLessonAction`
- `EvaluateUnlockRuleAction`
- `CalculateCourseProgressAction`
- `CalculateOfferingCompletionAction`
- `StartAssessmentAttemptAction`
- `SubmitActivityAttemptAction`
- `UploadMediaAction`

Inertia controllers should only:

- Authorize
- Validate/request DTO
- Call action/service
- Return Inertia response or redirect

Future API controllers should call the same services/actions.

Do not duplicate logic between Inertia and API routes.

### Interface Binding and Replaceability

Bind key services to interfaces in the Laravel service container so implementations are swappable.

Required service interfaces include:

- Media storage interface
- Video provider interface
- Notification channel interface
- Payment gateway interface, Phase 4
- Certificate renderer interface, Phase 3
- Settings repository/interface
- File URL signer/private media access interface
- Course progress calculator interface where useful
- Unlock rule evaluator interface where useful
- Completion rule evaluator interface where useful

The frontend audio recorder must also stay behind the already specified recorder abstraction.

No domain may depend directly on a concrete third-party SDK.

External services must be wrapped behind a domain-owned interface.

Examples:

- Do not call a payment gateway SDK directly from course/enrollment code.
- Do not call an SMS provider directly from auth code.
- Do not call storage provider SDKs directly from course blocks.
- Do not expose Spatie Media Library implementation details outside the Media domain interface.

The goal is to allow future replacement of:

- Local storage with S3-compatible storage
- Uploaded video with external video provider
- In-app notification with SMS/email/push
- Manual payment with BML gateway
- One certificate rendering implementation with another

---

## 43. Suggested Database Tables

### Core

- users
- roles
- permissions
- guardian_student
- system_settings

### Courses

- course_subjects (hierarchical taxonomy; distinct from Academics school `subjects`)
- audiences
- course_levels
- courses
- course_modules
- lessons
- lesson_revisions
- content_blocks

### Offerings

- course_offerings
- course_offering_sessions
- attendance_records
- course_enrollments

### Activities

- activities
- activity_attempts
- student_submissions
- teacher_feedback

### Assessments

- assessments
- assessment_questions
- assessment_attempts
- questions

### Glossary

- glossary_items
- lesson_glossary_items

### Progress

- student_lesson_progress

### Media

Use Spatie media table if using Spatie Media Library.

### Certificates

- certificate_templates
- issued_certificates

### Payments

- payments
- coupons later

### Notifications

- notifications
- announcement tables if needed

### Arabic Skills Module

- arabic_letters
- arabic_harakas
- arabic_pronunciation_attempts
- ai_predictions
- training_samples
- ai_model_versions

Optional later:

- arabic_handwriting_attempts
- arabic_skill_reports

### Future Qur’an/Hifz Module

The Qur’an/Hifz module must reuse shared Arabic letters, harakas, audio recording, AI prediction, training samples, and AI model version infrastructure where possible.

Do not duplicate the Arabic Skills AI tables unless there is a clear Qur’an-specific reason.

Suggested Qur’an/Hifz tables:

- quran_surahs
- quran_ayahs
- quran_hifz_assignments
- quran_recitation_submissions
- quran_mistake_marks
- quran_revision_schedules
- quran_recitation_reviews
- quran_memorization_progress

Optional later AI tables:

- quran_ayah_alignment_attempts
- quran_word_alignment_attempts
- quran_tajweed_rule_checks
- quran_ai_recitation_predictions

---

## 44. API Requirements

Build an API-ready backend.

Even though Phase 1 uses Inertia pages, JSON endpoints or service-ready actions should support future API use.

Future `/api/v1` endpoints should be easy to add for:

- Course CRUD
- Subject CRUD (hierarchical)
- Audience CRUD
- Level CRUD
- Module CRUD
- Lesson CRUD
- Content block CRUD
- Block reorder
- Course offering CRUD
- Session CRUD
- Attendance
- Media upload
- Enrollment
- Lesson player data
- Progress update
- Activity attempt
- Assessment attempt
- Teacher feedback
- Course publishing workflow
- Offering status workflow

All actions must use backend policies and permissions.

Do not rely only on frontend button hiding.

---

## 45. Policies and Permissions

Use policies for:

- Course create/edit/delete/publish
- Offering create/edit/delete/open/cancel/archive
- Session management
- Attendance marking
- Module management
- Lesson management
- Content block management
- Enrollment
- View lesson
- Complete lesson
- Review submission
- View reports
- Manage users
- Manage settings
- Manage payments
- Manage certificates

Backend must enforce permissions.

---

## 46. Phase 1A and Phase 1B Scope

Split Phase 1 into two sub-phases.

Do not start Phase 1B until Phase 1A is stable.

### 46.1 Phase 1A Scope

Phase 1A builds the stable core platform.

Phase 1A includes:

1. Laravel 12 setup
2. Inertia.js + React setup
3. Vite asset pipeline
4. Authentication and roles
5. Auth rate limiting
6. User management
7. Parent-child relationship model
8. System settings foundation
9. Subject management (hierarchical)
10. Audience management
11. Level management
12. Course CRUD
13. Course status workflow
14. Module CRUD
15. Lesson CRUD
16. Lesson revision foundation
17. Dynamic content block system
18. Phase 1A content blocks only:
    - Text
    - Rich Text
    - Image
    - Audio
    - Video
    - PDF
    - Instruction
19. React content block builder for Phase 1A blocks
20. React lesson player for Phase 1A blocks
21. Block validation
22. Block reorder
23. Media upload pipeline
24. Self-learning enrollment
25. Student dashboard basic version
26. Basic progress tracking
27. Domain services/actions
28. Domain boundary architecture tests
29. Core Phase 1A tests

### 46.2 Phase 1A Definition of Done

Phase 1A is complete only when:

- Laravel 12, Inertia, React, and Vite are working cleanly.
- Auth, roles, users, rate limiting, and parent-child relationship model exist.
- Subjects, audiences, and levels are admin-managed.
- A course creator can create a course, modules, lessons, and Phase 1A content blocks from the dashboard.
- Content blocks are stored dynamically in the database.
- No course content is hardcoded.
- Text direction is a setting on text-capable blocks, not a separate block type.
- A student can enroll in a self-learning course.
- A student can open the React lesson player.
- The lesson player renders all Phase 1A block types dynamically.
- Basic lesson progress updates correctly.
- Published lesson revisions protect student progress.
- Media uploads work through centralized media handling.
- Private media is protected where required.
- Controllers are thin and use domain services/actions.
- Domain boundary architecture tests exist.
- Required Phase 1A tests pass.

### 46.3 Phase 1B Scope

Phase 1B starts only after Phase 1A is stable.

Phase 1B adds delivery modes, offerings, stronger rules, and remaining blocks.

Phase 1B includes:

1. Course offerings table and basic CRUD
2. Delivery mode field and logic
3. Self-learning offering support
4. Face-to-face offering structure
5. Live online offering structure
6. Blended offering structure
7. Hybrid offering structure
8. Course content version pinning to offerings
9. Offering re-pin action
10. Session table foundation
11. Attendance table foundation
12. Enrollment linked to offering where applicable
13. Seat-limit concurrency-safe enrollment
14. Phase 1B content blocks:
    - Glossary / Term
    - Dialogue
    - Flashcard
    - Download
    - Quiz Embed
    - Assignment Embed
15. Unlock rule evaluator
16. Completion rule foundation
17. PWA manifest
18. Service worker
19. Full i18n polish
20. RTL/LTR polish for English, Dhivehi, and Arabic
21. Dhivehi/Thaana font bundling/verification
22. Required Phase 1B tests

### 46.4 Phase 1B Definition of Done

Phase 1B is complete only when:

- Admin can create at least one offering for a course.
- Admin can select delivery mode for the offering.
- Self-learning offering works.
- Basic scheduled offering structure exists for face-to-face, live online, blended, and hybrid modes.
- Course sessions can be stored for scheduled offerings.
- Attendance data model exists.
- Student enrollment can link to an offering.
- Seat limits are enforced safely at the database/transaction level.
- Concurrent enrollment test passes.
- Offering content pinning works.
- Self-learning offerings support latest-published or pinned mode.
- Phase 1B block types render dynamically.
- Unlock rules are evaluated by a dedicated service.
- Completion rules foundation exists.
- PWA manifest and service worker exist.
- Full i18n polish is complete.
- RTL and LTR layouts work.
- Dhivehi/Thaana and Arabic render properly.
- Required Phase 1B tests pass.

---

## 47. Phase 2 Scope

Build activities, assessments, and fuller scheduled-course features.

Phase 2 includes:

- Activity builder
- React activity player
- 4 base activity patterns
- Activity attempts
- Autosave for in-progress activity answers
- Auto-marking for selection activities
- Auto-marking for text input activities
- Text normalization settings
- Drag/arrange interactions
- Teacher-marked submissions
- Question bank
- Assessment builder
- React assessment player
- Autosave for assessment answers
- Assessment-question pivot
- Assessment attempt snapshots
- Assessment attempts
- Teacher review dashboard
- Teacher feedback
- Retake rules
- Passing rules
- Full session management UI
- Attendance marking UI
- Student schedule view
- Teacher schedule view

### Phase 2 Definition of Done

Phase 2 is complete when:

- Admin can create activities without code changes.
- New activity types can be added through the 4 base patterns.
- Auto-marked activities work.
- Text normalization is configurable per activity.
- Teacher-marked submissions work.
- Teachers can review, score, and give feedback.
- Students can see feedback.
- Assessments can be attached to lessons, modules, or courses.
- In-progress answers are periodically saved to the server.
- Question editing does not affect existing attempts.
- Teachers can mark attendance.
- Students can see scheduled sessions.
- Required tests pass.

---

## 48. Phase 3 Scope

Build certificates, reporting, and parent dashboard.

Phase 3 includes:

- Certificate template builder
- Certificate issuing
- QR verification
- Parent dashboard
- Student performance reports
- Course completion reports
- Offering completion reports
- Attendance reports
- Pending teacher review reports
- Basic weakness tracking
- Basic revision recommendations

### Phase 3 Definition of Done

Phase 3 is complete when:

- Admin can configure certificate rules.
- Student can receive certificate after eligibility.
- Certificate can be verified by QR code.
- Parents can view child progress and attendance.
- Admin can view useful academic/training reports.
- Teachers can identify pending reviews and weak students.

---

## 49. Phase 4 Scope

Build payment and advanced access control.

Phase 4 includes:

- BML payment integration
- Manual payment recording
- Coupons
- Paid course access
- Offering price override
- Trial lessons
- Subscription-ready access
- Payment reports
- Payment-based unlock rules

### Phase 4 Definition of Done

Phase 4 is complete when:

- Paid courses/offerings can be sold.
- Payment status controls access.
- Offering price overrides work.
- Admin can manually enroll or record payments.
- Students cannot access paid content without eligibility.
- Payment reports are available.

---

## 50. Phase 5 Scope

Optional mobile packaging and AI/personalization.

### Mobile Packaging

Build Capacitor app using the existing responsive Inertia React PWA.

Tasks:

- Wrap web app in Capacitor
- Test authentication
- Test lesson player
- Test media playback
- Test audio recording abstraction
- Test file upload
- Test RTL and Thaana fonts
- Test scheduled session views
- Test push notifications later if added

### Optional AI Features

Do not build AI in Phase 1.

Future AI features may include:

- Pronunciation feedback
- Writing correction
- Reading fluency feedback
- Personalized revision path
- AI-generated practice suggestions
- Smart weakness detection

AI must be optional and must not replace the core course/offering builder.

---

## 51. Arabic Skills and Local AI Module

The Arabic teaching project must be merged into this General Learning Platform as a module.

It must not become a second full website with duplicate users, duplicate lessons, duplicate exercises, duplicate dashboards, duplicate progress tracking, or duplicate review workflows.

The General Learning Platform remains the core system.

The Arabic module adds subject-specific Arabic learning and AI capabilities on top of the core platform.

### 51.1 Module Purpose

Create an Arabic Skills module for students to practice:

- Listening
- Speaking
- Reading
- Writing

This module is for Arabic language learning.

It is not a Quran/Hifz project.

Do not include:

- Quran memorization
- Ayah checking
- Tajweed rule checking
- Hifz mistake detection
- Quran-specific recitation scoring

Quran-related courses may exist in the general platform as normal courses, but this Arabic Skills module must not become the Quran/Hifz AI system.

### 51.2 Integration Rule

The Arabic module must use the main platform systems:

- Users
- Roles
- Courses
- Course offerings
- Modules
- Lessons
- Content blocks
- Activities
- Assessments
- Submissions
- Teacher review
- Supervisor review
- Dean oversight
- Progress
- Reports
- Media handling

Do not create a second LMS structure.

Do not create separate Arabic-only `lessons`, `exercises`, `student_assignments`, or `student_answers` tables that duplicate the main platform.

Arabic learning tasks should be represented using the general platform activity/submission system with Arabic-specific metadata where needed.

### 51.3 Arabic-Specific Tables

Add Arabic-specific tables only where the general platform cannot remain subject-neutral.

Suggested tables:

- `arabic_letters`
- `arabic_harakas`
- `arabic_pronunciation_attempts`
- `ai_predictions`
- `training_samples`
- `ai_model_versions`

Optional later:

- `arabic_handwriting_attempts`
- `arabic_skill_reports`

### 51.4 Arabic Letters

Create an `arabic_letters` table.

Fields:

- ID
- Key name
- Arabic character
- Display name
- Description nullable
- Sort order
- Is active
- Timestamps

Seed letters:

- alif: ا
- baa: ب
- taa: ت
- thaa: ث
- jeem: ج
- haa: ح
- khaa: خ
- daal: د
- dhaal: ذ
- raa: ر
- zaay: ز
- seen: س
- sheen: ش
- saad: ص
- daad: ض
- taa_emphatic: ط
- zaa_emphatic: ظ
- ayn: ع
- ghayn: غ
- faa: ف
- qaaf: ق
- kaaf: ك
- laam: ل
- meem: م
- noon: ن
- haa_final: ه
- waaw: و
- yaa: ي

### 51.5 Arabic Harakas

Create an `arabic_harakas` table.

Fields:

- ID
- Key name
- Symbol
- Display name
- Description nullable
- Sort order
- Is active
- Timestamps

Seed harakas:

- fatha: َ
- kasra: ِ
- damma: ُ
- sukoon: ْ

### 51.6 Arabic Skill Activities

Arabic skill activities must be implemented using the general platform activity system.

Do not create a separate Arabic exercise engine.

#### Listening

Listening practice should use existing activity patterns:

- Auto-marked selection
- Auto-marked text input
- Audio question
- Multiple choice
- True/false
- Dictation

Examples:

- Choose the heard letter
- Choose the heard haraka
- Choose the heard word
- Type what you hear
- Listen and select the correct answer

#### Speaking

Speaking practice should use audio submission activities.

Version 1 AI auto-checking must only support isolated Arabic letter + haraka pronunciation.

For words, sentences, and longer speech:

- Save the recording.
- Send it for teacher review.
- Do not claim full Arabic speech recognition is complete.

#### Reading

Reading activities should use teacher-marked audio submissions.

Examples:

- Letter reading
- Word reading
- Sentence reading
- Passage reading

Version 1:

- AI may check isolated letter + haraka only.
- Longer reading is manually reviewed by teacher.

#### Writing

Writing activities should use:

- Auto-marked text input for typed Arabic answers
- Teacher-marked submission for handwriting/canvas/image uploads

Examples:

- Type letter
- Type word
- Type sentence
- Copy text
- Dictation writing
- Handwriting canvas
- Handwriting image upload

Version 1:

- Typed Arabic answers can be auto-checked.
- Handwriting and canvas submissions are saved for teacher review.
- Handwriting AI is future work only.

### 51.7 Arabic Activity Metadata

Arabic-specific activity data should be stored in the activity `data` JSON or dedicated Arabic attempt tables where needed.

For letter/haraka pronunciation activities, store:

- Expected letter ID
- Expected haraka ID
- Prompt text
- Prompt audio media ID nullable
- Skill type: listening, speaking, reading, writing
- AI enabled boolean
- Teacher review required boolean
- Confidence threshold
- Normalization settings where applicable

Do not hardcode Arabic letters, harakas, or exercise types in React components.

### 51.8 Arabic Text Normalization

Create an Arabic text normalization service as part of the Arabic Skills module.

This service may be used by the general auto-marked text input system when the activity enables Arabic normalization.

Normalization options:

- Trim spaces
- Remove tatweel ـ
- Normalize repeated spaces
- Remove punctuation where configured
- Normalize alif variants where configured
- Normalize hamza variants where configured
- Strip tashkeel where configured
- Preserve haraka when the exercise requires haraka checking
- Strict mode
- Lenient mode
- Teacher override

Arabic normalization must be configurable per activity.

Do not apply Arabic normalization globally to all text answers.

### 51.9 Local/Offline AI Policy

The Arabic pronunciation AI must be local/offline as much as possible.

Do not use:

- OpenAI API
- Google Speech API
- Whisper API
- Cloud speech recognition APIs
- External AI APIs for pronunciation checking

The AI may run as a local Python module called by Laravel.

The first AI target is isolated Arabic letter + haraka pronunciation.

### 51.10 AI Module Structure

Use this structure inside the project:

```text
/ai
  /pronunciation
    /dataset
      /alif
        /fatha
        /kasra
        /damma
        /sukoon
      /baa
        /fatha
        /kasra
        /damma
        /sukoon
    /approved_training_samples
    /models
      arabic_letter_haraka_model.h5
      letter_labels.json
      haraka_labels.json
    audio_processor.py
    model.py
    train.py
    predict.py
    export_training_samples.py
    requirements.txt
    README.md

  /writing
    README.md
    future_handwriting_model_notes.md
```

### 51.11 Pronunciation AI Requirements

The pronunciation model should be a multi-output CNN.

Architecture:

- Shared CNN feature extractor
- Output 1: letter output
- Output 2: haraka output

Python dependencies:

- TensorFlow/Keras
- librosa
- soundfile
- numpy
- scikit-learn

`audio_processor.py` must:

- Load mono audio at 16000 Hz
- Normalize audio
- Pad or truncate to exactly 1 second
- Extract MFCC features
- Return fixed-shape CNN input
- Handle wav, webm, mp3, and flac where possible
- Include clear error handling
- Document ffmpeg requirements for browser webm audio if needed

`predict.py` must:

- Accept audio path from command line
- Return JSON only

Example JSON:

```json
{
  "success": true,
  "predicted_letter": "baa",
  "predicted_haraka": "fatha",
  "letter_confidence": 0.94,
  "haraka_confidence": 0.88,
  "model_version": "arabic_pronunciation_v1"
}
```

### 51.12 AI Prediction Storage

Create an `ai_predictions` table.

Fields:

- ID
- Audio submission ID nullable
- Arabic pronunciation attempt ID nullable
- Predicted letter ID nullable
- Predicted haraka ID nullable
- Predicted letter label nullable
- Predicted haraka label nullable
- Letter confidence decimal
- Haraka confidence decimal
- Is letter match boolean
- Is haraka match boolean
- Final status
- Raw JSON long text nullable
- Model version nullable
- Error message nullable
- Timestamps

Final statuses:

- Correct
- Wrong letter
- Wrong haraka
- Low confidence
- Needs teacher review
- Error

### 51.13 Arabic Pronunciation Attempts

Create an `arabic_pronunciation_attempts` table.

Fields:

- ID
- Student ID
- Course ID nullable
- Course offering ID nullable
- Lesson ID nullable
- Lesson revision ID nullable
- Activity ID nullable
- Expected letter ID
- Expected haraka ID
- Audio media ID nullable
- Mode: live or manual
- Duration seconds nullable
- Status
- AI prediction ID nullable
- Teacher review required boolean
- Timestamps

Statuses:

- Submitted
- AI checked
- Teacher reviewed
- Supervisor reviewed
- Dean reviewed
- Failed

### 51.14 Laravel AI Services

Create services/actions behind interfaces.

Suggested service contracts:

- `PronunciationPredictionInterface`
- `ArabicTextNormalizerInterface`
- `AiTrainingManagerInterface`
- `AiModelVersionManagerInterface`

Suggested actions:

- `RunPronunciationPredictionAction`
- `ComparePronunciationPredictionAction`
- `StoreArabicPronunciationAttemptAction`
- `NormalizeArabicTextAction`
- `CheckArabicTypedAnswerAction`
- `RecommendTrainingSampleAction`
- `ApproveTrainingSampleAction`
- `RejectTrainingSampleAction`
- `ExportApprovedSamplesAction`
- `StartAiTrainingJobAction`
- `ActivateAiModelVersionAction`
- `RollbackAiModelVersionAction`
- `GetAiDatasetStatsAction`

Do not let the Courses, Activities, or Progress domains call Python scripts directly.

AI calls must go through the Arabic Skills / AI Pronunciation domain interface.

### 51.15 Environment Variables

Add these environment variables when the Arabic AI module is enabled:

```env
AI_PYTHON_BIN=python3
AI_PRONUNCIATION_PREDICT_SCRIPT=/full/path/to/ai/pronunciation/predict.py
AI_PRONUNCIATION_MODEL_PATH=/full/path/to/ai/pronunciation/models/arabic_letter_haraka_model.h5
AI_CONFIDENCE_THRESHOLD=0.70
AI_PRONUNCIATION_ENABLED=false
```

The module should be feature-flagged.

The main platform must work even if the AI module is disabled.

### 51.16 Human-in-the-Loop AI Improvement

Add a system where teachers, supervisors, deans, and admins can improve the pronunciation AI over time using verified student recordings.

Workflow:

1. Student submits speaking audio.
2. AI predicts letter + haraka if applicable.
3. Teacher/supervisor/dean/admin listens.
4. Reviewer confirms or corrects:
   - Correct
   - Wrong letter
   - Wrong haraka
   - Both wrong
   - Unclear audio / reject sample
5. Reviewer sets verified letter and verified haraka.
6. Approved samples become training data.
7. Admin retrains model in batches.
8. New model is saved as a new version.
9. Admin can activate or rollback model versions.

Important rules:

- Do not retrain after every correction.
- Only approved samples should be used.
- Bad/noisy audio must be rejected.
- Keep old model versions.
- Never overwrite active model without backup.
- Model activation and rollback must be audited.

### 51.17 Training Samples

Create a `training_samples` table.

Fields:

- ID
- Arabic pronunciation attempt ID nullable
- Audio media ID
- Verified letter ID
- Verified haraka ID
- Original predicted letter ID nullable
- Original predicted haraka ID nullable
- Teacher user ID nullable
- Supervisor user ID nullable
- Dean user ID nullable
- Admin user ID nullable
- Status
- Rejection reason nullable
- Notes nullable
- Timestamps

Statuses:

- Pending review
- Approved
- Rejected
- Used for training

### 51.18 AI Model Versions

Create an `ai_model_versions` table.

Fields:

- ID
- Model type
- Version name
- Model path
- Letter labels path nullable
- Haraka labels path nullable
- Training sample count
- Validation letter accuracy nullable
- Validation haraka accuracy nullable
- Is active boolean
- Trained by user ID nullable
- Notes nullable
- Timestamps

Model types:

- Pronunciation letter haraka
- Handwriting future

### 51.19 Arabic Module UI Components

Add these React components when the Arabic module is implemented:

- AudioRecorder
- LiveAudioChecker
- ConfidenceBadge
- PredictionResultCard
- ArabicLetterHarakaCard
- ListeningQuestionCard
- MultipleChoiceQuestion
- DictationInput
- ArabicTextInput
- ArabicWritingCanvas
- ReadingPromptCard
- AudioPlayer
- ReviewDecisionForm
- TrainingSampleApprovalForm
- ModelVersionCard
- SkillProgressCard

These components must plug into the general platform lesson player/activity player, not replace it.

### 51.20 Arabic Module Dashboards

Arabic module screens should extend existing dashboards.

Do not create completely separate dashboard systems.

Admin additions:

- Arabic letters management
- Harakas management
- Arabic AI status
- Training samples
- Dataset statistics
- AI model versions
- AI training controls

Dean additions:

- Arabic skill reports
- AI dataset statistics
- Model version visibility
- Academic quality overview

Supervisor additions:

- Training sample approval
- Common mistake reports
- Dataset statistics

Teacher additions:

- Arabic submissions review
- Pronunciation correction
- Recommend samples for training

Student additions:

- Listening practice
- Speaking practice
- Reading practice
- Writing practice
- Arabic skill progress

### 51.21 Arabic Module API Strategy

Do not build a separate API surface that duplicates platform resources.

Use the main platform APIs for:

- Courses
- Lessons
- Activities
- Submissions
- Reviews
- Progress

Add Arabic-specific endpoints only for Arabic-specific operations.

Suggested future endpoints:

```text
POST /api/v1/arabic/pronunciation/attempts
POST /api/v1/arabic/pronunciation/live-check
GET /api/v1/arabic/letters
GET /api/v1/arabic/harakas
POST /api/v1/arabic/training-samples/{id}/approve
POST /api/v1/arabic/training-samples/{id}/reject
POST /api/v1/arabic/model-versions/{id}/activate
POST /api/v1/arabic/model-versions/{id}/rollback
POST /api/v1/arabic/ai-training/start
GET /api/v1/arabic/ai-dataset-stats
```

### 51.22 Arabic Module Phase

Do not build the Arabic AI module in Phase 1A.

Recommended timing:

- Phase 1A: Build core platform foundation.
- Phase 1B: Build offerings, delivery modes, remaining blocks, PWA/i18n polish.
- Phase 2: Build general activities, assessments, submissions, teacher review.
- Phase 3: Build reporting, parent dashboard, certificates.
- Arabic Skills Module: Start after the general activity/submission/review system is stable.

The Arabic Skills Module may be split into:

#### Arabic Module A: Non-AI Arabic Skills

- Arabic letters
- Harakas
- Listening activities using audio + choices
- Typed Arabic writing checks
- Reading/speaking submissions for teacher review
- Arabic writing canvas submission
- Arabic skill reports

#### Arabic Module B: Local Pronunciation AI

- Python AI module
- Letter + haraka pronunciation checking
- AI predictions
- Training samples
- Model versions
- Human-in-the-loop improvement

### 51.23 Arabic Module Acceptance Criteria

The Arabic module is complete only when:

- It uses the main platform courses, lessons, activities, submissions, reviews, and progress.
- It does not duplicate the LMS structure.
- Arabic letters and harakas are admin-managed.
- Listening practice works through the general activity system.
- Speaking isolated letter + haraka AI check works locally/offline.
- Reading recordings are saved for teacher review.
- Typed Arabic writing can be auto-checked with configurable normalization.
- Handwriting/canvas submissions are saved for teacher review.
- Teachers can correct AI predictions.
- Supervisors/deans/admins can approve/reject training samples according to permissions.
- Admin can retrain in batches.
- Admin can activate or rollback model versions.
- Old model versions are preserved.
- The main platform still works if the Arabic AI module is disabled.

---

## 52. Future Qur’an/Hifz Recitation Module

The Qur’an/Hifz plan must be merged into the General Learning Platform as a separate future module.

It must not become a second duplicate website.

It must not be mixed into the Arabic Skills module.

The Arabic Skills module is for Arabic language learning: listening, speaking, reading, writing, letters, harakas, vocabulary, pronunciation, and writing practice.

The Qur’an/Hifz module is for Qur’an memorization, recitation practice, revision, teacher correction, mistake tracking, and future AI-assisted Qur’an recitation support.

### 52.1 Module Purpose

Build a Qur’an/Hifz learning module where students can:

- Practice isolated Arabic letter + haraka pronunciation first
- Use live microphone checking for letter + haraka practice
- Use manual recording as backup
- Submit recitations for teacher review
- Receive teacher correction
- Track memorization/revision progress
- Later practice Qur’an ayah memorization and recitation

The correct Qur’an/Hifz plan emphasizes that Version 1 is not full Qur’an ayah checking yet and must not claim complete Qur’an memorization checking.

### 52.2 Critical Haraka Rule

In Qur’an memorization and recitation, haraka is very important.

The system must not only check the Arabic letter.

It must also check the vowel/haraka.

Example:

```text
بَ = baa + fatha
بِ = baa + kasra
بُ = baa + damma
بْ = baa + sukoon
```

If expected is `بَ` and the student reads `بِ`, the result should be:

```text
Letter: correct
Haraka: wrong
Final result: haraka mistake
```

This rule applies to the shared pronunciation AI engine and must also be respected by the Qur’an/Hifz module.

### 52.3 Relationship to Shared Arabic Pronunciation AI

The Qur’an/Hifz module should reuse the Arabic Skills / AiPronunciation infrastructure for isolated letter + haraka checking.

Reuse shared systems:

- Arabic letters
- Arabic harakas
- Audio recorder
- Live microphone checking
- Manual audio recording
- AI predictions
- Confidence threshold
- Training samples
- AI model versions
- Human-in-the-loop correction
- Teacher/supervisor/dean/admin review workflow

Do not create a second independent letter + haraka AI system only for Qur’an.

The same local/offline pronunciation AI can be used by both:

- Arabic Skills module
- Qur’an/Hifz module

The Qur’an module can add Qur’an-specific context around the same AI result.

### 52.4 Local/Offline AI Policy

The Qur’an/Hifz module must use local/offline AI as much as possible.

Do not use:

- OpenAI API
- Google Speech API
- Whisper API
- Cloud speech recognition APIs
- External AI APIs for recitation checking

The local AI may run as a Python module called by Laravel through the existing AI interface.

### 52.5 Version 1 Scope

Version 1 of Qur’an/Hifz module must focus on:

- Isolated Arabic letter + haraka checking
- Live microphone checking
- Manual recording as backup
- Teacher review
- Supervisor review
- Dean academic oversight
- Admin AI training/improvement
- Student progress tracking

Version 1 must not claim:

- Full Qur’an memorization checking
- Full ayah alignment
- Full word-by-word checking
- Full tajweed checking
- Full continuous recitation checking
- Automatic Hifz pass/fail judgment

### 52.6 Future Scope

Future Qur’an/Hifz versions may add:

- Continuous Qur’an recitation checking
- Ayah text alignment
- Word-by-word checking
- Full haraka detection in words
- Madd detection
- Ghunnah detection
- Tajweed rules
- Memorization mistake detection
- Missed/repeated/added word detection
- AI-assisted teacher review
- Revision recommendations

These must be treated as future advanced features, not Phase 1 features.

### 52.7 Student Features

Student can:

- View assigned Hifz/recitation lessons or tasks
- View assigned Arabic letters and haraka practice items
- Practice selected letter + haraka
- Use live microphone checking
- Use manual record-and-submit mode
- See live result:
  - Expected letter
  - Expected haraka
  - Predicted letter
  - Predicted haraka
  - Letter confidence
  - Haraka confidence
  - Correct / wrong letter / wrong haraka / low confidence
- View previous submissions
- View progress history
- Replay saved manual recordings
- Later view assigned surah/ayah ranges
- Later submit Qur’an recitation recordings
- Later view memorization and revision progress

### 52.8 Live Checking Feature

The Qur’an/Hifz module may use the shared `LiveAudioChecker` component.

Flow:

1. Student opens letter + haraka practice page.
2. Student is shown the expected Arabic sound, for example `بَ`.
3. Student clicks Start Live Check.
4. Browser asks for microphone permission.
5. React uses MediaRecorder API.
6. Capture short audio chunks every 700ms to 1000ms.
7. Ignore silence as much as possible.
8. Send useful chunks to Laravel API using FormData.
9. Laravel stores the chunk temporarily.
10. Laravel calls the local Python prediction service through the AI interface.
11. Python returns JSON.
12. Laravel compares predicted letter and predicted haraka with expected letter and haraka.
13. React updates the screen immediately.

Rules:

- Do not save every live chunk permanently.
- Save only useful live events/results.
- Delete temporary live chunks after prediction.
- Add Start Live Check and Stop Live Check buttons.
- Add loading/checking indicator.
- Add confidence threshold.
- If letter confidence or haraka confidence is below configured threshold, show low-confidence result.
- Add throttling/debounce so backend is not overloaded.
- Add basic silence detection in frontend if possible.
- Add backend cleanup for temporary chunks.

### 52.9 Manual Recording Mode

Manual mode is the backup and review-safe flow.

Flow:

1. Student clicks Record.
2. Student reads one letter + haraka or assigned recitation.
3. Student clicks Stop.
4. Student can replay recording.
5. Student clicks Submit.
6. Laravel stores audio permanently using private media storage.
7. Laravel calls the local AI where applicable.
8. Result is saved.
9. Teacher/supervisor/dean/admin can review later.

Manual recordings must be protected as private media.

Do not expose private storage paths directly.

Use authenticated audio streaming routes or signed URLs.

### 52.10 Teacher Features

Teacher can:

- Assign Hifz/recitation lessons or practice items
- Assign letter + haraka practice items
- View student submissions
- Listen to saved student recordings
- See AI prediction results
- Manually mark result:
  - Correct
  - Wrong letter
  - Wrong haraka
  - Needs practice
  - Unclear audio
- Add teacher comments
- Recommend audio sample for AI training
- Track student progress
- Later assign surah/ayah ranges
- Later mark Qur’an recitation mistakes manually

### 52.11 Supervisor Features

Supervisor can:

- Monitor teachers and students
- View teacher submissions/reviews
- Review student submissions
- Correct AI predictions
- Correct teacher review if needed
- Approve/reject samples for AI training
- View AI review queue
- View AI dataset statistics
- View model versions as read-only
- View low-confidence predictions
- View common mistakes
- View student progress
- View teacher activity

### 52.12 Dean Features

Dean can:

- View whole academic overview
- View supervisors
- View teachers
- View students
- View lessons
- View all student progress
- View all submissions
- View teacher and supervisor reviews
- Listen to saved student recordings
- Correct academic review decisions if needed
- Approve high-quality samples for AI training
- Reject bad/noisy/unclear samples
- View AI dataset statistics
- View AI model versions as read-only
- View AI performance reports
- View teacher performance
- View supervisor performance
- View most common wrong letters
- View most common wrong harakas
- View low-confidence trends
- Export academic progress reports where practical

Dean cannot:

- Delete users
- Change technical settings
- Activate/rollback AI models unless admin later gives permission
- Start AI training unless admin later gives permission

### 52.13 Admin Features

Admin can:

- Manage users
- Manage deans
- Manage supervisors
- Manage teachers
- Manage students
- Manage parents
- Manage Arabic letters
- Manage harakas
- Manage Hifz/recitation settings
- View all submissions
- View all AI predictions
- View AI model status
- Upload/manage approved training dataset files
- Review and approve training samples
- Reject bad/noisy samples
- Start AI retraining
- Activate new model version
- Roll back to previous model version
- View dashboard statistics:
  - Total students
  - Total teachers
  - Total supervisors
  - Total deans
  - Total submissions
  - Average letter confidence
  - Average haraka confidence
  - Most common wrong letters
  - Most common wrong harakas
  - Low-confidence attempts
  - Approved training samples
  - Active model version

### 52.14 Human-in-the-Loop AI Improvement

The Qur’an/Hifz module reuses the shared human-in-the-loop AI improvement workflow.

Workflow:

1. Student submits or live-checks audio.
2. AI predicts letter + haraka if applicable.
3. Teacher/supervisor/dean/admin listens to the audio.
4. Reviewer confirms:
   - AI was correct
   - Wrong letter
   - Wrong haraka
   - Both wrong
   - Unclear audio / reject sample
5. Reviewer sets the verified letter.
6. Reviewer sets the verified haraka.
7. Approved corrected samples can be added to the training dataset.
8. Admin later retrains the local AI model in batches.
9. Save each trained model as a new version.
10. Admin can activate or rollback model versions.

Rules:

- Do not retrain instantly after every correction.
- Collect approved samples first.
- Retrain in batches.
- Keep old model versions.
- Never overwrite the active model without backup.
- Only approved samples should be used for training.
- Bad audio, noisy audio, or unclear pronunciation should be rejected.
- Keep model version history.
- Teachers can recommend samples.
- Supervisors can approve samples.
- Dean can approve important/high-quality samples.
- Admin can approve samples and retrain models.

### 52.15 Qur’an/Hifz Specific Data Model

The Qur’an/Hifz module needs its own future tables for Qur’an-specific learning.

Suggested tables:

- `quran_surahs`
- `quran_ayahs`
- `quran_hifz_assignments`
- `quran_recitation_submissions`
- `quran_mistake_marks`
- `quran_revision_schedules`
- `quran_recitation_reviews`
- `quran_memorization_progress`

Optional later AI tables:

- `quran_ayah_alignment_attempts`
- `quran_word_alignment_attempts`
- `quran_tajweed_rule_checks`
- `quran_ai_recitation_predictions`

Do not duplicate these shared tables:

- `arabic_letters`
- `arabic_harakas`
- `ai_predictions`
- `training_samples`
- `ai_model_versions`

Reuse them through shared interfaces where possible.

### 52.16 quran_surahs Table

Fields:

- ID
- Surah number
- Arabic name
- English name nullable
- Dhivehi name nullable
- Total ayahs
- Sort order
- Timestamps

### 52.17 quran_ayahs Table

Fields:

- ID
- Surah ID
- Ayah number
- Arabic text
- Uthmani text nullable
- Simple text nullable
- Juz number nullable
- Hizb number nullable
- Page number nullable
- Timestamps

### 52.18 quran_hifz_assignments Table

Fields:

- ID
- Student ID
- Teacher ID
- Course ID nullable
- Course offering ID nullable
- Surah ID nullable
- Start ayah number nullable
- End ayah number nullable
- Expected letter ID nullable
- Expected haraka ID nullable
- Assignment type
- Due date nullable
- Status
- Notes nullable
- Timestamps

Assignment types:

- Letter haraka practice
- New memorization
- Revision
- Correction repeat
- Assessment

Statuses:

- Assigned
- In progress
- Submitted
- Needs repeat
- Passed
- Failed
- Cancelled

### 52.19 quran_recitation_submissions Table

Fields:

- ID
- Assignment ID
- Student ID
- Audio media ID
- Mode: live or manual
- Duration seconds nullable
- Submitted at
- Status
- Teacher review ID nullable
- AI prediction ID nullable
- Timestamps

Statuses:

- Submitted
- AI checked
- Teacher reviewed
- Supervisor reviewed
- Dean reviewed
- Needs repeat
- Passed
- Failed
- AI processed later

### 52.20 quran_mistake_marks Table

Fields:

- ID
- Recitation submission ID
- Surah ID nullable
- Ayah number nullable
- Word position nullable
- Expected letter ID nullable
- Expected haraka ID nullable
- Predicted letter ID nullable
- Predicted haraka ID nullable
- Mistake type
- Severity
- Teacher ID
- Comment nullable
- Audio timestamp start nullable
- Audio timestamp end nullable
- Timestamps

Mistake types:

- Wrong letter
- Wrong haraka
- Missed word
- Added word
- Repeated word
- Wrong word
- Pronunciation issue
- Waqf/stop issue
- Madd issue later
- Ghunnah issue later
- Tajweed issue later
- Other

Severity:

- Minor
- Medium
- Major

### 52.21 quran_revision_schedules Table

Fields:

- ID
- Student ID
- Teacher ID nullable
- Surah ID nullable
- Start ayah number nullable
- End ayah number nullable
- Scheduled date
- Frequency nullable
- Status
- Notes nullable
- Timestamps

### 52.22 quran_memorization_progress Table

Fields:

- ID
- Student ID
- Surah ID nullable
- Start ayah number nullable
- End ayah number nullable
- Status
- Last reviewed at nullable
- Strength score nullable
- Mistake count nullable
- Teacher ID nullable
- Timestamps

Statuses:

- Not started
- Learning
- Submitted
- Passed
- Needs revision
- Weak
- Strong

### 52.23 AI Dataset and Model Requirements

The shared pronunciation AI dataset uses:

```text
Parent folder = letter label
Child folder = haraka label
```

Example:

```text
ai/dataset/baa/fatha/sample_001.wav
letter = baa
haraka = fatha

ai/dataset/baa/kasra/sample_001.wav
letter = baa
haraka = kasra
```

The model should be a multi-output CNN:

- Shared CNN feature extractor
- Output 1: letter_output
- Output 2: haraka_output

The model must track:

- Letter accuracy
- Haraka accuracy

Prediction final status logic:

- If prediction success is false, final status is error.
- If letter confidence or haraka confidence is below threshold, final status is low confidence.
- If predicted letter does not match expected letter, final status is wrong letter.
- If predicted letter matches but predicted haraka does not match expected haraka, final status is wrong haraka.
- If both match, final status is correct.

### 52.24 Qur’an/Hifz UI Components

The module should reuse shared React audio and AI components:

- AudioRecorder
- LiveAudioChecker
- ConfidenceBadge
- PredictionResultCard
- ArabicLetterHarakaCard
- AudioPlayer
- ReviewDecisionForm
- TrainingSampleApprovalForm
- ModelVersionCard
- AcademicReportCard

Future Qur’an-specific components:

- HifzAssignmentCard
- RecitationRecorder
- QuranAyahRangePicker
- RecitationMistakeMarker
- RevisionScheduleView
- MemorizationProgressCard

### 52.25 Qur’an/Hifz API Strategy

Do not build duplicate user/course/lesson APIs.

Use main platform APIs for:

- Users
- Courses
- Offerings
- Enrollments
- Media
- Reviews
- Reports

Use shared Arabic AI endpoints for:

- Letter + haraka prediction
- Training samples
- AI model versions
- Dataset statistics

Add Qur’an-specific endpoints only for Qur’an-specific operations.

Suggested future endpoints:

```text
GET /api/v1/quran/surahs
GET /api/v1/quran/surahs/{id}/ayahs
POST /api/v1/quran/hifz-assignments
GET /api/v1/quran/hifz-assignments
POST /api/v1/quran/recitation-submissions
POST /api/v1/quran/recitation-submissions/{id}/review
POST /api/v1/quran/recitation-submissions/{id}/mistake-marks
GET /api/v1/quran/revision-schedule
GET /api/v1/quran/memorization-progress
```

### 52.26 Security Rules

Security requirements:

- Use Laravel validation.
- Use authenticated routes.
- Use role middleware and policies.
- Students can only see their own submissions.
- Teachers can only review assigned students where possible.
- Supervisors can review across teachers.
- Deans can view academic data across the institute.
- Admin can see all.
- Do not expose private storage paths directly.
- Use controlled audio streaming route or signed URL for saved recordings.
- Validate audio file type and max size, for example 10MB.
- Do not send audio to external services.

### 52.27 Feature Flag

The Qur’an/Hifz module should be feature-flagged.

Example setting:

```env
QURAN_HIFZ_MODULE_ENABLED=false
```

The main platform must work even if the Qur’an/Hifz module is disabled.

### 52.28 Recommended Implementation Timing

Do not build Qur’an/Hifz in Phase 1A.

Do not build Qur’an/Hifz in Phase 1B.

Recommended order:

1. Phase 1A: General platform foundation.
2. Phase 1B: Offerings, delivery modes, remaining blocks, PWA/i18n.
3. Phase 2: General activities, assessments, submissions, teacher review.
4. Phase 3: Reports, parent dashboard, certificates.
5. Arabic Skills Module A: non-AI Arabic skills.
6. Arabic Skills Module B: local letter + haraka pronunciation AI.
7. Qur’an/Hifz Module A: human-first Hifz assignments, recitation submissions, teacher mistake marking, revision schedules.
8. Qur’an/Hifz Module B: reuse local letter + haraka AI inside Hifz practice.
9. Qur’an/Hifz Module C: future ayah/word alignment and tajweed AI.

### 52.29 Qur’an/Hifz Acceptance Criteria

The Qur’an/Hifz module is acceptable only when:

- It uses the main platform users, roles, courses, offerings, media, review, and reporting systems.
- It does not duplicate the LMS structure.
- It stays separate from the Arabic Skills module.
- It reuses shared Arabic pronunciation AI where applicable.
- Letter + haraka checking treats haraka as critical.
- Teachers can assign practice and/or surah/ayah ranges.
- Students can submit live checks and manual recordings.
- Teachers can mark mistakes manually.
- Students can see corrections and resubmit.
- Progress is tracked by letter/haraka practice and later by surah/ayah range.
- Supervisor/dean can monitor academic quality.
- Admin can manage AI training and model versions through shared AI infrastructure.
- Future full Qur’an checking is clearly marked as future unless actually implemented.
- The main platform works even if the Qur’an/Hifz module is disabled.

---

## 53. Testing Scope

Do not aim for blanket coverage.

Write tests for important business rules.

### Phase 1A Feature Tests

Test:

1. Role-based access

Each role's permitted and forbidden actions through policies.

2. Course publishing transitions

Test:

- Draft to In Review
- In Review to Published
- In Review to Draft/Changes Requested
- Published to Archived
- Invalid transitions rejected

3. Lesson revision creation

Test:

- Publishing a lesson creates an immutable revision.
- Draft edits do not change what students see.
- Student progress references the revision completed.
- Reordering draft blocks does not corrupt historical completed progress.

4. Block reordering

Test:

- Blocks reorder correctly.
- Position values persist.
- Lesson revision snapshots preserve historical order.
- Lesson player receives blocks in correct order.

5. Self-learning enrollment

Test:

- Free/self-learning enrollment works.
- Manual self-learning enrollment works.
- Access denied when not eligible.
- Student cannot access a non-enrolled private course.

6. Lesson player data/rendering contract

Test each Phase 1A block type using seeded dynamic data:

- Text
- Rich Text
- Image
- Audio
- Video
- PDF
- Instruction

7. Progress tracking

Test:

- Lesson completion updates progress.
- Course percentage is correct.
- Completed lesson remains completed after draft block edit/reorder.
- Progress references the correct lesson revision.

8. Auth rate limiting

Test:

- Login throttling
- Registration throttling
- Password reset throttling
- OTP send throttling placeholder
- OTP verification throttling placeholder

9. Inertia shared props

Test:

- Auth user shared correctly
- Locale shared correctly
- Direction shared correctly
- Permissions summary shared correctly

10. Domain boundary architecture tests

Use architecture tests, such as Pest arch tests, to verify:

- Domains do not import other domains' internal Eloquent models.
- Domains communicate through public actions/services, DTOs, interfaces, or events.
- Controllers stay thin.
- Third-party SDKs are not used directly inside domain business logic.

### Phase 1B Feature Tests

Test:

1. Offering status transitions

Test:

- Draft to Open
- Open to In Progress
- In Progress to Completed
- Open to Cancelled
- Completed to Archived
- Invalid transitions rejected

2. Offering content pinning

Test:

- Offering pins to a course content version when opened.
- Students enrolled in a pinned offering see the pinned content.
- Draft edits do not affect pinned offering content.
- Admin can explicitly re-pin an offering.
- Re-pinning is logged.
- Re-pinning never happens automatically.

3. Self-learning latest vs pinned mode

Test:

- Self-learning default mode uses latest published.
- Self-learning pinned mode uses pinned content.
- Switching mode follows explicit admin configuration.

4. Enrollment and seat-limit concurrency

Test:

- Enrollment linked to offering works.
- Seat limit is respected.
- Simulate two concurrent enrollments against one remaining seat.
- Exactly one succeeds.
- The failed enrollment receives a clear business error.
- Student cannot access non-enrolled private offering/course.

5. Phase 1B lesson player block rendering

Test each Phase 1B block type using seeded dynamic data:

- Glossary / Term
- Dialogue
- Flashcard
- Download
- Quiz Embed
- Assignment Embed

6. Progress tracking in offering context

Test:

- Offering-context progress is correct.
- Completed lesson remains completed after offering content pinning/re-pinning behavior is applied according to rules.

7. Unlock rules

Test:

- All lessons open
- Previous lesson required
- Offering start date required
- Quiz pass required placeholder
- Assignment submission required placeholder
- Teacher approval required placeholder
- Date-based unlock
- Payment required placeholder

### Unit Tests

Only write unit tests where real logic exists.

Test:

- Progress percentage calculation
- Offering completion calculation
- Unlock rule evaluation
- Completion rule evaluation
- Block data validation per type
- Text direction settings validation
- Text normalization
- Arabic normalization when enabled
- Lesson revision snapshot creation
- Offering pinning behavior
- Assessment attempt snapshot logic in Phase 2
- Auto-marking logic in Phase 2

### Frontend Testing

Phase 1 does not need heavy browser testing.

But add lightweight component tests only where useful for:

- Lesson block rendering components
- Block builder validation behavior
- RTL direction rendering basics
- Audio recorder abstraction fallback behavior
- Offering mode display behavior

Do not overbuild frontend tests in Phase 1.

### Arabic Normalization Tests

When Arabic normalization is enabled, test:

- Diacritics/tashkeel differences
- Alef variants
- Hamza variants
- Taa marbuta tolerance
- Whitespace differences
- Punctuation removal
- Strict vs lenient settings

### Factories

Use factories for all test data.

Factories must include realistic:

- English content
- Dhivehi content
- Arabic content
- Courses
- Offerings
- Sessions
- Lessons
- Lesson revisions
- Blocks
- Questions
- Glossary terms

If a test needs hardcoded course content inside application code, the architecture has failed.

### Browser Tests

Skip Dusk/browser tests in Phase 1.

API/feature tests, architecture tests, concurrency tests, and limited component tests are enough.

---

## 54. Development Rules

1. Do not hardcode subject content.

No hardcoded:

- Course content
- Lesson plans
- Vocabulary
- Questions
- Assessments
- Curriculum
- Course sequence

2. Everything must be admin-managed.

3. Keep the platform general.

Arabic is supported, but the platform is not Arabic-only.

4. Support multiple delivery modes.

Do not design the system as self-learning only.

5. Course and offering must be separate.

Course is the reusable content.

Offering is the delivery mode/batch/schedule/access.

6. Split Phase 1 into Phase 1A and Phase 1B.

Do not start Phase 1B until Phase 1A is stable.

7. Use Inertia + React.

Do not switch to Vue or Blade-only for interactive features.

8. Do not over-engineer.

Build a modular monolith, not microservices.

9. Use backend policies.

Do not rely only on frontend restrictions.

10. Keep controllers thin.

Business logic belongs in domain services/actions.

11. Enforce domain boundaries.

Domains communicate through public actions/services, DTOs, interfaces, or events.

A domain must not directly import or query another domain's internal Eloquent models.

12. Use architecture tests.

Add architecture tests to prevent modularity violations from entering CI.

13. Bind swappable services to interfaces.

Media storage, video provider, notifications, payments, certificates, settings, and other replaceable services must be accessed through interfaces.

14. Do not depend directly on third-party SDKs inside domain logic.

Wrap SDKs behind domain-owned interfaces.

15. Build RTL from day one.

Arabic and Dhivehi support must not be added later as an afterthought.

16. Do not create an RTL Text Block.

Text direction is a setting on text-capable blocks.

17. Use logical CSS properties.

Do not hardcode left/right layouts.

18. Use a centralized media pipeline.

All uploads must use the same media system.

19. Use queues for media processing.

Do not generate thumbnails inline.

20. Keep progress safe.

Published lesson edits must not corrupt student history.

21. Use immutable lesson revisions.

Students must see published revisions, not draft block records.

22. Pin offering content versions.

Scheduled offerings pin to a course content version when opened unless explicitly re-pinned.

23. Keep assessment attempts safe.

Question edits must not alter previous attempts.

24. Keep offering history safe.

Completed/cancelled offerings with students must not be hard-deleted.

25. Enforce seat limits safely.

Use database transactions/locks/atomic operations, not unsafe count-then-insert logic.

26. Store timestamps in UTC.

Display using configurable platform timezone.

27. Protect private files.

Paid media and student submissions must not be publicly accessible.

28. Keep mobile path clean.

Do not scatter platform-specific logic across React components.

29. Build in phases.

Do not try to build all phases at once.

---

## 55. Cursor Build Instruction

Start with Phase 1A only.

Do not implement all phases at once.

Do not start Phase 1B until Phase 1A is stable.

### First implement Phase 1A

- Laravel 12
- Inertia.js
- React
- Vite
- Auth and roles
- Auth rate limiting
- Users
- Parent-child relationship model
- Settings foundation
- Subjects/audiences/levels
- Courses domain
- Course CRUD
- Course status workflow
- Modules
- Lessons
- Lesson revision foundation
- Phase 1A dynamic content blocks:
  - Text
  - Rich Text
  - Image
  - Audio
  - Video
  - PDF
  - Instruction
- React content block builder
- Block validation
- Block reorder
- Media upload pipeline
- Self-learning enrollment
- React student dashboard basic version
- React lesson player
- Basic progress tracking
- Domain services/actions
- Domain boundary architecture tests
- Phase 1A tests

### Then implement Phase 1B after Phase 1A is stable

- Course offerings
- Delivery modes
- Offering content version pinning
- Basic sessions foundation
- Attendance data model foundation
- Enrollment linked to offering
- Seat-limit concurrency-safe enrollment
- Phase 1B content blocks:
  - Glossary / Term
  - Dialogue
  - Flashcard
  - Download
  - Quiz Embed
  - Assignment Embed
- Unlock rule evaluator
- Completion rule foundation
- PWA manifest
- Service worker
- Full RTL/localization polish
- Dhivehi/Thaana font verification
- Phase 1B tests

After Phase 1B is complete, create a clear roadmap and task list for Phase 2.

Do not implement AI, full payments, certificates, advanced reports, Capacitor packaging, advanced attendance UI, or advanced assessments until Phase 1B is stable.

---


## 57. Build Strategy Addendum and Cursor Working Rules

This section controls how the project should be built.

Where this section conflicts with earlier feature sections, this Build Strategy Addendum wins.

Where this section is silent, follow the main specification.

The purpose of this addendum is to prevent scope creep, protect the architecture, and make the project buildable in Cursor.

---

## 57.1 Context Strategy

Do not attempt to keep the entire full specification in active working context all the time.

Use a layered context strategy.

### Always Keep in Context

Always keep these in context:

- Core Design Principle
- Development Rules
- Cursor Build Instruction
- Current active phase/slice
- This Build Strategy Addendum

### Active Phase Only

When building Phase 1A, load only Phase 1A-relevant sections.

Do not implement Phase 1B, Phase 2, Phase 3, Arabic Skills, Qur’an/Hifz, AI, payments, certificates, or mobile packaging while building Phase 1A.

Future sections should be read only to avoid blocking them architecturally.

### Feature Mapping Rule

Before implementing a feature, briefly state:

- Which spec section it comes from
- Which phase/slice it belongs to
- Which development rules apply

If a feature cannot be mapped to the spec, do not implement it.

For small UI wording/layout choices, make a sensible default and document it in `docs/STATUS.md`.

For decisions affecting data safety, architecture, security, future compatibility, or historical records, stop and ask for a decision.

Never implement future-phase features “while you are already there.”

Scope creep is a build failure.

---

## 57.2 Phase 1A Discipline

Treat Phase 1A as a real product, not a warm-up.

Phase 1A must be built in vertical slices.

Do not build all migrations first, then all models, then all controllers, then all UI.

Each slice must go end-to-end:

```text
Database → Domain service/action → Controller → React UI → Tests → STATUS.md update
```

### Recommended Phase 1A Slice Order

#### Slice 1: Auth, Roles, Users, Settings Foundation

Build:

- Authentication
- Roles and permissions
- User management foundation
- Settings foundation
- Rate limiting
- Basic layout/authenticated shell

#### Slice 2: Taxonomy (Subjects, Audiences, Levels), Course CRUD, Status Workflow

Build:

- Subject CRUD (hierarchical `parent_id`)
- Audience CRUD
- Level CRUD
- Course CRUD
- Course status workflow
- Policies and tests

#### Slice 3: Modules, Lessons, Lesson Revision Mechanism

Build:

- Modules
- Lessons
- Draft lesson editing
- Publish lesson
- Immutable lesson revision snapshot
- Player reads published revision only
- Revision tests
- ADR for lesson revision decision

#### Slice 4: Core Text Blocks

Build only:

- Text block
- Rich Text block
- Instruction block
- Builder UI
- Player rendering
- Revision serialization
- Validation and tests

Do not add media blocks before the revision mechanism is proven.

#### Slice 5: Media Pipeline and Media Blocks

Build:

- Media upload pipeline
- Private media rules
- Queued processing
- Image block
- Audio block
- Video block
- PDF block

#### Slice 6: Self-Learning Enrollment, Student Dashboard, Progress

Build:

- Self-learning enrollment
- Student dashboard
- Lesson progress
- Course progress
- Access control
- Progress tests

#### Slice 7: Parent-Child Relationship, Polish, Architecture Tests

Build:

- Guardian/student relationship
- Parent-child policy foundation
- Architecture test hardening
- RTL/i18n polish required for Phase 1A
- Final Phase 1A cleanup

### Slice Completion Rule

After each slice:

- Run the full test suite.
- Run architecture tests.
- Run lint/static analysis.
- Update `docs/STATUS.md`.
- Do not start the next slice with failing tests.

Tests are part of each slice, not a later cleanup task.

---

## 57.3 De-risk Lesson Revisions First

The immutable lesson revision system is one of the highest-risk architecture pieces.

Build a minimal end-to-end revision prototype before adding many block types.

### Required Revision Flow

The system must prove this flow:

```text
Draft lesson with blocks
→ Publish
→ Immutable revision snapshot created
→ Student/player reads only the published revision
→ Author edits draft
→ Student still sees old revision
→ Author republishes
→ New revision created
```

### Required Revision Tests

Write tests proving:

- Editing draft blocks never changes what a student sees from a published revision.
- Deleting draft blocks never mutates an existing revision.
- Reordering draft blocks never mutates an existing revision.
- Progress records reference revision-stable identifiers.
- Republishing does not corrupt progress.

### Snapshot Decision

Use a single denormalized JSON snapshot per lesson revision unless a concrete requirement forces versioned block rows.

Preferred default:

```text
lesson_revisions.snapshot_json = ordered immutable block list + lesson render data
```

Reason:

- Simple lesson player reads
- Stable historical rendering
- Easier export later
- Fewer joins
- Lower risk of historical mutation

Record this decision in:

```text
docs/decisions/ADR-001-lesson-revisions.md
```

The ADR must include:

- Decision
- Context
- Alternatives considered
- Trade-offs
- Consequences
- How future offering pinning will reuse this mechanism

### Offering Pinning Relationship

In Phase 1B, offering pinning must reuse the same revision/content version mechanism.

Do not invent a second versioning system for offerings.

An offering pins to a course content version, which is a stable set of published lesson revisions.

---

## 57.4 Architectural Decision Records

Maintain an ADR folder:

```text
docs/decisions/
```

Create ADRs for significant decisions.

Required ADRs:

- `ADR-001-lesson-revisions.md`
- `ADR-002-domain-boundaries.md`
- `ADR-003-media-storage.md`
- `ADR-004-auth-and-roles.md`
- `ADR-005-course-offering-pinning.md` in Phase 1B

When the spec is ambiguous and the decision affects architecture, data safety, security, or future compatibility:

1. Propose 2–3 options.
2. Explain trade-offs.
3. Recommend one option.
4. Wait for decision.
5. Record the decision as an ADR.

---

## 57.5 Infrastructure, Deployment, and Operations

The project targets a single-server modular monolith deployment.

### Production Target

Recommended production stack:

- PHP-FPM
- Nginx
- MySQL 8
- Redis for cache/queues/sessions
- Queue worker process
- Laravel scheduler cron
- Supervisor or Horizon for queue process management

### Development and Limited Hosting Fallback

Redis is preferred.

However, if local development or temporary hosting cannot support Redis, a database queue may be used temporarily.

Do not remove the queue abstraction.

Production should be designed for Redis-backed queues.

### Queues

Use queues from day one.

Required queue names:

- `default`
- `media`
- `notifications`

Failed jobs table must be enabled.

Document retry and failure policy.

Media processing must not happen inline during request/response.

Emails must not be sent inline during request/response.

### Deployment Documentation

Create:

```text
docs/deployment.md
```

It must document:

- Server assumptions
- PHP extensions
- Nginx/PHP-FPM setup
- MySQL setup
- Redis setup
- Queue worker setup
- Scheduler cron
- Storage setup
- Environment variables
- Deployment steps
- Migration procedure
- Maintenance mode procedure
- Rollback notes

### Backups

Document backup strategy in `docs/deployment.md`.

Minimum backup plan:

- Nightly database backup
- Media/private storage backup
- Retention policy
- Restore procedure

A backup is not considered valid unless the restore procedure has been tested.

### Logging and Error Tracking

Use structured logging where practical.

Use daily log rotation.

Create an error tracking interface so Sentry, Flare, or another provider can be swapped later.

No third-party error-tracking SDK should be called directly from domain business logic.

### Health Check

Add a simple health-check endpoint.

It should check:

- Application boot
- Database connection
- Redis connection where enabled
- Queue heartbeat where practical

---

## 57.6 CI and Code Quality

CI must run on every push.

Minimum CI checks:

- Laravel Pint
- Larastan
- Full test suite
- Architecture tests
- Frontend lint/build where applicable

### Larastan Level

Target Larastan level 6 before Phase 1A is marked complete.

It is acceptable to start with level 5 or 6 during scaffolding, but do not permanently ignore static-analysis errors.

Architecture test failures must block merge.

---

## 57.7 Email and Notification Foundation

The full notification module is later phase, but Phase 1 still needs transactional email foundation.

Implement:

- Laravel mail
- SMTP-ready production config
- Log/Mailpit development config
- Queued emails only
- Notification dispatcher interface

Phase 1 transactional emails:

- Registration verification if email registration is enabled
- Password reset
- Enrollment confirmation

All mail dispatch must go through a domain-owned interface:

```text
NotificationDispatcher
```

This allows the future notification module to replace internals without changing call sites.

### OTP/SMS Compatibility

Keep the system OTP/SMS-ready.

Do not force email verification if the business later chooses phone-first or OTP-first registration.

Email foundation and OTP readiness must coexist.

---

## 57.8 Localization, RTL, and Fonts

Localization must start from the first slice.

All main UI strings must use localization.

Temporary literal strings during active development are allowed only inside a slice, but they must be removed before the slice is marked done.

### Required Locales

Phase 1A:

- English complete
- Dhivehi translation files scaffolded
- Arabic translation files scaffolded

Phase 1B:

- Full i18n polish
- RTL/LTR polish
- Dhivehi/Thaana visual verification
- Arabic visual verification

### Visual Verification Page

Create an admin-only hidden visual verification route.

It must render sample text for:

- English
- Dhivehi/Thaana
- Arabic

It must show:

- All major font sizes
- All major font weights
- LTR direction
- RTL direction
- Buttons
- Form fields
- Cards
- Lesson player samples

This makes RTL and font support testable.

### Logical CSS

Use logical CSS properties.

Avoid physical direction properties.

Preferred:

- `margin-inline-start`
- `margin-inline-end`
- `padding-inline-start`
- `padding-inline-end`
- `inset-inline-start`
- `inset-inline-end`
- `text-align: start`
- `text-align: end`

Add a stylelint rule banning physical `left` and `right` properties where practical.

---

## 57.9 Performance and Scale Assumptions

Document the initial scale assumptions.

Baseline target:

- Up to 2,000 registered users
- Up to 300 concurrent users during peak
- Up to 50 active offerings

Do not over-optimize beyond this too early.

Do not design below this.

### Lesson Player Performance

Lesson player payloads must come from published revision snapshots.

Target:

```text
O(1) query pattern per lesson load
```

Do not load lesson blocks with one query per block.

Use eager loading and snapshot reads.

Enable N+1 detection in development.

Laravel `preventLazyLoading` should be enabled in non-production.

### Media Performance

Media should not be streamed through PHP when a signed redirect or storage-native range request path is possible.

Private media must remain protected.

### Database Indexes

Add indexes deliberately with each migration.

Document indexes for hot query paths.

Seat-limit enrollment must use database-level concurrency safety and must have a CI concurrency test.

---

## 57.10 Initial Content and Seeding Strategy

Production seeders must contain only structural/reference data.

Allowed production seed data:

- Roles
- Permissions
- Default settings
- Block type registry
- Arabic letters when Arabic module arrives
- Arabic harakas when Arabic module arrives
- Qur’an reference data when Qur’an module arrives

Not allowed in production seeders:

- Sample courses
- Sample lessons
- Sample assessments
- Sample student answers
- Demo content

### Demo Seeder

Create a separate demo seeder for development and QA only.

The demo seeder may create:

- Sample English course
- Sample Arabic course
- Sample Dhivehi course
- Sample lessons for each major block type
- Sample RTL/LTR content
- Sample student progress

The demo seeder must never run in production.

### Import/Export Roadmap

Course import/export is Phase 2+.

However, lesson revision snapshots should be designed so JSON export is easy later.

Document this in `ADR-001-lesson-revisions.md`.

---

## 57.11 Security Hardening Checklist

Security is part of Phase 1, not a later afterthought.

Required:

- Authorization tests for every policy.
- Wrong-role test cases for each feature.
- MIME sniffing for uploads, not extension-only checks.
- Upload size limits per media type from settings.
- Non-guessable stored file names.
- Signed expiring URLs or authenticated streaming for private media.
- Submission downloads authorized by enrollment/teacher assignment.
- Laravel CSRF/session/password defaults must remain intact.
- Do not hand-roll authentication primitives.
- Audit-relevant actions must be logged.
- Child/parent visibility rules enforced by backend policies.

### Activity Log

Create a simple `activity_log` mechanism or interface-bound activity logger.

Audit these actions:

- Publish revision
- Archive course
- Delete draft content
- Open offering
- Pin/re-pin offering
- Manual enrollment
- Grade override
- Teacher review correction
- Supervisor/dean override
- Model activation/rollback later

The activity logger should be interface-bound so it can later be replaced with a package or external logging provider.

---

## 57.12 AI Boundaries

In Phase 1A and Phase 1B, create zero AI code.

Only avoid blocking future AI.

Phase 1 must support:

- Audio submissions
- Private audio storage
- Review status lifecycle
- Teacher review flow later
- Arbitrary JSON metadata on submissions/activities where needed

This is the entire Phase 1 AI obligation.

Teacher review is the real v1 pronunciation feature.

The product must be useful even if AI is never added.

Do not promise AI checking in user-facing UI copy before AI actually exists.

### Future Arabic AI Module Order

When Arabic AI begins, treat it as a separate sub-project.

Recommended order:

1. Dataset collection tooling
2. Labeling workflow
3. Review/approval workflow
4. Model training
5. Held-out accuracy measurement
6. Laravel interface/stub integration
7. Real Python process integration
8. UI feedback

Do not start integration before a model exists with measured accuracy on a held-out set.

### Shared Pronunciation AI Interface

When AI work begins, define a Laravel interface first.

Example:

```text
PronunciationPredictionInterface
```

Create a stub/fake implementation before using the real Python process.

The main platform must never depend on the Python process existing.

The shared AI interface can later serve both:

- Arabic Skills module
- Qur’an/Hifz module

---

## 57.13 Cursor Working Agreements

Cursor must follow these working agreements:

1. Never modify published revisions, assessment attempt snapshots, or historical enrollment/attendance records directly.
2. Never add a third-party package without stating:
   - What it is for
   - Why built-ins are insufficient
   - Maintenance status
3. Each PR/commit batch maps to exactly one slice or sub-task.
4. Do not mix drive-by refactors with feature work.
5. Keep `docs/STATUS.md` updated.
6. All code comments, identifiers, and documentation must be in English.
7. All user-facing strings must be localized.
8. If a change seems to require mutating historical records, stop and ask.
9. If a package or architecture choice affects long-term maintainability, record an ADR.
10. Keep the system shippable without Arabic AI and without Qur’an AI.

---

## 57.14 Per-Slice Definition of Done

A slice is done only when all of the following are true:

- Feature tests pass.
- Authorization failure tests pass.
- Architecture tests pass.
- Factories exist for every new model.
- No hardcoded subject content exists.
- No production seeder contains demo course content.
- UI is responsive and touch-friendly.
- RTL mode works for the relevant UI.
- Visual verification page renders correctly for affected components where applicable.
- `docs/STATUS.md` is updated.
- Relevant ADRs are updated.
- No new lint/static-analysis violations exist.
- No failing queue jobs are ignored.
- No private media is exposed publicly.

---

## 58. Final Acceptance Criteria

The final system must be a **general learning platform and course builder**.

The codebase provides the learning engine.

The dashboard provides the course-building tools.

The content comes from the database.

The platform must support many subjects and course types.

The platform must support multiple delivery modes:

- Self-learning
- Face-to-face
- Live online
- Blended
- Hybrid

The system must separate:

```text
Course = reusable content/template
Course Offering = delivery mode, batch, schedule, access, and attendance rules
```

Arabic, Dhivehi, and English must be supported, but no subject should control the architecture.

The frontend must be Inertia + React.

The web app must be PWA-ready.

The mobile path must remain clean for Capacitor later.

The Arabic Skills + Local AI module must be treated as a future platform module, not as a separate duplicate website.

The Qur’an/Hifz Recitation module must also be treated as a future platform module, separate from Arabic Skills but sharing core platform services and shared pronunciation AI.

The Arabic module must use the main platform's users, roles, courses, lessons, activities, submissions, reviews, and progress system.

The Qur’an/Hifz module must use the main platform's users, roles, courses, offerings, media, review, and reporting systems while keeping Qur’an-specific memorization and recitation structures separate.

The most important rules:

```text
No subject, lesson, curriculum, vocabulary, question, assessment, or course structure should be hardcoded.

Do not design the platform as self-learning only.

Use course offerings to support different delivery modes.

Do not build the Arabic teaching plan as a second LMS.

Merge Arabic listening, speaking, reading, writing, and local AI features as an Arabic Skills module inside the main platform.

Do not mix Qur’an/Hifz into Arabic Skills.

Merge Qur’an memorization, recitation submissions, haraka-critical letter checking, ayah tracking, revision schedules, teacher mistake marking, and future recitation AI as a separate Qur’an/Hifz module inside the main platform.
```
