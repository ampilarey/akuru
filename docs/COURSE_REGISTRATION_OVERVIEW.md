# Course Registration & Admissions - Overview

## How Course Registration Works

### 1. **Public Course Browsing**
- **URL:** `/en/courses` (or `/ar/courses`, `/dv/courses`)
- Users can browse courses filtered by category, status (open/upcoming), language, level
- Each course has a detail page: `/en/courses/{slug}` (e.g. `/en/courses/arabic-language-beginners`)
- Course show page includes: description, schedule, fee, Apply button

### 2. **Applying for a Course (Admission Form)**
- **URL:** `/en/admissions` (or `/ar/admissions`, `/dv/admissions`)
- **Pre-selection:** From course detail page, "Apply for This Course" links to `/en/admissions?course=5` (by ID) to pre-select the course in the form
- Form fields:
  - Course of Interest (dropdown - open & upcoming courses)
  - Full Name, Phone, Email
  - Guardian Name (optional)
  - Message (optional)
  - Source (web, social, whatsapp, other)
- On submit: Creates `AdmissionApplication` record, notifies admins
- Redirects to thanks page: `/en/admissions/thanks`

### 3. **Course Data**
- Courses stored in `courses` table with `course_category_id`, `title`, `slug`, `body`, `fee`, `status` (open/upcoming/closed), `seats`, etc.
- Categories in `course_categories` table
- Only courses with status `open` or `upcoming` appear in the admission form dropdown
- Sample courses seeded via `CourseSeeder` and `CourseCategorySeeder`

### 4. **Admin Management**
- Admins manage courses via Admin CMS: `/admin/public-site/courses`
- Admins view admission applications (location varies by implementation)
- `AdmissionApplication` model links to `Course` via `course_id`

### 5. **Key Files**
- **Controller:** `app/Http/Controllers/PublicSite/AdmissionController.php`
- **Controller:** `app/Http/Controllers/PublicSite/CourseController.php`
- **Views:** `resources/views/public/admissions/create.blade.php`, `thanks.blade.php`
- **Views:** `resources/views/public/courses/index.blade.php`, `show.blade.php`
- **Model:** `app/Models/AdmissionApplication.php`, `app/Models/Course.php`
- **Routes:** `routes/web_public.php` (admissions, courses)

---

## Summary
Course registration = Public admission form where users select a course and submit their details. No payment or account required for the initial application. Admin processes applications and can approve/enroll students.
