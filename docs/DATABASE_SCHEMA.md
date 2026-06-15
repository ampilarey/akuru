# Database Schema - Akuru Institute LMS

## Overview
Complete database schema documentation for the Akuru Institute Learning Management System with 66+ tables covering user management, Islamic education, communications, and more.

---

## 📊 Database Statistics

- **Total Tables**: 66+
- **Primary Database**: `akuru_institute`
- **Engine**: MySQL 8.0+
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci

---

## 🏗️ Core Tables Structure

### User Management & Authentication

#### `users` - Core User Accounts
```sql
- id (bigint, primary key)
- name (varchar) - Full name
- email (varchar, unique) - Email address
- email_verified_at (timestamp, nullable)
- password (varchar) - Encrypted password
- phone (varchar, nullable) - Phone number for OTP
- remember_token (varchar, nullable)
- created_at, updated_at (timestamps)
```

#### `otps` - OTP Authentication
```sql
- id (bigint, primary key)
- phone (varchar) - Phone number
- otp_code (varchar) - 6-digit OTP
- expires_at (timestamp) - OTP expiration
- is_used (boolean) - Used status
- created_at, updated_at (timestamps)
```

#### `sessions` - User Sessions
```sql
- id (varchar, primary key)
- user_id (bigint, foreign key → users.id)
- ip_address (varchar, nullable)
- user_agent (text, nullable)
- payload (longtext)
- last_activity (integer, indexed)
```

### Role & Permission System

#### `roles` - User Roles
```sql
- id (bigint, primary key)
- name (varchar, unique) - Role name
- guard_name (varchar)
- created_at, updated_at (timestamps)
```

#### `permissions` - System Permissions
```sql
- id (bigint, primary key)
- name (varchar, unique) - Permission name
- guard_name (varchar)
- created_at, updated_at (timestamps)
```

#### `model_has_permissions` - Model Permissions
#### `model_has_roles` - Model Roles
#### `role_has_permissions` - Role-Permission Mapping

### Educational Entities

#### `schools` - School Information
```sql
- id (bigint, primary key)
- name (varchar)
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- address (text, nullable)
- phone (varchar, nullable)
- email (varchar, nullable)
- logo (varchar, nullable)
- created_at, updated_at (timestamps)
```

#### `classes` - Class/Grade Management
```sql
- id (bigint, primary key)
- school_id (bigint, foreign key → schools.id)
- name (varchar) - Class name
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- grade_level (integer) - Grade level number
- max_students (integer, default: 30)
- academic_year (varchar) - e.g., "2024-2025"
- status (enum: active, inactive)
- created_at, updated_at (timestamps)
```

#### `subjects` - Subject Management
```sql
- id (bigint, primary key)
- name (varchar) - Subject name
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- code (varchar, unique) - Subject code
- description (text, nullable)
- is_active (boolean, default: true)
- created_at, updated_at (timestamps)
```

### Student Management

#### `students` - Student Profiles
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key → users.id)
- school_id (bigint, foreign key → schools.id)
- class_id (bigint, foreign key → classes.id)
- student_id (varchar, unique) - School-specific ID
- first_name, last_name (varchar)
- first_name_arabic, last_name_arabic (varchar, nullable)
- first_name_dhivehi, last_name_dhivehi (varchar, nullable)
- date_of_birth (date)
- gender (enum: male, female)
- national_id (varchar, nullable)
- phone (varchar, nullable)
- address (text, nullable)
- emergency_contact_name, emergency_contact_phone (varchar, nullable)
- photo (varchar, nullable) - Profile photo path
- admission_date (date)
- status (enum: active, inactive, graduated, transferred)
- notes (text, nullable)
- created_at, updated_at (timestamps)
```

#### `parents` - Parent/Guardian Information
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key → users.id)
- first_name, last_name (varchar)
- first_name_arabic, last_name_arabic (varchar, nullable)
- first_name_dhivehi, last_name_dhivehi (varchar, nullable)
- phone (varchar, nullable)
- email (varchar, nullable)
- address (text, nullable)
- occupation (varchar, nullable)
- relationship (varchar) - Relationship to student
- created_at, updated_at (timestamps)
```

