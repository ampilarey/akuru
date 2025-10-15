# EduPage Feature Comparison for Akuru LMS

**Date Created:** October 15, 2025  
**Reference:** akuru.edupage.org  
**Project:** Akuru Institute LMS

---

## Overview

This document tracks features from EduPage that can be replicated or enhanced in the Akuru LMS system. The goal is to achieve feature parity with EduPage while maintaining the unique Islamic education focus of Akuru Institute.

---

## ✅ Features Already Implemented in Akuru LMS

### 1. **Electronic Class Register**
- ✅ Attendance tracking system
- ✅ Grade recording and management
- ✅ Student performance tracking
- **Location:** `app/Models/Attendance.php`, `app/Models/Grade.php`

### 2. **Timetable System**
- ✅ Class scheduling
- ✅ Period management
- ✅ Teacher-subject-class assignments
- ✅ CSV import functionality
- **Location:** `app/Models/Timetable.php`, `app/Console/Commands/TimetableImport.php`

### 3. **Homework & Assignments**
- ✅ Assignment creation and distribution
- ✅ Student submissions
- ✅ Grading system
- ✅ Due date tracking
- **Location:** `app/Models/Assignment.php`, `app/Models/AssignmentSubmission.php`

### 4. **E-Learning & Tests**
- ✅ Quiz creation with multiple question types (MCQ, True/False, Short Answer)
- ✅ Quiz attempts and scoring
- ✅ Time limits
- ✅ Question banks
- **Location:** `app/Models/Quiz.php`, `app/Models/QuizQuestion.php`, `app/Models/QuizAttempt.php`

### 5. **Attendance Tracking**
- ✅ Daily attendance marking
- ✅ Period-based attendance
- ✅ Attendance reports
- ✅ Late arrival tracking
- **Location:** `app/Models/Attendance.php`

### 6. **Mobile App Support**
- ✅ Firebase Cloud Messaging (FCM) integration
- ✅ Push notifications
- ✅ Device registration
- **Location:** `app/Models/Device.php`, `app/Services/Notify.php`

### 7. **Teacher Substitutions**
- ✅ Teacher absence management
- ✅ Substitution request system
- ✅ Substitute teacher assignment
- ✅ Automatic request generation
- **Location:** `app/Models/TeacherAbsence.php`, `app/Models/SubstitutionRequest.php`

### 8. **Public Website with CMS**
- ✅ Multilingual support (EN/AR/DV with RTL)
- ✅ Course catalog
- ✅ News & announcements
- ✅ Events calendar with Hijri dates
- ✅ Photo gallery
- ✅ Contact forms
- ✅ Admissions portal
- **Location:** `app/Http/Controllers/PublicSite/`, `resources/views/public/`

### 9. **News & Announcements**
- ✅ Announcement creation and distribution
- ✅ Audience targeting (roles)
- ✅ Email notifications
- ✅ Push notifications
- **Location:** `app/Models/Announcement.php`, `app/Models/Post.php`

### 10. **Parent Portal Features**
- ✅ Absence note submission by parents
- ✅ Approval workflow
- ✅ Student progress viewing
- **Location:** `app/Models/AbsenceNote.php`

### 11. **Qur'an-Specific Features** (Unique to Akuru)
- ✅ Recitation practice tracking
- ✅ Tajweed feedback system
- ✅ Surah progress monitoring
- ✅ Audio recording submissions
- **Location:** `app/Models/RecitationPractice.php`, `app/Models/QuranProgress.php`

### 12. **Lesson Planning**
- ✅ Course plans
- ✅ Plan topics
- ✅ Lesson logs
- ✅ Homework tracking per lesson
- **Location:** `app/Models/CoursePlan.php`, `app/Models/LessonLog.php`

### 13. **Fees & Invoicing** (Draft)
- ✅ Fee items management
- ✅ Invoice generation
- ✅ Invoice lines
- ✅ Payment status tracking
- **Location:** `app/Models/Invoice.php`, `app/Models/FeeItem.php`

### 14. **Admissions CRM**
- ✅ Application forms
- ✅ Application status tracking
- ✅ Timeline tracking
- ✅ Staff assignment
- ✅ Tag system
- **Location:** `app/Models/AdmissionApplication.php`

---

## 🎯 EduPage Features to Consider Adding

### 1. **Enhanced Communication Features**
- [ ] Internal messaging system improvements
- [ ] Group messaging
- [ ] Message threading
- [ ] Read receipts
- [ ] File attachments in messages (partially done)
- [ ] Email integration for external communication
- [ ] SMS integration (mentioned - `sms_local` database exists)

**Priority:** High  
**Complexity:** Medium  
**Estimated Time:** 2-3 weeks

### 2. **Advanced Timetable Features**
- [ ] aSc Timetables integration
- [ ] Automatic conflict detection
- [ ] Room booking system
- [ ] Resource allocation (labs, equipment)
- [ ] Teacher availability management
- [ ] Substitute teacher suggestions based on availability

**Priority:** Medium  
**Complexity:** High  
**Estimated Time:** 3-4 weeks

### 3. **E-Learning Enhancements**
- [ ] Interactive multimedia lessons
- [ ] Video embedding
- [ ] Rich text editor for lesson content
- [ ] Student collaboration tools
- [ ] Discussion forums per course
- [ ] Live class integration (Zoom/Google Meet)
- [ ] Screen recording integration

**Priority:** High  
**Complexity:** High  
**Estimated Time:** 4-5 weeks

