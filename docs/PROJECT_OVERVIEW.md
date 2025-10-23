# Akuru Institute LMS - Project Overview

## Vision
Build a comprehensive, multilingual Learning Management System (LMS) specifically designed for Islamic education institutions, with features comparable to EduPage but tailored for Quran memorization, Arabic language learning, and Islamic studies.

## Target Platform
- **Primary**: Web-based (responsive design)
- **Languages**: English, Arabic (RTL), Dhivehi (Thaana)
- **Domain**: akuru.edu.mv
- **Hosting**: cPanel shared hosting

## Business Context
- **Client**: Akuru Institute (Islamic Education)
- **Currency**: MVR (Maldivian Rufiyaa)
- **Location**: Maldives
- **Focus**: Islamic education, Quran memorization, Arabic studies

## Core Value Propositions

### 1. Islamic Education Specialization
- Quran memorization tracking (Surah & Ayat level)
- Recitation practice with audio recording
- Tajweed feedback system
- Islamic calendar integration (Hijri dates)
- Arabic typography support (Amiri font)

### 2. Comprehensive LMS Features
- Student & teacher management (7 user roles)
- Attendance tracking & grade management
- Assignment & quiz system
- E-learning content management
- Timetable & scheduling system

### 3. Multi-Language Support
- English (primary interface)
- Arabic (full RTL support)
- Dhivehi (Thaana script support)
- URL-based language switching (/en/, /ar/, /dv/)
- Localized user interfaces

### 4. Advanced Communication
- SMS integration for notifications
- OTP-based authentication
- Parent-student-teacher messaging
- Announcement system
- Push notifications (Firebase)

## Key Differentiators

### vs. General LMS Platforms
- ✅ **Islamic-focused**: Quran progress tracking, Islamic calendar
- ✅ **Multi-language**: Native Arabic & Dhivehi support
- ✅ **Custom OTP**: SMS-based authentication
- ✅ **Regional**: Designed for Maldives educational system

### vs. Commercial Solutions (EduPage)
- ✅ **Cost-effective**: One-time development vs subscription
- ✅ **Customizable**: Tailored to Islamic education needs
- ✅ **Self-hosted**: Full data ownership and control
- ✅ **Local integration**: SMS gateway, local payment systems

## User Roles & Permissions

### 1. Super Admin (System Owner)
- Full system access
- User & role management
- System configuration
- API & integration management

### 2. Admin (School Administrator)
- Student & teacher management
- Fees & invoicing system
- Admissions management
- Website CMS management

### 3. Headmaster (Academic Leadership)
- Academic oversight
- Substitution approvals
- School-wide announcements
- Performance monitoring

### 4. Supervisor (Academic Monitor)
- Academic monitoring
- Report access
- Substitution management
- Quality assurance

### 5. Teacher
- Class management
- Attendance marking
- Grade entry
- Assignment creation
- Quran progress tracking

### 6. Student
- Personal dashboard
- Grade viewing
- Assignment submission
- Quiz taking
- Quran progress tracking

### 7. Parent
- Child progress monitoring
- Fee payments
- Absence note submission
- Communication with teachers

## Technical Highlights

### Backend (Laravel 12)
- RESTful API with JSON responses
- Sanctum authentication
- Role-based access control (Spatie)
- Queue-based async operations (SMS, emails)
- Comprehensive audit logging
- Multi-language support (mcamara/laravel-localization)

### Frontend (Blade + TailwindCSS)
- Responsive design (mobile-first)
- Alpine.js for interactivity
- Multi-language UI switching
- RTL/LTR automatic switching
- Progressive Web App features

### Database Architecture
- MySQL 8+ with proper indexing
- 66+ database tables
- Foreign key constraints
- Soft deletes for data integrity
- JSON columns for flexible data

### Integrations
- SMS Gateway (local provider)
- Firebase Cloud Messaging (push notifications)
- Laravel Socialite (Google/Microsoft login)
- Image processing (Intervention Image)

## Feature Set Overview

### Core LMS Features (200+ total)

#### Authentication & Security (15+ features)
- Email/password login
- OTP-based authentication via SMS
- Password reset via SMS
- Role-based access control
- Session management & security

#### Student Management (20+ features)
- Student registration & profiles
- Class assignments
- Parent-student linking
- Academic progress tracking
- Fee management

#### Teacher Management (12+ features)
- Teacher profiles & assignments
- Subject-class linking
- Substitution management
- Performance tracking
- Schedule management