#### `student_parent` - Student-Parent Relationships
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- parent_id (bigint, foreign key → parents.id)
- relationship_type (enum: father, mother, guardian)
- is_primary (boolean, default: false)
- created_at, updated_at (timestamps)
```

### Teacher Management

#### `teachers` - Teacher Profiles
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key → users.id)
- school_id (bigint, foreign key → schools.id)
- employee_id (varchar, unique) - Employee ID
- first_name, last_name (varchar)
- first_name_arabic, last_name_arabic (varchar, nullable)
- first_name_dhivehi, last_name_dhivehi (varchar, nullable)
- phone (varchar, nullable)
- address (text, nullable)
- qualification (text, nullable)
- specialization (varchar, nullable)
- hire_date (date)
- status (enum: active, inactive, terminated)
- created_at, updated_at (timestamps)
```

#### `teacher_subject` - Teacher-Subject Assignments
```sql
- id (bigint, primary key)
- teacher_id (bigint, foreign key → teachers.id)
- subject_id (bigint, foreign key → subjects.id)
- created_at, updated_at (timestamps)
```

#### `class_subject` - Class-Subject Relationships
```sql
- id (bigint, primary key)
- class_id (bigint, foreign key → classes.id)
- subject_id (bigint, foreign key → subjects.id)
- teacher_id (bigint, foreign key → teachers.id)
- created_at, updated_at (timestamps)
```

### Islamic Education System

#### `surahs` - Quran Surah Database
```sql
- id (bigint, primary key)
- number (integer) - Surah number (1-114)
- name_latin (varchar) - e.g., "Al-Fatiha"
- name_arabic (varchar) - e.g., "الفاتحة"
- name_english (varchar) - e.g., "The Opening"
- revelation_place (enum: mecca, medina)
- ayah_count (integer) - Number of verses
- created_at, updated_at (timestamps)
```

#### `quran_progress` - Quran Memorization Tracking
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- teacher_id (bigint, foreign key → teachers.id)
- surah_name (varchar) - e.g., "Al-Fatiha"
- surah_name_arabic (varchar) - e.g., "الفاتحة"
- surah_number (integer) - 1-114
- from_ayah, to_ayah (integer, nullable) - Verse range
- type (enum: memorization, recitation, revision)
- status (enum: in_progress, completed, needs_revision)
- accuracy_percentage (integer, nullable) - 0-100
- teacher_notes, teacher_notes_arabic (text, nullable)
- date_completed (date, nullable)
- last_revision_date (date, nullable)
- revision_count (integer, default: 0)
- created_at, updated_at (timestamps)
```

#### `recitation_practices` - Audio Recording Submissions
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- teacher_id (bigint, foreign key → teachers.id)
- surah_number (integer) - Surah being practiced
- from_ayah, to_ayah (integer) - Verse range
- audio_file_path (varchar) - Path to audio file
- feedback (text, nullable) - Teacher feedback
- rating (integer, nullable) - 1-5 rating
- created_at, updated_at (timestamps)
```

#### `tajweed_feedback` - Quran Recitation Feedback
```sql
- id (bigint, primary key)
- recitation_practice_id (bigint, foreign key → recitation_practices.id)
- feedback_type (enum: pronunciation, rhythm, tajweed_rules)
- feedback_text (text)
- feedback_text_arabic (text, nullable)
- created_at, updated_at (timestamps)
```

### Academic Management

