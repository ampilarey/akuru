# User Roles & Permissions Guide

Complete guide to user roles and access levels in Akuru Institute LMS.

---

## 👥 7 USER ROLES

### **1. 🔴 SUPER ADMIN** (System Owner)

**Who:** System owner, IT administrator, you  
**Access Level:** FULL SYSTEM ACCESS

**Can Do:**
- ✅ Everything all other roles can do
- ✅ Manage system settings
- ✅ Create/delete/modify all users
- ✅ Assign/remove any role
- ✅ Access server settings
- ✅ Manage integrations (SMS Gateway API keys)
- ✅ Database management
- ✅ View system logs
- ✅ Backup/restore system
- ✅ Manage permissions
- ✅ Override any restriction

**Cannot Be Deleted:** Protected account  
**Typical Users:** 1 (you)

---

### **2. 🟠 ADMIN** (School Administrator)

**Who:** School administrative staff, office manager  
**Access Level:** HIGH (School Operations)

**Can Do:**
- ✅ Manage students & teachers (add, edit, archive)
- ✅ **Manage fees & invoices**
- ✅ **Process payments**
- ✅ **Generate financial reports**
- ✅ Manage admissions applications
- ✅ Manage website content (CMS)
- ✅ Send announcements
- ✅ View all reports
- ✅ Manage class assignments
- ✅ Contact management
- ✅ Send bulk SMS
- ✅ View usage statistics

**Cannot Do:**
- ❌ Change system settings (Super Admin only)
- ❌ Manage integrations/API keys
- ❌ Access server configurations
- ❌ Delete Super Admin

**Typical Users:** 2-5 (office staff, registrar)

---

### **3. 🟡 HEADMASTER** (Academic Leadership)

**Who:** Principal, headmaster, academic director  
**Access Level:** HIGH (Academic Oversight)

**Can Do:**
- ✅ View all students & teachers
- ✅ Approve substitutions
- ✅ View all attendance & grades
- ✅ Access all academic reports
- ✅ Make school-wide announcements
- ✅ Manage timetables
- ✅ Approve teacher requests
- ✅ View financial summaries
- ✅ Monitor Quran progress
- ✅ Assign teachers to classes
- ✅ Send SMS to parents

**Cannot Do:**
- ❌ Process payments (Admin function)
- ❌ Edit fee structures
- ❌ Manage website CMS
- ❌ System settings

**Typical Users:** 1-2 (headmaster, assistant headmaster)

---

### **4. 🟢 SUPERVISOR** (Academic Monitor)

**Who:** Academic coordinator, department head  
**Access Level:** MEDIUM-HIGH (Monitoring)

**Can Do:**
- ✅ View all students (read-only)
- ✅ View all teachers (read-only)
- ✅ Monitor attendance
- ✅ Monitor grades
- ✅ View assignment submissions
- ✅ Access academic reports
- ✅ Manage substitutions
- ✅ View timetables
- ✅ Post announcements
- ✅ Send SMS notifications

**Cannot Do:**
- ❌ Add/edit students
- ❌ Enter grades (Teacher function)
- ❌ Mark attendance (Teacher function)
- ❌ Financial operations
- ❌ Website management

**Typical Users:** 2-4 (coordinators, department heads)

---

### **5. 🔵 TEACHER**

**Who:** Teaching staff, instructors  
**Access Level:** MEDIUM (Teaching Functions)

**Can Do:**
- ✅ **Mark attendance** for own classes
- ✅ **Enter grades** for own students
- ✅ Create assignments
- ✅ Create quizzes
- ✅ Grade submissions
- ✅ Track Quran progress for students
- ✅ Provide Tajweed feedback
- ✅ Post announcements (own classes)
- ✅ View own timetable
- ✅ Request substitutions
- ✅ Message students & parents
- ✅ View own class reports
- ✅ Access lesson plans

**Cannot Do:**
- ❌ View other teachers' data
- ❌ Edit student records
- ❌ Financial operations
- ❌ Approve substitutions
- ❌ System administration

**Typical Users:** 10-50 (all teaching staff)

---

### **6. 🔷 STUDENT**

**Who:** Enrolled students  
**Access Level:** LOW (Own Data Only)

**Can Do:**
- ✅ View own grades
- ✅ View own attendance
- ✅ Submit assignments
- ✅ Take quizzes
- ✅ View own timetable
- ✅ Access e-learning content
- ✅ View announcements
- ✅ Track own Quran progress
- ✅ View own fee information
- ✅ Message teachers
- ✅ View own dashboard

**Cannot Do:**
- ❌ View other students' data
- ❌ Edit grades
- ❌ Mark attendance
- ❌ Create content
- ❌ Access admin features

**Typical Users:** 50-500+ (all students)

---

### **7. 🟣 PARENT** (Guardian)