#### Quran & Islamic Education (15+ features)
- Surah-by-surah progress tracking
- Ayat-level memorization
- Recitation practice recordings
- Tajweed feedback system
- Islamic calendar integration

#### Attendance & Grades (22+ features)
- Daily attendance marking
- Grade entry & management
- Report generation
- Parent notifications
- Academic analytics

#### E-Learning System (15+ features)
- Quiz creation & management
- Assignment distribution
- File uploads & submissions
- Grading & feedback
- Learning progress tracking

#### Communication (15+ features)
- Internal messaging system
- SMS notifications
- Email notifications
- Push notifications
- Announcement broadcasting

#### Public Website (35+ features)
- Course catalog & information
- News & blog system
- Event management
- Photo galleries
- Online admission applications

## Current Status

### Implemented Features
- ✅ Complete user authentication system
- ✅ 7-role permission system
- ✅ Multi-language support (EN/AR/DV)
- ✅ SMS integration & OTP authentication
- ✅ Quran progress tracking system
- ✅ Basic LMS functionality (attendance, grades)
- ✅ Public website with CMS

### In Development
- 🔄 Enhanced communication features
- 🔄 Advanced reporting system
- 🔄 Mobile app integration
- 🔄 Payment gateway integration

### Planned Features
- 📋 Advanced analytics dashboard
- 📋 Video lesson integration
- 📋 Advanced timetable management
- 📋 Library management system

## Success Metrics

### Technical
- [ ] 99.9% uptime
- [ ] <2s page load times
- [ ] Mobile-responsive across all devices
- [ ] 100% RTL support for Arabic
- [ ] SMS delivery success rate >95%

### Educational
- [ ] Improved student engagement
- [ ] Better parent-school communication
- [ ] Streamlined administrative processes
- [ ] Enhanced Quran memorization tracking
- [ ] Reduced manual paperwork

## Risk Assessment

### High Risk
⚠️ **SMS Integration Reliability** - Dependent on local SMS provider
   - Mitigation: Fallback to email notifications, robust error handling

⚠️ **Multi-language Complexity** - RTL/LTR switching and translation maintenance
   - Mitigation: Comprehensive testing, native speaker review

### Medium Risk
⚠️ **Scalability** - Growing student population and data load
   - Mitigation: Database optimization, caching strategies

⚠️ **User Adoption** - Staff training and system adoption
   - Mitigation: Training programs, user-friendly interface

### Low Risk
✅ **Technology Stack** - Laravel is mature and well-supported
✅ **Security** - Standard authentication and authorization patterns
✅ **Hosting** - cPanel hosting is well-established

## Development Principles

1. **Islamic Education First**: Every feature considers Islamic educational needs
2. **Multi-language Native**: Not an afterthought, but core to the design
3. **User-Centric**: Designed for actual school workflows
4. **Security Focused**: Protecting student and family data
5. **Mobile-Responsive**: Works on all devices and screen sizes

## Deployment Architecture

```
┌─────────────────────────────────────────────────────┐
│                    Internet                         │
└────────────┬────────────────────────────────────────┘
             │
┌────────────▼──────────────┐
│   cPanel Hosting          │
│   akuru.edu.mv            │
└────────────┬──────────────┘
             │
┌────────────▼──────────────┐
│   Laravel Application     │
│   - PHP 8.4+             │
│   - MySQL Database        │
│   - File Storage          │
└────────────┬──────────────┘
             │
┌────────────▼──────────────┐
│   External Integrations   │
│   - SMS Gateway          │
│   - Firebase FCM         │
│   - Payment Gateway      │
└───────────────────────────┘
```

## Next Steps

1. ✅ **Documentation** (Current)
   - [x] Project overview and architecture
   - [x] Feature documentation
   - [x] User roles and permissions

2. 🔨 **Development** (Ongoing)
   - [ ] Enhanced communication features
   - [ ] Advanced reporting system
   - [ ] Mobile app development
   - [ ] Performance optimization

3. 🚀 **Deployment** (Planned)
   - [ ] Production environment setup
   - [ ] SSL configuration
   - [ ] Performance testing
   - [ ] Staff training

---

**Project Start Date**: September 2025  
**Current Version**: 2.0.0  
**Total Features**: 200+  
**Database Tables**: 66+  

**Team**: Full-Stack Development  
**Stakeholder**: Akuru Institute Management  