#### `attendances` - Attendance Tracking
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- class_id (bigint, foreign key → classes.id)
- subject_id (bigint, foreign key → subjects.id)
- teacher_id (bigint, foreign key → teachers.id)
- date (date)
- status (enum: present, absent, late, excused)
- period_number (integer, nullable) - Period in the day
- notes (text, nullable)
- created_at, updated_at (timestamps)
```

#### `grades` - Grade Management
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- subject_id (bigint, foreign key → subjects.id)
- teacher_id (bigint, foreign key → teachers.id)
- class_id (bigint, foreign key → classes.id)
- assignment_id (bigint, foreign key → assignments.id, nullable)
- quiz_id (bigint, foreign key → quizzes.id, nullable)
- grade_type (enum: assignment, quiz, exam, practical, participation)
- score_obtained (decimal) - Actual score
- maximum_score (decimal) - Maximum possible score
- percentage (decimal) - Calculated percentage
- letter_grade (varchar, nullable) - A+, A, B+, etc.
- comments (text, nullable)
- grading_date (date)
- created_at, updated_at (timestamps)
```

#### `timetables` - Class Schedules
```sql
- id (bigint, primary key)
- class_id (bigint, foreign key → classes.id)
- subject_id (bigint, foreign key → subjects.id)
- teacher_id (bigint, foreign key → teachers.id)
- day_of_week (enum: monday, tuesday, wednesday, thursday, friday, saturday, sunday)
- period_number (integer) - Period in the day
- start_time (time)
- end_time (time)
- academic_year (varchar)
- is_active (boolean, default: true)
- created_at, updated_at (timestamps)
```

#### `periods` - Daily Period Schedule
```sql
- id (bigint, primary key)
- period_number (integer) - Period number (1, 2, 3, etc.)
- name (varchar) - Period name
- start_time (time)
- end_time (time)
- is_active (boolean, default: true)
- created_at, updated_at (timestamps)
```

### E-Learning System

#### `assignments` - Assignment Management
```sql
- id (bigint, primary key)
- title (varchar)
- title_arabic (varchar, nullable)
- title_dhivehi (varchar, nullable)
- description (text)
- description_arabic (text, nullable)
- description_dhivehi (text, nullable)
- class_id (bigint, foreign key → classes.id)
- subject_id (bigint, foreign key → subjects.id)
- teacher_id (bigint, foreign key → teachers.id)
- due_date (datetime)
- max_score (decimal)
- instructions (text, nullable)
- attachments (json, nullable) - File attachments
- status (enum: draft, published, closed)
- created_at, updated_at (timestamps)
```

#### `assignment_submissions` - Student Submissions
```sql
- id (bigint, primary key)
- assignment_id (bigint, foreign key → assignments.id)
- student_id (bigint, foreign key → students.id)
- submission_text (text, nullable)
- attachments (json, nullable) - Submitted files
- submitted_at (datetime, nullable)
- status (enum: draft, submitted, late)
- grade_id (bigint, foreign key → grades.id, nullable)
- feedback (text, nullable)
- created_at, updated_at (timestamps)
```

#### `quizzes` - Quiz Management
```sql
- id (bigint, primary key)
- title (varchar)
- title_arabic (varchar, nullable)
- title_dhivehi (varchar, nullable)
- description (text, nullable)
- class_id (bigint, foreign key → classes.id)
- subject_id (bigint, foreign key → subjects.id)
- teacher_id (bigint, foreign key → teachers.id)
- time_limit (integer, nullable) - Minutes
- max_attempts (integer, default: 1)
- shuffle_questions (boolean, default: false)
- show_results_after (enum: immediate, after_close, manual)
- status (enum: draft, published, closed)
- start_date, end_date (datetime, nullable)
- created_at, updated_at (timestamps)
```

#### `quiz_questions` - Quiz Questions
```sql
- id (bigint, primary key)
- quiz_id (bigint, foreign key → quizzes.id)
- question_text (text)
- question_text_arabic (text, nullable)
- question_text_dhivehi (text, nullable)
- question_type (enum: multiple_choice, true_false, short_answer, essay)
- options (json, nullable) - For multiple choice
- correct_answer (text, nullable)
- explanation (text, nullable)
- points (decimal, default: 1.0)
- order (integer) - Question order
- created_at, updated_at (timestamps)
```