### 4. **Gradebook Enhancements**
- [ ] Weighted grade calculations
- [ ] Grade curves and statistics
- [ ] Multiple grading periods
- [ ] Grade categories (exams, homework, participation)
- [ ] Export to Excel/PDF
- [ ] Transcript generation
- [ ] Honor roll / Dean's list automation

**Priority:** High  
**Complexity:** Medium  
**Estimated Time:** 2-3 weeks

### 5. **Parent Portal Enhancements**
- [ ] Meeting scheduling with teachers
- [ ] Enhanced progress reports
- [ ] Behavior tracking
- [ ] Monthly progress summaries
- [ ] Report card download
- [ ] Payment history and receipts
- [ ] Document signing (permission slips)

**Priority:** High  
**Complexity:** Medium  
**Estimated Time:** 2-3 weeks

### 6. **Calendar & Events**
- [ ] Unified calendar view
- [ ] Assignment calendar
- [ ] Event calendar
- [ ] Exam schedule
- [ ] Islamic calendar integration (Hijri - partially done)
- [ ] Calendar sync (Google Calendar, iCal)
- [ ] Reminders and notifications

**Priority:** High  
**Complexity:** Medium  
**Estimated Time:** 2 weeks

### 7. **Dashboard Improvements**
- [ ] Customizable widgets
- [ ] Upcoming events widget
- [ ] Grade summary charts
- [ ] Attendance overview
- [ ] Quick actions panel
- [ ] Recent activity feed
- [ ] Role-specific dashboards

**Priority:** Medium  
**Complexity:** Medium  
**Estimated Time:** 2 weeks

### 8. **Reports & Analytics**
- [ ] Student performance reports
- [ ] Attendance reports (partially done)
- [ ] Class performance comparison
- [ ] Teacher workload reports
- [ ] Custom report builder
- [ ] Data export to Excel
- [ ] Visual analytics dashboards

**Priority:** Medium  
**Complexity:** High  
**Estimated Time:** 3-4 weeks

### 9. **Library Management**
- [ ] Book catalog
- [ ] Check-in/check-out system
- [ ] Reservation system
- [ ] Fine management
- [ ] Digital library integration

**Priority:** Low  
**Complexity:** Medium  
**Estimated Time:** 2 weeks

### 10. **Behavior & Discipline**
- [ ] Incident reporting
- [ ] Merit/demerit system
- [ ] Behavior tracking
- [ ] Counselor notes
- [ ] Parent notifications for incidents

**Priority:** Medium  
**Complexity:** Low  
**Estimated Time:** 1-2 weeks

---

## 📊 Feature Implementation Priority Matrix

### High Priority (Next 3 months)
1. Enhanced Communication (Messaging improvements + SMS)
2. Gradebook Enhancements
3. Calendar & Events System
4. E-Learning Enhancements
5. Parent Portal Improvements

### Medium Priority (3-6 months)
1. Advanced Timetable Features
2. Dashboard Improvements
3. Reports & Analytics
4. Behavior & Discipline Tracking

### Low Priority (6+ months)
1. Library Management
2. Additional third-party integrations

---

## 🔧 Technical Notes

### Current Tech Stack
- **Framework:** Laravel 12.34.0 (Latest)
- **PHP:** 8.4.12
- **Database:** MySQL 9.4.0
- **Frontend:** Tailwind CSS 3.4.18, Alpine.js 3.15.0, Vite 7.1.10
- **Localization:** mcamara/laravel-localization (EN/AR/DV)
- **Authentication:** Laravel Breeze
- **Permissions:** spatie/laravel-permission
- **Push Notifications:** Firebase Cloud Messaging
- **Hijri Dates:** alkoumi/laravel-hijri-date

### Database Stats
- **Total Tables:** 66 tables
- **Database Size:** 6.55 MB
- **Database Name:** `akuru_institute`

### SMS Integration Available
- Separate database `sms_local` exists with SMS functionality
- Ready for integration into Akuru LMS

---

## 📝 Development Approach

When implementing EduPage features:

1. **User Feedback First:** 
   - User browses EduPage (akuru.edupage.org)
   - User provides screenshots or descriptions
   - Identify specific workflows and UI patterns

2. **Feature Analysis:**
   - Break down the feature into components
   - Identify database schema needs
   - Map to existing Akuru models if applicable

3. **Implementation:**
   - Create migrations if needed
   - Build models and relationships
   - Develop controllers with proper authorization
   - Create views matching Akuru's design system
   - Add routes with localization
   - Write tests

4. **Testing & Refinement:**
   - Test with demo data
   - Get user feedback
   - Iterate and improve

---

## 🌟 Unique Akuru Advantages

Features that Akuru has that EduPage doesn't:

1. **Islamic Education Focus:**
   - Qur'an recitation tracking
   - Tajweed feedback
   - Surah progress monitoring
   - Hijri calendar integration

2. **Multilingual Islamic Context:**
   - Arabic, English, Dhivehi support
   - RTL support for Arabic
   - Islamic terminology localization

3. **Maldivian Context:**
   - Local timezone (Indian/Maldives)
   - Dhivehi language support
   - Local payment integration ready (BML)

---

## 📞 Contact & Resources

- **EduPage Reference:** https://akuru.edupage.org (User has login credentials)
- **Project Repository:** https://github.com/ampilarey/akuru
- **Local Development:** http://localhost:8000

---

## 🔄 Version History

- **v1.0** - October 15, 2025 - Initial feature comparison document created
- Future versions will track implemented features from this list

---

## Next Steps

1. User to provide specific EduPage features they want most urgently
2. Provide screenshots or detailed descriptions
3. Prioritize based on user needs
4. Begin implementation in order of priority

**Remember:** This is a living document. Update as features are implemented or priorities change.