**Who:** Parents, guardians, family members  
**Access Level:** LOW (Children's Data Only)

**Can Do:**
- ✅ **View all children's grades** (linked to account)
- ✅ **View all children's attendance**
- ✅ **Submit absence notes**
- ✅ View assignments & homework
- ✅ View quiz results
- ✅ View Quran progress
- ✅ View timetables
- ✅ View announcements
- ✅ **View & pay fees**
- ✅ Download invoices
- ✅ **Receive SMS notifications** (attendance, grades)
- ✅ **Receive email notifications**
- ✅ Message teachers
- ✅ View school events
- ✅ Update own profile

**Cannot Do:**
- ❌ Edit student information
- ❌ View other students
- ❌ Enter grades
- ❌ Mark attendance
- ❌ Create content
- ❌ Any administrative function

**Special Features:**
- Can have **multiple children** linked
- Dashboard shows **combined view** of all children
- Can switch between children
- Receives **separate notifications** for each child

**Typical Users:** 50-500+ (parents of enrolled students)

---

## 🔐 ROLE HIERARCHY & PERMISSIONS

```
Super Admin (System Level)
    │
    ├── Admin (School Operations)
    │     └── Financial, Admissions, CMS
    │
    ├── Headmaster (Academic Leadership)
    │     └── Academic Oversight, Approvals
    │
    ├── Supervisor (Academic Monitoring)
    │     └── Monitoring, Reports
    │
    ├── Teacher (Teaching & Assessment)
    │     └── Teaching, Grading, Attendance
    │
    ├── Student (Learning)
    │     └── View Own Data, Submit Work
    │
    └── Parent (Guardian)
          └── View Children's Data, Submit Notes
```

---

## 📊 PERMISSION COMPARISON TABLE

| Feature | Super Admin | Admin | Headmaster | Supervisor | Teacher | Student | Parent |
|---------|-------------|-------|------------|------------|---------|---------|--------|
| **System Settings** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **API Keys** | ✅ Manage | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **User Management** | ✅ All | ✅ CRUD | ✅ View | ✅ View | ❌ | ❌ | ❌ |
| **Fees & Payments** | ✅ All | ✅ Manage | ✅ View | ❌ | ❌ | ✅ Own | ✅ Own |
| **Financial Reports** | ✅ All | ✅ Full | ✅ Summary | ❌ | ❌ | ❌ | ❌ |
| **Admissions** | ✅ All | ✅ Manage | ✅ View | ❌ | ❌ | ❌ | ❌ |
| **Website CMS** | ✅ All | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Timetables** | ✅ All | ✅ Manage | ✅ Manage | ✅ View | ✅ Own | ✅ Own | ✅ Children |
| **Attendance** | ✅ All | ✅ View | ✅ View | ✅ View | ✅ Mark | ✅ Own | ✅ Children |
| **Grades** | ✅ All | ✅ View | ✅ View | ✅ View | ✅ Enter | ✅ Own | ✅ Children |
| **Assignments** | ✅ All | ✅ View | ✅ View | ✅ View | ✅ Create | ✅ Submit | ✅ View |
| **Quran Progress** | ✅ All | ✅ View | ✅ View | ✅ View | ✅ Update | ✅ Own | ✅ Children |
| **Announcements** | ✅ All | ✅ Create | ✅ Create | ✅ Create | ✅ Class | ✅ View | ✅ View |
| **Substitutions** | ✅ All | ✅ Manage | ✅ Approve | ✅ Manage | ✅ Request | ❌ | ❌ |
| **SMS Sending** | ✅ All | ✅ Bulk | ✅ Bulk | ✅ Limited | ✅ Class | ❌ | ❌ |
| **Reports** | ✅ All | ✅ All | ✅ Academic | ✅ Academic | ✅ Own | ✅ Own | ✅ Children |

---

## 🎯 ROLE DESCRIPTIONS UPDATED

### **Super Admin vs Admin:**

**SUPER ADMIN** (You - System Owner):
- 🔧 System configuration
- 🔑 API keys & integrations  
- 🗄️ Database access
- 🔐 Security settings
- 👤 Can create/delete Admins
- 📊 System-level analytics
- 🚀 Deployment access

**ADMIN** (School Office Staff):
- 💰 Fees & payments
- 📝 Admissions processing
- 👥 Student/teacher registration
- 🌐 Website content updates
- 📧 Bulk communications
- 📊 Financial reports
- 🏢 Day-to-day operations

**The Difference:**
- **Super Admin** = Technical/System level
- **Admin** = Business/Operations level

---

Should I implement this role separation now? This will:
1. Create Super Admin role
2. Update permissions
3. Update navigation based on roles
4. Update documentation

Type "yes" to proceed or tell me if you want any changes first!