#### `quiz_attempts` - Student Quiz Attempts
```sql
- id (bigint, primary key)
- quiz_id (bigint, foreign key → quizzes.id)
- student_id (bigint, foreign key → students.id)
- started_at (datetime)
- completed_at (datetime, nullable)
- time_spent (integer, nullable) - Seconds
- score_obtained (decimal, nullable)
- total_score (decimal, nullable)
- percentage (decimal, nullable)
- status (enum: in_progress, completed, abandoned)
- answers (json, nullable) - Student answers
- created_at, updated_at (timestamps)
```

### Communication System

#### `messages` - Internal Messaging
```sql
- id (bigint, primary key)
- sender_id (bigint, foreign key → users.id)
- recipient_id (bigint, foreign key → users.id)
- subject (varchar)
- message_body (text)
- is_read (boolean, default: false)
- read_at (timestamp, nullable)
- parent_message_id (bigint, foreign key → messages.id, nullable) - For threading
- attachments (json, nullable)
- created_at, updated_at (timestamps)
```

#### `announcements` - School Announcements
```sql
- id (bigint, primary key)
- title (varchar)
- title_arabic (varchar, nullable)
- title_dhivehi (varchar, nullable)
- content (text)
- content_arabic (text, nullable)
- content_dhivehi (text, nullable)
- author_id (bigint, foreign key → users.id)
- target_audience (enum: all, students, teachers, parents, specific_class)
- target_class_id (bigint, foreign key → classes.id, nullable)
- priority (enum: low, normal, high, urgent)
- start_date, end_date (datetime, nullable)
- is_published (boolean, default: false)
- created_at, updated_at (timestamps)
```

#### `notifications` - Push/System Notifications
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key → users.id)
- type (varchar) - Notification type
- title (varchar)
- message (text)
- data (json, nullable) - Additional data
- read_at (timestamp, nullable)
- created_at, updated_at (timestamps)
```

### Administrative Features

#### `teacher_absences` - Teacher Absence Management
```sql
- id (bigint, primary key)
- teacher_id (bigint, foreign key → teachers.id)
- date (date)
- reason (text)
- status (enum: pending, approved, denied)
- approved_by (bigint, foreign key → users.id, nullable)
- created_at, updated_at (timestamps)
```

#### `substitution_requests` - Substitution Requests
```sql
- id (bigint, primary key)
- absence_id (bigint, foreign key → teacher_absences.id)
- requested_teacher_id (bigint, foreign key → teachers.id, nullable)
- status (enum: pending, assigned, completed, cancelled)
- notes (text, nullable)
- created_at, updated_at (timestamps)
```

#### `substitution_assignments` - Substitution Assignments
```sql
- id (bigint, primary key)
- request_id (bigint, foreign key → substitution_requests.id)
- substitute_teacher_id (bigint, foreign key → teachers.id)
- assigned_by (bigint, foreign key → users.id)
- created_at, updated_at (timestamps)
```

#### `absence_notes` - Student Absence Notes (Parent Submission)
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- parent_id (bigint, foreign key → parents.id)
- absence_date (date)
- reason (text)
- status (enum: pending, approved, denied)
- approved_by (bigint, foreign key → users.id, nullable)
- created_at, updated_at (timestamps)
```

### Financial Management

#### `fee_items` - Fee Structure
```sql
- id (bigint, primary key)
- name (varchar)
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- description (text, nullable)
- amount (decimal) - Fee amount in MVR
- fee_type (enum: tuition, transport, uniform, books, other)
- is_active (boolean, default: true)
- created_at, updated_at (timestamps)
```

#### `invoices` - Student Invoices
```sql
- id (bigint, primary key)
- student_id (bigint, foreign key → students.id)
- invoice_number (varchar, unique)
- issue_date (date)
- due_date (date)
- total_amount (decimal)
- paid_amount (decimal, default: 0)
- status (enum: pending, partial, paid, overdue)
- notes (text, nullable)
- created_at, updated_at (timestamps)
```

#### `invoice_lines` - Invoice Line Items
```sql
- id (bigint, primary key)
- invoice_id (bigint, foreign key → invoices.id)
- fee_item_id (bigint, foreign key → fee_items.id)
- description (varchar)
- quantity (decimal, default: 1)
- unit_price (decimal)
- total_price (decimal)
- created_at, updated_at (timestamps)
```

### Public Website & CMS

#### `pages` - Static Website Pages
```sql
- id (bigint, primary key)
- title (varchar)
- title_arabic (varchar, nullable)
- title_dhivehi (varchar, nullable)
- slug (varchar, unique)
- content (longtext)
- content_arabic (longtext, nullable)
- content_dhivehi (longtext, nullable)
- meta_description (text, nullable)
- meta_keywords (text, nullable)
- status (enum: draft, published)
- created_by (bigint, foreign key → users.id)
- created_at, updated_at (timestamps)
```

#### `posts` - News & Blog Posts
```sql
- id (bigint, primary key)
- title (varchar)
- title_arabic (varchar, nullable)
- title_dhivehi (varchar, nullable)
- slug (varchar, unique)
- excerpt (text, nullable)
- excerpt_arabic (text, nullable)
- excerpt_dhivehi (text, nullable)
- content (longtext)
- content_arabic (longtext, nullable)
- content_dhivehi (longtext, nullable)
- featured_image (varchar, nullable)
- status (enum: draft, published)
- published_at (datetime, nullable)
- author_id (bigint, foreign key → users.id)
- created_at, updated_at (timestamps)
```

#### `courses` - Course Information *(current DB)*

```sql
- id (bigint, primary key)
- course_category_id (bigint, foreign key → course_categories.id)  -- legacy; see planned taxonomy below
- name (varchar)
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- description (text)
- description_arabic (text, nullable)
- description_dhivehi (text, nullable)
- duration (varchar, nullable) - e.g., "3 months"
- price (decimal, nullable)
- image (varchar, nullable)
- status (enum: active, inactive)
- created_at, updated_at (timestamps)
```

#### `course_categories` - Course Categories *(current DB — legacy)*

Flat category list on today's schema. **Planned replacement:** hierarchical `course_subjects` + flat `audiences` + `course_levels` (spec §10.4–10.6, ADR-003). Not yet migrated.

```sql
- id (bigint, primary key)
- name (varchar)
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- description (text, nullable)
- icon (varchar, nullable)
- is_active (boolean, default: true)
- created_at, updated_at (timestamps)
```

### Course engine taxonomy *(planned — spec §10, ADR-003; not yet migrated)*

Distinct from Academics school **`subjects`** (class timetables, teacher assignment). Course taxonomy tables live in the Courses domain when Phase 1A ships.

#### `course_subjects` - Hierarchical browse axis

```sql
- id (bigint, primary key)
- parent_id (bigint, nullable, foreign key → course_subjects.id)  -- null = root
- name_en (varchar)
- name_dv (varchar, nullable)
- name_ar (varchar, nullable)
- slug (varchar, unique)
- sort_order (integer, default: 0)
- active (boolean, default: true)
- created_at, updated_at (timestamps)
```

Seed examples (admin-editable): Quran → Hifz, Tajweed, …; Arabic → Nahw, Sarf, …; Islamic Studies → Fiqh, Aqeedah, …; Dhivehi; English.

#### `audiences` - Who an offering is for

```sql
- id (bigint, primary key)
- name_en (varchar)
- name_dv (varchar, nullable)
- name_ar (varchar, nullable)
- slug (varchar, unique)
- sort_order (integer, default: 0)
- active (boolean, default: true)
- created_at, updated_at (timestamps)
```

Seed examples (admin-editable): Kids, School children, Adults, All.

#### `course_levels` - Proficiency / stage

```sql
- id (bigint, primary key)
- name_en (varchar)
- name_dv (varchar, nullable)
- name_ar (varchar, nullable)
- slug (varchar, unique)
- sort_order (integer, default: 0)
- active (boolean, default: true)
- created_at, updated_at (timestamps)
```

Seed examples (admin-editable): Foundation, Beginner, Intermediate, Advanced, Level 1, Level 2, A1, A2, B1.

#### Planned FK columns (course engine split, spec §11)

**`courses` (template):**

```sql
- subject_id (bigint, foreign key → course_subjects.id)  -- leaf preferred; non-leaf if admin allows
```

**`course_offerings` (batch / delivery instance):**

```sql
- course_id (bigint, foreign key → courses.id)
- audience_id (bigint, foreign key → audiences.id)
- level_id (bigint, foreign key → course_levels.id)
-- … plus delivery mode, schedule, seats, etc. (spec §11.3)
```

Same course template + different `audience_id` / `level_id` per offering — no duplicate course rows.

### Media & File Management

#### `media_galleries` - Photo Galleries
```sql
- id (bigint, primary key)
- name (varchar)
- name_arabic (varchar, nullable)
- name_dhivehi (varchar, nullable)
- description (text, nullable)
- is_active (boolean, default: true)
- created_at, updated_at (timestamps)
```

#### `media_items` - Gallery Items
```sql
- id (bigint, primary key)
- gallery_id (bigint, foreign key → media_galleries.id)
- title (varchar, nullable)
- file_path (varchar)
- file_type (enum: image, video, document)
- file_size (bigint, nullable) - Bytes
- mime_type (varchar, nullable)
- alt_text (varchar, nullable)
- description (text, nullable)
- order (integer, default: 0)
- created_at, updated_at (timestamps)
```

### Additional Tables

#### `contact_messages` - Website Contact Form
#### `testimonials` - Student/Parent Testimonials
#### `faqs` - Frequently Asked Questions
#### `hero_banners` - Website Hero Banners
#### `devices` - Mobile/Push Notification Devices
#### `course_plans` - Lesson Planning
#### `plan_topics` - Course Plan Topics
#### `lesson_logs` - Lesson Activity Logs

---

## 🔗 Key Relationships

### User System
```
users ←→ students (1:1)
users ←→ parents (1:1)
users ←→ teachers (1:1)
users ←→ sessions (1:many)
users ←→ messages (1:many as sender/recipient)
```

### Educational Hierarchy
```
schools ←→ classes (1:many)
classes ←→ students (1:many)
classes ←→ subjects (many:many via class_subject)
teachers ←→ subjects (many:many via teacher_subject)
```

### Islamic Education
```
students ←→ quran_progress (1:many)
teachers ←→ quran_progress (1:many)
quran_progress ←→ recitation_practices (1:many)
recitation_practices ←→ tajweed_feedback (1:many)
```

### Academic Management
```
students ←→ attendances (1:many)
students ←→ grades (1:many)
classes ←→ timetables (1:many)
assignments ←→ assignment_submissions (1:many)
quizzes ←→ quiz_attempts (1:many)
```

---

## 🚀 Database Optimization

### Indexes
- Primary keys on all tables
- Foreign key indexes
- Unique indexes on email, phone, student_id
- Composite indexes on frequently queried columns

### Performance Considerations
- JSON columns for flexible data storage
- Proper foreign key constraints
- Soft deletes where appropriate
- Timestamp indexing for audit trails

---

**Database Version**: MySQL 8.0+  
**Total Tables**: 66+  
**Last Updated**: October 16, 2025  
**Schema Version**: 2.0.0
