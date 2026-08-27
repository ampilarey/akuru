<?php

use App\Domains\Academics\Http\Controllers\AbsenceNoteReviewController;
use App\Domains\Academics\Http\Controllers\AcademicYearController;
use App\Domains\Academics\Http\Controllers\AnnouncementController;
use App\Domains\Academics\Http\Controllers\AttendanceReportController;
use App\Domains\Academics\Http\Controllers\BehaviorRecordController;
use App\Domains\Academics\Http\Controllers\CalendarDayController;
use App\Domains\Academics\Http\Controllers\ClassDirectoryController;
use App\Domains\Academics\Http\Controllers\CoursePlanController;
use App\Domains\Academics\Http\Controllers\DailyAttendanceController;
use App\Domains\Academics\Http\Controllers\MeetingSlotController;
use App\Domains\Academics\Http\Controllers\PeriodDirectoryController;
use App\Domains\Academics\Http\Controllers\PromotionController;
use App\Domains\Academics\Http\Controllers\RegisterReportController;
use App\Domains\Academics\Http\Controllers\RoomBookingController;
use App\Domains\Academics\Http\Controllers\RoomDirectoryController;
use App\Domains\Academics\Http\Controllers\SchoolRequestController;
use App\Domains\Academics\Http\Controllers\SubstitutionRequestController;
use App\Domains\Academics\Http\Controllers\TeacherRegisterController;
use App\Domains\Academics\Http\Controllers\TimetableBuilderController;
use App\Domains\Academics\Legacy\Http\Controllers\ELearningController;
use App\Domains\Admissions\Http\Controllers\AdminEnrollmentController;
use App\Domains\Courses\Components\Arabic\Http\Controllers\CatalogArabicReferenceController;
use App\Domains\Courses\Components\Arabic\Http\Controllers\CatalogArabicReportController;
use App\Domains\Courses\Components\Arabic\Http\Controllers\LearnArabicReportController;
use App\Domains\Courses\Components\Quran\Http\Controllers\CatalogQuranReferenceController;
use App\Domains\Courses\Http\Controllers\AudienceController;
use App\Domains\Courses\Http\Controllers\CatalogActivityController;
use App\Domains\Courses\Http\Controllers\CatalogAssessmentController;
use App\Domains\Courses\Http\Controllers\CatalogMediaController;
use App\Domains\Courses\Http\Controllers\CatalogQuestionController;
use App\Domains\Courses\Http\Controllers\CatalogReviewController;
use App\Domains\Courses\Http\Controllers\CourseCertificateController;
use App\Domains\Courses\Http\Controllers\CourseCompletionReportController;
use App\Domains\Courses\Http\Controllers\CourseLevelController;
use App\Domains\Courses\Http\Controllers\CourseOutlineController;
use App\Domains\Courses\Http\Controllers\CourseSubjectController;
use App\Domains\Courses\Http\Controllers\EngineCourseController;
use App\Domains\Courses\Http\Controllers\GlossaryController;
use App\Domains\Courses\Http\Controllers\I18nPreviewController;
use App\Domains\Courses\Http\Controllers\LearnActivityController;
use App\Domains\Courses\Http\Controllers\LearnAssessmentController;
use App\Domains\Courses\Http\Controllers\LearnCatalogController;
use App\Domains\Courses\Http\Controllers\LearnCourseController;
use App\Domains\Courses\Http\Controllers\LearnDashboardController;
use App\Domains\Courses\Http\Controllers\LearnLessonController;
use App\Domains\Courses\Http\Controllers\LearnMediaController;
use App\Domains\Courses\Http\Controllers\LearnScheduleController;
use App\Domains\Courses\Http\Controllers\LessonPlayerController;
use App\Domains\ExamsGrades\Http\Controllers\AwardController;
use App\Domains\ExamsGrades\Http\Controllers\CompetencyController;
use App\Domains\ExamsGrades\Http\Controllers\ExamController;
use App\Domains\ExamsGrades\Http\Controllers\ExamMarkController;
use App\Domains\ExamsGrades\Http\Controllers\ExamTypeController;
use App\Domains\ExamsGrades\Http\Controllers\GradebookController;
use App\Domains\ExamsGrades\Http\Controllers\GradeScaleController;
use App\Domains\ExamsGrades\Http\Controllers\ReportCardController;
use App\Domains\ExamsGrades\Http\Controllers\ReportCardTemplateController;
use App\Domains\ExamsGrades\Http\Controllers\StandardController;
use App\Domains\ExamsGrades\Http\Controllers\WeightSchemeController;
use App\Domains\Finance\Http\Controllers\ArrearsController;
use App\Domains\Finance\Http\Controllers\CollectionsController;
use App\Domains\Finance\Http\Controllers\FeeAdjustmentController;
use App\Domains\Finance\Http\Controllers\FeeItemController;
use App\Domains\Finance\Http\Controllers\FeeStructureController;
use App\Domains\Finance\Http\Controllers\InvoiceController;
use App\Domains\Finance\Http\Controllers\ManualReceiptController;
use App\Domains\Finance\Http\Controllers\PaymentPlanController;
use App\Domains\Finance\Http\Controllers\ReceiptDocumentController;
use App\Domains\Finance\Http\Controllers\ReconciliationController;
use App\Domains\Hifz\Http\Controllers\QuranProgressController;
use App\Domains\HR\Http\Controllers\AppraisalController;
use App\Domains\HR\Http\Controllers\ComplianceController;
use App\Domains\HR\Http\Controllers\CpdRecordController;
use App\Domains\HR\Http\Controllers\InstructorController as AdminInstructorController;
use App\Domains\HR\Http\Controllers\JobApplicationController;
use App\Domains\HR\Http\Controllers\JobPostingController;
use App\Domains\HR\Http\Controllers\LeaveBalanceController;
use App\Domains\HR\Http\Controllers\LeaveTypeController;
use App\Domains\HR\Http\Controllers\LessonObservationController;
use App\Domains\HR\Http\Controllers\OnboardingController;
use App\Domains\HR\Http\Controllers\PayrollPeriodController;
use App\Domains\HR\Http\Controllers\PayslipDocumentController;
use App\Domains\HR\Http\Controllers\StaffAttendanceController;
use App\Domains\HR\Http\Controllers\StaffAttendanceReportController;
use App\Domains\HR\Http\Controllers\StaffContractController;
use App\Domains\Identity\Http\Controllers\ProfileController;
use App\Domains\Notifications\Http\Controllers\NotificationController;
use App\Domains\Offerings\Http\Controllers\CourseOfferingController;
use App\Domains\Offerings\Http\Controllers\OfferingSessionController;
use App\Domains\Offerings\Http\Controllers\TeacherScheduleController;
use App\Domains\People\Http\Controllers\CustomFieldDefinitionController;
use App\Domains\People\Http\Controllers\StaffDirectoryController;
use App\Domains\People\Http\Controllers\StudentConsentController;
use App\Domains\People\Http\Controllers\StudentController;
use App\Domains\People\Http\Controllers\StudentDirectoryController;
use App\Domains\People\Http\Controllers\TeacherController;
use App\Domains\Portal\Http\Controllers\DashboardController;
use App\Domains\Portal\Http\Controllers\EnhancedDashboardController;
use App\Domains\Portal\Http\Controllers\GuardianChildrenController;
use App\Domains\Portal\Http\Controllers\PortalAbsenceNoteController;
use App\Domains\Portal\Http\Controllers\PortalAppraisalController;
use App\Domains\Portal\Http\Controllers\PortalAttendanceController;
use App\Domains\Portal\Http\Controllers\PortalAwardController;
use App\Domains\Portal\Http\Controllers\PortalBehaviorController;
use App\Domains\Portal\Http\Controllers\PortalEventController;
use App\Domains\Portal\Http\Controllers\PortalExamController;
use App\Domains\Portal\Http\Controllers\PortalHolidayController;
use App\Domains\Portal\Http\Controllers\PortalHomeController;
use App\Domains\Portal\Http\Controllers\PortalInvoiceController;
use App\Domains\Portal\Http\Controllers\PortalLearningController;
use App\Domains\Portal\Http\Controllers\PortalLeaveBalanceController;
use App\Domains\Portal\Http\Controllers\PortalMeetingController;
use App\Domains\Portal\Http\Controllers\PortalPayslipController;
use App\Domains\Portal\Http\Controllers\PortalPerformanceController;
use App\Domains\Portal\Http\Controllers\PortalReportCardController;
use App\Domains\Portal\Http\Controllers\PortalStaffCheckInController;
use App\Domains\Portal\Http\Controllers\StaffOverviewController;
use App\Domains\PrayerTimes\Http\Controllers\Admin\BroadcastController as AdminPrayerBroadcastController;
use App\Domains\PrayerTimes\Http\Controllers\Admin\ImportController as AdminPrayerImportController;
use App\Domains\PrayerTimes\Http\Controllers\Admin\IslandController as AdminPrayerIslandController;
use App\Domains\PrayerTimes\Http\Controllers\Admin\RecipientGroupController as AdminPrayerGroupController;
use App\Domains\Settings\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Domains\Settings\Http\Controllers\AnalyticsController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\CourseController as AdminCourseController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\DailyContentController as AdminDailyContentController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\DailySubscriptionController as AdminDailySubscriptionController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\FunnelController as AdminFunnelController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\LeadController as AdminLeadController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\PageController as AdminPageController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\ResearchPostController as AdminResearchPostController;
use App\Domains\Website\Http\Controllers\EventAdminController;
use App\Support\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// Authentication routes (using Breeze)
require __DIR__.'/auth.php';

// Language switching
Route::get('/locale/{locale}', [LocaleController::class, 'setLocale'])->name('locale');

// Dashboard routes
Route::middleware(['auth', 'trackActivity'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/enhanced-dashboard', [EnhancedDashboardController::class, 'index'])->name('enhanced.dashboard');
    Route::get('/portal/home/export', [PortalHomeController::class, 'export'])->name('portal.home.export');
    Route::get('/portal/home', [PortalHomeController::class, 'index'])->name('portal.home');
    Route::get('/portal/overview/export', [StaffOverviewController::class, 'export'])->name('portal.overview.export');
    Route::get('/portal/overview', [StaffOverviewController::class, 'index'])->name('portal.overview');
    Route::get('/portal/meetings/export', [PortalMeetingController::class, 'export'])->name('portal.meetings.export');
    Route::get('/portal/meetings', [PortalMeetingController::class, 'index'])->name('portal.meetings');
    Route::post('/portal/meetings/{slot}/book', [PortalMeetingController::class, 'book'])->name('portal.meetings.book')->whereNumber('slot');
    Route::post('/portal/meetings/bookings/{booking}/cancel', [PortalMeetingController::class, 'cancel'])->name('portal.meetings.cancel')->whereNumber('booking');
    Route::get('/portal/children', [GuardianChildrenController::class, 'index'])->name('portal.children');
    Route::get('/portal/holidays', [PortalHolidayController::class, 'index'])->name('portal.holidays');
    Route::get('/portal/attendance', [PortalAttendanceController::class, 'index'])->name('portal.attendance');
    Route::get('/portal/behavior', [PortalBehaviorController::class, 'index'])->name('portal.behavior');
    Route::get('/portal/absence-notes', [PortalAbsenceNoteController::class, 'index'])->name('portal.absence-notes');
    Route::post('/portal/absence-notes', [PortalAbsenceNoteController::class, 'store'])->name('portal.absence-notes.store');
    Route::get('/portal/events', [PortalEventController::class, 'index'])->name('portal.events');
    Route::post('/portal/events/{event}/register', [PortalEventController::class, 'register'])->name('portal.events.register')->whereNumber('event');
    Route::post('/portal/events/registrations/{registration}/confirm', [PortalEventController::class, 'confirm'])->name('portal.events.confirm')->whereNumber('registration');
    Route::get('/portal/exams', [PortalExamController::class, 'index'])->name('portal.exams');
    Route::get('/portal/report-cards', [PortalReportCardController::class, 'index'])->name('portal.report-cards');
    Route::get('/portal/report-cards/{reportCard}/download', [PortalReportCardController::class, 'download'])->name('portal.report-cards.download');
    Route::get('/portal/transcript', [PortalReportCardController::class, 'transcript'])->name('portal.transcript');
    Route::get('/portal/awards', [PortalAwardController::class, 'index'])->name('portal.awards');
    Route::get('/portal/invoices', [PortalInvoiceController::class, 'index'])->name('portal.invoices');
    Route::post('/portal/invoices/{invoice}/pay', [PortalInvoiceController::class, 'pay'])->name('portal.invoices.pay');
    Route::get('/portal/staff-check-in', [PortalStaffCheckInController::class, 'index'])->name('portal.staff-check-in');
    Route::post('/portal/staff-check-in', [PortalStaffCheckInController::class, 'store'])->name('portal.staff-check-in.store');
    Route::get('/portal/leave', [PortalLeaveBalanceController::class, 'index'])->name('portal.leave');
    Route::get('/portal/appraisals', [PortalAppraisalController::class, 'index'])->name('portal.appraisals');
    Route::post('/portal/appraisals/{appraisal}/acknowledge', [PortalAppraisalController::class, 'acknowledge'])->name('portal.appraisals.acknowledge');
    Route::get('/portal/payslips', [PortalPayslipController::class, 'index'])->name('portal.payslips');
    Route::get('/portal/learning', [PortalLearningController::class, 'index'])->name('portal.learning');
    Route::get('/portal/performance/export', [PortalPerformanceController::class, 'export'])->name('portal.performance.export');
    Route::get('/portal/performance', [PortalPerformanceController::class, 'index'])->name('portal.performance');
    Route::get('/learn', [LearnDashboardController::class, 'index'])->name('learn.dashboard');
    Route::get('/learn/schedule', [LearnScheduleController::class, 'index'])->name('learn.schedule');
    Route::get('/learn/arabic-report', [LearnArabicReportController::class, 'index'])->name('learn.arabic-report');
    Route::get('/teach/schedule', [TeacherScheduleController::class, 'index'])->name('teach.schedule');
    Route::get('/learn/catalog', [LearnCatalogController::class, 'index'])->name('learn.catalog');
    Route::post('/learn/courses/{course}/enroll', [LearnCatalogController::class, 'enroll'])->name('learn.courses.enroll')->whereNumber('course');
    Route::get('/learn/courses/{course}', [LearnCourseController::class, 'show'])->name('learn.courses.show')->whereNumber('course');
    Route::get('/learn/lessons/{lesson}', [LearnLessonController::class, 'show'])->name('learn.lessons.show')->whereNumber('lesson');
    Route::post('/learn/lessons/{lesson}/complete', [LearnLessonController::class, 'complete'])->name('learn.lessons.complete')->whereNumber('lesson');
    Route::get('/learn/media/{media}', [LearnMediaController::class, 'show'])->name('learn.media.show')->whereNumber('media');
    Route::get('/learn/activities/{activity}', [LearnActivityController::class, 'show'])->name('learn.activities.show')->whereNumber('activity');
    Route::post('/learn/activities/{activity}/autosave', [LearnActivityController::class, 'autosave'])->name('learn.activities.autosave')->whereNumber('activity');
    Route::post('/learn/activities/{activity}/submit', [LearnActivityController::class, 'submit'])->name('learn.activities.submit')->whereNumber('activity');
    Route::get('/learn/assessments/{assessment}', [LearnAssessmentController::class, 'show'])->name('learn.assessments.show')->whereNumber('assessment');
    Route::post('/learn/assessments/{assessment}/autosave', [LearnAssessmentController::class, 'autosave'])->name('learn.assessments.autosave')->whereNumber('assessment');
    Route::post('/learn/assessments/{assessment}/submit', [LearnAssessmentController::class, 'submit'])->name('learn.assessments.submit')->whereNumber('assessment');
    Route::get('/hr/payslips/{payslip}/document', [PayslipDocumentController::class, 'show'])->name('hr.payslips.document')->whereNumber('payslip');
    Route::get('/finance/receipts/{receipt}/document', [ReceiptDocumentController::class, 'show'])->name('finance.receipts.show')->whereNumber('receipt');

    Route::get('exams/{exam}/marks/export', [ExamMarkController::class, 'export'])->name('exams.marks.export');
    Route::get('exams/{exam}/marks', [ExamMarkController::class, 'show'])->name('exams.marks.show');
    Route::put('exams/{exam}/marks', [ExamMarkController::class, 'update'])->name('exams.marks.update');
    Route::post('exams/{exam}/marks/import', [ExamMarkController::class, 'import'])->name('exams.marks.import');

    Route::get('exams/gradebook/export', [GradebookController::class, 'export'])->name('exams.gradebook.export');
    Route::get('exams/gradebook', [GradebookController::class, 'index'])->name('exams.gradebook.index');
    Route::post('exams/gradebook/compute', [GradebookController::class, 'compute'])->name('exams.gradebook.compute');
    Route::get('academics/gradebook', [GradebookController::class, 'redirectFromAcademics'])->name('academics.gradebook.redirect');
    Route::post('exams/competencies/assess', [CompetencyController::class, 'assess'])->name('exams.competencies.assess');

    Route::get('academics/registers/today', [TeacherRegisterController::class, 'today'])->name('academics.registers.today');
    Route::post('academics/registers/today/generate', [TeacherRegisterController::class, 'generateToday'])->name('academics.registers.today.generate');
    Route::get('academics/registers/export', [RegisterReportController::class, 'export'])->name('academics.registers.export');
    Route::post('academics/registers/generate', [RegisterReportController::class, 'generate'])->name('academics.registers.generate');
    Route::get('academics/registers', [RegisterReportController::class, 'index'])->name('academics.registers.index');
    Route::get('academics/registers/{lessonLog}', [TeacherRegisterController::class, 'show'])->name('academics.registers.show');
    Route::put('academics/registers/{lessonLog}', [TeacherRegisterController::class, 'update'])->name('academics.registers.update');
    Route::post('academics/registers/{lessonLog}/unlock', [RegisterReportController::class, 'unlock'])->name('academics.registers.unlock');

    Route::get('academics/plans', [CoursePlanController::class, 'index'])->name('academics.plans.index');
    Route::post('academics/plans', [CoursePlanController::class, 'store'])->name('academics.plans.store');
    Route::post('academics/plans/{coursePlan}/topics', [CoursePlanController::class, 'storeTopic'])->name('academics.plans.topics.store');
    Route::post('academics/plans/{coursePlan}/copy', [CoursePlanController::class, 'copy'])->name('academics.plans.copy');

    Route::get('academics/attendance/export', [AttendanceReportController::class, 'export'])->name('academics.attendance.export');
    Route::get('academics/attendance/daily', [DailyAttendanceController::class, 'index'])->name('academics.attendance.daily');
    Route::post('academics/attendance/daily', [DailyAttendanceController::class, 'store'])->name('academics.attendance.daily.store');
    Route::get('academics/attendance', [AttendanceReportController::class, 'index'])->name('academics.attendance.index');

    Route::get('academics/absence-notes/export', [AbsenceNoteReviewController::class, 'export'])->name('academics.absence-notes.export');
    Route::get('academics/absence-notes', [AbsenceNoteReviewController::class, 'index'])->name('academics.absence-notes.index');
    Route::post('academics/absence-notes/{absenceNote}/approve', [AbsenceNoteReviewController::class, 'approve'])->name('academics.absence-notes.approve');
    Route::post('academics/absence-notes/{absenceNote}/reject', [AbsenceNoteReviewController::class, 'reject'])->name('academics.absence-notes.reject');

    Route::get('academics/behavior/export', [BehaviorRecordController::class, 'export'])->name('academics.behavior.export');
    Route::get('academics/behavior', [BehaviorRecordController::class, 'index'])->name('academics.behavior.index');
    Route::post('academics/behavior', [BehaviorRecordController::class, 'store'])->name('academics.behavior.store');
    Route::put('academics/behavior/{behaviorRecord}', [BehaviorRecordController::class, 'update'])->name('academics.behavior.update');
    Route::delete('academics/behavior/{behaviorRecord}', [BehaviorRecordController::class, 'destroy'])->name('academics.behavior.destroy');

    Route::get('academics/requests/export', [SchoolRequestController::class, 'export'])->name('academics.requests.export');
    Route::get('academics/requests', [SchoolRequestController::class, 'index'])->name('academics.requests.index');
    Route::post('academics/requests', [SchoolRequestController::class, 'store'])->name('academics.requests.store');
    Route::post('academics/requests/{schoolRequest}/review', [SchoolRequestController::class, 'review'])->name('academics.requests.review');

    // Student routes
    Route::resource('students', StudentController::class);
    Route::get('/students/{student}/quran-progress', [StudentController::class, 'quranProgress'])->name('students.quran-progress');

    // Teacher routes
    Route::resource('teachers', TeacherController::class);

    // Quran Progress routes
    Route::resource('quran-progress', QuranProgressController::class);
    Route::post('/quran-progress/{student}/update', [QuranProgressController::class, 'updateProgress'])->name('quran-progress.update-progress');

    // Hifz Progress module
    require base_path('app/Domains/Hifz/routes.php');

    // Announcement routes
    Route::resource('announcements', AnnouncementController::class);

    // E-Learning routes
    Route::get('/e-learning', [ELearningController::class, 'index'])->name('e-learning.index');
    Route::get('/e-learning/quran', [ELearningController::class, 'quranLessons'])->name('e-learning.quran');
    Route::get('/e-learning/arabic', [ELearningController::class, 'arabicLessons'])->name('e-learning.arabic');
    Route::get('/e-learning/islamic-studies', [ELearningController::class, 'islamicStudies'])->name('e-learning.islamic-studies');
    Route::get('/e-learning/{subject}', [ELearningController::class, 'show'])->name('e-learning.show');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Analytics routes (admin only)
    Route::middleware(['role:admin|super_admin'])->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/reports', [AnalyticsController::class, 'reports'])->name('analytics.reports');
        Route::post('/analytics/reports/generate', [AnalyticsController::class, 'generateReport'])->name('analytics.reports.generate');
        Route::get('/analytics/reports/{id}/download', [AnalyticsController::class, 'downloadReport'])->name('analytics.reports.download');
        Route::delete('/analytics/reports/{id}', [AnalyticsController::class, 'deleteReport'])->name('analytics.reports.delete');
    });

    // Substitution routes
    Route::middleware(['role:super_admin|admin|headmaster|supervisor|teacher'])->group(function () {
        Route::get('substitutions/absences', [SubstitutionRequestController::class, 'absencesIndex'])->name('absences.index');
        Route::get('substitutions/absences/create', [SubstitutionRequestController::class, 'absencesCreate'])->name('absences.create');
        Route::post('substitutions/absences', [SubstitutionRequestController::class, 'absencesStore'])->name('absences.store');
        Route::get('substitutions/absences/{absence}/edit', [SubstitutionRequestController::class, 'absencesEdit'])->name('absences.edit');
        Route::put('substitutions/absences/{absence}', [SubstitutionRequestController::class, 'absencesUpdate'])->name('absences.update');
        Route::delete('substitutions/absences/{absence}', [SubstitutionRequestController::class, 'absencesDestroy'])->name('absences.destroy');
        Route::resource('substitutions/requests', SubstitutionRequestController::class)->names('substitutions.requests');
        Route::post('substitutions/requests/{request}/take', [SubstitutionRequestController::class, 'take'])->name('substitutions.requests.take');
        Route::post('substitutions/requests/{request}/assign', [SubstitutionRequestController::class, 'assign'])->name('substitutions.requests.assign');
    });

    // Admin user management (super_admin only)
    Route::prefix('admin/users')->middleware(['role:super_admin'])->group(function () {
        Route::get('/', [\App\Domains\Identity\Http\Controllers\AdminUserController::class, 'index'])->name('admin.users.index');
        Route::delete('/{user}', [\App\Domains\Identity\Http\Controllers\AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });

    // Admin enrollment management
    Route::prefix('admin/enrollments')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::get('/', [AdminEnrollmentController::class, 'index'])->name('admin.enrollments.index');
        Route::get('/export', [AdminEnrollmentController::class, 'export'])->name('admin.enrollments.export');
        Route::get('/payments', [AdminEnrollmentController::class, 'payments'])->name('admin.enrollments.payments');
        Route::get('/{enrollment}', [AdminEnrollmentController::class, 'show'])->name('admin.enrollments.show');
        Route::patch('/{enrollment}/activate', [AdminEnrollmentController::class, 'activate'])->name('admin.enrollments.activate');
        Route::patch('/{enrollment}/reject', [AdminEnrollmentController::class, 'reject'])->name('admin.enrollments.reject');
    });

    // Instructor management
    Route::prefix('admin/instructors')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::get('/', [AdminInstructorController::class, 'index'])->name('admin.instructors.index');
        Route::get('/create', [AdminInstructorController::class, 'create'])->name('admin.instructors.create');
        Route::post('/', [AdminInstructorController::class, 'store'])->name('admin.instructors.store');
        Route::get('/{instructor}/edit', [AdminInstructorController::class, 'edit'])->name('admin.instructors.edit');
        Route::put('/{instructor}', [AdminInstructorController::class, 'update'])->name('admin.instructors.update');
        Route::delete('/{instructor}', [AdminInstructorController::class, 'destroy'])->name('admin.instructors.destroy');
    });

    // Admin Settings
    Route::prefix('admin/settings')->middleware(['role:super_admin'])->group(function () {
        Route::get('/', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/clear-cache', [AdminSettingsController::class, 'clearCache'])->name('admin.settings.clear-cache');
    });

    // Admin CMS routes
    Route::prefix('admin/public-site')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::resource('pages', AdminPageController::class)->names([
            'index' => 'admin.pages.index',
            'create' => 'admin.pages.create',
            'store' => 'admin.pages.store',
            'show' => 'admin.pages.show',
            'edit' => 'admin.pages.edit',
            'update' => 'admin.pages.update',
            'destroy' => 'admin.pages.destroy',
        ]);

        Route::resource('courses', AdminCourseController::class)->names([
            'index' => 'admin.courses.index',
            'create' => 'admin.courses.create',
            'store' => 'admin.courses.store',
            'edit' => 'admin.courses.edit',
            'update' => 'admin.courses.update',
            'destroy' => 'admin.courses.destroy',
        ]);

        Route::get('leads/export', [AdminLeadController::class, 'export'])->name('admin.leads.export');
        Route::get('leads', [AdminLeadController::class, 'index'])->name('admin.leads.index');
        Route::get('funnel/export', [AdminFunnelController::class, 'export'])->name('admin.funnel.export');
        Route::get('funnel', [AdminFunnelController::class, 'index'])->name('admin.funnel.index');
        Route::get('daily-subscriptions/export', [AdminDailySubscriptionController::class, 'export'])->name('admin.daily-subscriptions.export');
        Route::get('daily-subscriptions', [AdminDailySubscriptionController::class, 'index'])->name('admin.daily-subscriptions.index');
        Route::get('daily-content/export', [AdminDailyContentController::class, 'export'])->name('admin.daily-content.export');
        Route::get('daily-content/queue', [AdminDailyContentController::class, 'queue'])->name('admin.daily-content.queue');
        Route::get('daily-content/ayah-preview', [AdminDailyContentController::class, 'ayahPreview'])->name('admin.daily-content.ayah-preview');
        Route::post('daily-content/batch', [AdminDailyContentController::class, 'batch'])->name('admin.daily-content.batch');
        Route::get('daily-content/create', [AdminDailyContentController::class, 'create'])->name('admin.daily-content.create');
        Route::get('daily-content', [AdminDailyContentController::class, 'index'])->name('admin.daily-content.index');
        Route::post('daily-content', [AdminDailyContentController::class, 'store'])->name('admin.daily-content.store');
        Route::get('daily-content/{dailyContent}/edit', [AdminDailyContentController::class, 'edit'])->name('admin.daily-content.edit')->whereNumber('dailyContent');
        Route::put('daily-content/{dailyContent}', [AdminDailyContentController::class, 'update'])->name('admin.daily-content.update')->whereNumber('dailyContent');
        Route::post('daily-content/{dailyContent}/approve', [AdminDailyContentController::class, 'approve'])->name('admin.daily-content.approve')->whereNumber('dailyContent');

        Route::get('research/export', [AdminResearchPostController::class, 'export'])->name('admin.research.export');
        Route::get('research/create', [AdminResearchPostController::class, 'create'])->name('admin.research.create');
        Route::get('research', [AdminResearchPostController::class, 'index'])->name('admin.research.index');
        Route::post('research', [AdminResearchPostController::class, 'store'])->name('admin.research.store');
        Route::get('research/{post}/edit', [AdminResearchPostController::class, 'edit'])->name('admin.research.edit')->whereNumber('post');
        Route::put('research/{post}', [AdminResearchPostController::class, 'update'])->name('admin.research.update')->whereNumber('post');
    });

    Route::prefix('admin/prayer-times')->middleware(['role:super_admin|admin|headmaster|supervisor', 'can:prayer.manage'])->group(function () {
        Route::get('islands/export', [AdminPrayerIslandController::class, 'export'])->name('admin.prayer-times.islands.export');
        Route::get('islands', [AdminPrayerIslandController::class, 'index'])->name('admin.prayer-times.islands');
        Route::get('import', [AdminPrayerImportController::class, 'index'])->name('admin.prayer-times.import');
        Route::post('import', [AdminPrayerImportController::class, 'store'])->name('admin.prayer-times.import.store');
        Route::get('groups', [AdminPrayerGroupController::class, 'index'])->name('admin.prayer-times.groups.index');
        Route::get('groups/create', [AdminPrayerGroupController::class, 'create'])->name('admin.prayer-times.groups.create');
        Route::post('groups', [AdminPrayerGroupController::class, 'store'])->name('admin.prayer-times.groups.store');
        Route::get('groups/{group}/edit', [AdminPrayerGroupController::class, 'edit'])->name('admin.prayer-times.groups.edit')->whereNumber('group');
        Route::put('groups/{group}', [AdminPrayerGroupController::class, 'update'])->name('admin.prayer-times.groups.update')->whereNumber('group');
        Route::get('broadcasts/export', [AdminPrayerBroadcastController::class, 'export'])->name('admin.prayer-times.broadcasts.export');
        Route::get('broadcasts/create', [AdminPrayerBroadcastController::class, 'create'])->name('admin.prayer-times.broadcasts.create');
        Route::get('broadcasts', [AdminPrayerBroadcastController::class, 'index'])->name('admin.prayer-times.broadcasts.index');
        Route::post('broadcasts', [AdminPrayerBroadcastController::class, 'store'])->name('admin.prayer-times.broadcasts.store');
        Route::get('broadcasts/{broadcast}/edit', [AdminPrayerBroadcastController::class, 'edit'])->name('admin.prayer-times.broadcasts.edit')->whereNumber('broadcast');
        Route::put('broadcasts/{broadcast}', [AdminPrayerBroadcastController::class, 'update'])->name('admin.prayer-times.broadcasts.update')->whereNumber('broadcast');
        Route::post('broadcasts/{broadcast}/preview', [AdminPrayerBroadcastController::class, 'preview'])->name('admin.prayer-times.broadcasts.preview')->whereNumber('broadcast');
        Route::post('broadcasts/{broadcast}/confirm', [AdminPrayerBroadcastController::class, 'confirm'])->name('admin.prayer-times.broadcasts.confirm')->whereNumber('broadcast');
    });

    Route::prefix('people')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::get('students/export', [StudentDirectoryController::class, 'export'])->name('people.students.export');
        Route::get('students', [StudentDirectoryController::class, 'index'])->name('people.students.index');
        Route::post('students', [StudentDirectoryController::class, 'store'])->name('people.students.store');
        Route::get('students/{student}', [StudentDirectoryController::class, 'show'])->name('people.students.show');
        Route::put('students/{student}', [StudentDirectoryController::class, 'update'])->name('people.students.update');
        Route::put('students/{student}/custom-fields', [StudentDirectoryController::class, 'updateCustomFields'])->name('people.students.custom-fields.update');
        Route::post('students/{student}/guardians', [StudentDirectoryController::class, 'attachGuardian'])->name('people.students.guardians.attach');
        Route::delete('students/{student}/guardians/{guardian}', [StudentDirectoryController::class, 'detachGuardian'])->name('people.students.guardians.detach');
        Route::post('students/{student}/consents', [StudentConsentController::class, 'store'])->name('people.students.consents.store');

        Route::get('staff', [StaffDirectoryController::class, 'index'])->name('people.staff.index');
        Route::post('staff', [StaffDirectoryController::class, 'store'])->name('people.staff.store');
        Route::get('staff/{staffProfile}', [StaffDirectoryController::class, 'show'])->name('people.staff.show');
        Route::put('staff/{staffProfile}', [StaffDirectoryController::class, 'update'])->name('people.staff.update');
        Route::post('staff/{staffProfile}/qualifications', [StaffDirectoryController::class, 'storeQualification'])->name('people.staff.qualifications.store');
        Route::delete('staff/{staffProfile}/qualifications/{qualification}', [StaffDirectoryController::class, 'destroyQualification'])->name('people.staff.qualifications.destroy');

        Route::get('custom-fields/admission-preview', [CustomFieldDefinitionController::class, 'admissionPreview'])->name('people.custom-fields.admission-preview');
        Route::get('custom-fields', [CustomFieldDefinitionController::class, 'index'])->name('people.custom-fields.index');
        Route::post('custom-fields', [CustomFieldDefinitionController::class, 'store'])->name('people.custom-fields.store');
        Route::put('custom-fields/{definition}', [CustomFieldDefinitionController::class, 'update'])->name('people.custom-fields.update');
        Route::delete('custom-fields/{definition}', [CustomFieldDefinitionController::class, 'destroy'])->name('people.custom-fields.destroy');
    });

    Route::prefix('academics')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::get('years', [AcademicYearController::class, 'index'])->name('academics.years.index');
        Route::post('years', [AcademicYearController::class, 'store'])->name('academics.years.store');
        Route::post('years/{academicYear}/terms', [AcademicYearController::class, 'storeTerm'])->name('academics.years.terms.store');
        Route::post('years/{academicYear}/terms/{term}/close', [AcademicYearController::class, 'closeTerm'])->name('academics.years.terms.close');
        Route::post('years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academics.years.activate');
        Route::post('years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academics.years.close');

        Route::get('classes', [ClassDirectoryController::class, 'index'])->name('academics.classes.index');
        Route::post('classes', [ClassDirectoryController::class, 'store'])->name('academics.classes.store');
        Route::get('classes/{classRoom}', [ClassDirectoryController::class, 'show'])->name('academics.classes.show');
        Route::get('classes/{classRoom}/assessments/export', [ClassDirectoryController::class, 'exportAssessments'])->name('academics.classes.assessments.export');
        Route::put('classes/{classRoom}', [ClassDirectoryController::class, 'update'])->name('academics.classes.update');
        Route::post('classes/{classRoom}/assign', [ClassDirectoryController::class, 'assign'])->name('academics.classes.assign');

        Route::get('rooms/export', [RoomDirectoryController::class, 'export'])->name('academics.rooms.export');
        Route::get('rooms', [RoomDirectoryController::class, 'index'])->name('academics.rooms.index');
        Route::post('rooms', [RoomDirectoryController::class, 'store'])->name('academics.rooms.store');
        Route::put('rooms/{room}', [RoomDirectoryController::class, 'update'])->name('academics.rooms.update');

        Route::get('periods/export', [PeriodDirectoryController::class, 'export'])->name('academics.periods.export');
        Route::get('periods', [PeriodDirectoryController::class, 'index'])->name('academics.periods.index');
        Route::post('periods', [PeriodDirectoryController::class, 'store'])->name('academics.periods.store');
        Route::put('periods/{period}', [PeriodDirectoryController::class, 'update'])->name('academics.periods.update');

        Route::get('bookings/export', [RoomBookingController::class, 'export'])->name('academics.bookings.export');
        Route::get('bookings', [RoomBookingController::class, 'index'])->name('academics.bookings.index');
        Route::post('bookings', [RoomBookingController::class, 'store'])->name('academics.bookings.store');
        Route::put('bookings/{roomBooking}', [RoomBookingController::class, 'update'])->name('academics.bookings.update');
        Route::delete('bookings/{roomBooking}', [RoomBookingController::class, 'destroy'])->name('academics.bookings.destroy');

        Route::get('meetings/export', [MeetingSlotController::class, 'export'])->name('academics.meetings.export');
        Route::get('meetings', [MeetingSlotController::class, 'index'])->name('academics.meetings.index');
        Route::post('meetings', [MeetingSlotController::class, 'store'])->name('academics.meetings.store');
        Route::put('meetings/{meetingSlot}', [MeetingSlotController::class, 'update'])->name('academics.meetings.update');
        Route::delete('meetings/{meetingSlot}', [MeetingSlotController::class, 'destroy'])->name('academics.meetings.destroy');

        Route::get('calendar/export', [CalendarDayController::class, 'export'])->name('academics.calendar.export');
        Route::get('calendar', [CalendarDayController::class, 'index'])->name('academics.calendar.index');
        Route::post('calendar', [CalendarDayController::class, 'store'])->name('academics.calendar.store');
        Route::put('calendar/{calendarDay}', [CalendarDayController::class, 'update'])->name('academics.calendar.update');
        Route::delete('calendar/{calendarDay}', [CalendarDayController::class, 'destroy'])->name('academics.calendar.destroy');

        Route::get('events/export', [EventAdminController::class, 'export'])->name('academics.events.export');
        Route::get('events', [EventAdminController::class, 'index'])->name('academics.events.index');
        Route::post('events', [EventAdminController::class, 'store'])->name('academics.events.store');
        Route::get('events/{event}', [EventAdminController::class, 'show'])->name('academics.events.show')->whereNumber('event');
        Route::put('events/{event}', [EventAdminController::class, 'update'])->name('academics.events.update')->whereNumber('event');
        Route::post('events/{event}/register', [EventAdminController::class, 'register'])->name('academics.events.register')->whereNumber('event');
        Route::post('events/{event}/second-round', [EventAdminController::class, 'secondRound'])->name('academics.events.second-round')->whereNumber('event');
        Route::post('events/{event}/registrations/{registration}/confirm', [EventAdminController::class, 'confirm'])->name('academics.events.confirm')->whereNumber('event')->whereNumber('registration');
        Route::get('events/{event}/registrations/export', [EventAdminController::class, 'exportRegistrations'])->name('academics.events.registrations.export')->whereNumber('event');

        Route::get('timetable/export', [TimetableBuilderController::class, 'export'])->name('academics.timetable.export');
        Route::get('timetable', [TimetableBuilderController::class, 'index'])->name('academics.timetable.index');
        Route::post('timetable', [TimetableBuilderController::class, 'store'])->name('academics.timetable.store');
        Route::post('timetable/preview', [TimetableBuilderController::class, 'preview'])->name('academics.timetable.preview');
        Route::post('timetable/copy-from-class', [TimetableBuilderController::class, 'copyFromClass'])->name('academics.timetable.copy-from-class');
        Route::post('timetable/copy-week', [TimetableBuilderController::class, 'copyWeek'])->name('academics.timetable.copy-week');
        Route::put('timetable/{timetable}', [TimetableBuilderController::class, 'update'])->name('academics.timetable.update');
        Route::delete('timetable/{timetable}', [TimetableBuilderController::class, 'destroy'])->name('academics.timetable.destroy');

        Route::get('promotion', [PromotionController::class, 'create'])->name('academics.promotion.create');
        Route::post('promotion/dry-run', [PromotionController::class, 'dryRun'])->name('academics.promotion.dry-run');
        Route::post('promotion', [PromotionController::class, 'commit'])->name('academics.promotion.commit');
    });

    Route::prefix('exams')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::get('scales/export', [GradeScaleController::class, 'export'])->name('exams.scales.export');
        Route::get('scales', [GradeScaleController::class, 'index'])->name('exams.scales.index');
        Route::post('scales', [GradeScaleController::class, 'store'])->name('exams.scales.store');
        Route::put('scales/{gradeScale}', [GradeScaleController::class, 'update'])->name('exams.scales.update');

        Route::get('types/export', [ExamTypeController::class, 'export'])->name('exams.types.export');
        Route::get('types', [ExamTypeController::class, 'index'])->name('exams.types.index');
        Route::post('types', [ExamTypeController::class, 'store'])->name('exams.types.store');
        Route::put('types/{examType}', [ExamTypeController::class, 'update'])->name('exams.types.update');

        Route::get('weights/export', [WeightSchemeController::class, 'export'])->name('exams.weights.export');
        Route::get('weights', [WeightSchemeController::class, 'index'])->name('exams.weights.index');
        Route::post('weights', [WeightSchemeController::class, 'store'])->name('exams.weights.store');
        Route::put('weights/{weightScheme}', [WeightSchemeController::class, 'update'])->name('exams.weights.update');

        Route::get('schedule/export', [ExamController::class, 'export'])->name('exams.export');
        Route::get('schedule', [ExamController::class, 'index'])->name('exams.index');
        Route::post('schedule', [ExamController::class, 'store'])->name('exams.store');
        Route::post('schedule/bulk', [ExamController::class, 'bulk'])->name('exams.bulk');
        Route::put('schedule/{exam}', [ExamController::class, 'update'])->name('exams.update');
        Route::post('schedule/{exam}/transition', [ExamController::class, 'transition'])->name('exams.transition');

        Route::get('competencies/export', [CompetencyController::class, 'export'])->name('exams.competencies.export');
        Route::get('competencies', [CompetencyController::class, 'index'])->name('exams.competencies.index');
        Route::post('competencies', [CompetencyController::class, 'store'])->name('exams.competencies.store');

        Route::get('standards/export', [StandardController::class, 'export'])->name('exams.standards.export');
        Route::get('standards', [StandardController::class, 'index'])->name('exams.standards.index');
        Route::post('standards', [StandardController::class, 'store'])->name('exams.standards.store');
        Route::post('standards/tag', [StandardController::class, 'tag'])->name('exams.standards.tag');

        Route::get('report-templates/export', [ReportCardTemplateController::class, 'export'])->name('exams.report-templates.export');
        Route::get('report-templates', [ReportCardTemplateController::class, 'index'])->name('exams.report-templates.index');
        Route::post('report-templates', [ReportCardTemplateController::class, 'store'])->name('exams.report-templates.store');
        Route::put('report-templates/{reportCardTemplate}', [ReportCardTemplateController::class, 'update'])->name('exams.report-templates.update');

        Route::get('report-cards/export', [ReportCardController::class, 'export'])->name('exams.report-cards.export');
        Route::get('report-cards', [ReportCardController::class, 'index'])->name('exams.report-cards.index');
        Route::post('report-cards/generate', [ReportCardController::class, 'generate'])->name('exams.report-cards.generate');
        Route::post('report-cards/publish', [ReportCardController::class, 'publish'])->name('exams.report-cards.publish');
        Route::post('report-cards/comment', [ReportCardController::class, 'comment'])->name('exams.report-cards.comment');
        Route::get('report-cards/{reportCard}/download', [ReportCardController::class, 'download'])->name('exams.report-cards.download');
        Route::get('transcript', [ReportCardController::class, 'transcript'])->name('exams.transcript');

        Route::get('awards/export', [AwardController::class, 'export'])->name('exams.awards.export');
        Route::get('awards', [AwardController::class, 'index'])->name('exams.awards.index');
        Route::post('awards', [AwardController::class, 'store'])->name('exams.awards.store');
        Route::post('awards/issue', [AwardController::class, 'issue'])->name('exams.awards.issue');
        Route::get('awards/id-card', [AwardController::class, 'idCard'])->name('exams.awards.id-card');
        Route::get('awards/transfer', [AwardController::class, 'transfer'])->name('exams.awards.transfer');
        Route::get('awards/{award}/download', [AwardController::class, 'download'])->name('exams.awards.download');
    });

    Route::prefix('finance')->middleware(['role:super_admin|admin|headmaster'])->group(function () {
        Route::get('fee-items/export', [FeeItemController::class, 'export'])->name('finance.fee-items.export');
        Route::get('fee-items', [FeeItemController::class, 'index'])->name('finance.fee-items.index');
        Route::post('fee-items', [FeeItemController::class, 'store'])->name('finance.fee-items.store');
        Route::put('fee-items/{feeItem}', [FeeItemController::class, 'update'])->name('finance.fee-items.update');
        Route::get('fee-structures/export', [FeeStructureController::class, 'export'])->name('finance.fee-structures.export');
        Route::post('fee-structures/copy-last-year', [FeeStructureController::class, 'copyLastYear'])->name('finance.fee-structures.copy-last-year');
        Route::get('fee-structures', [FeeStructureController::class, 'index'])->name('finance.fee-structures.index');
        Route::post('fee-structures', [FeeStructureController::class, 'store'])->name('finance.fee-structures.store');
        Route::put('fee-structures/{feeStructure}', [FeeStructureController::class, 'update'])->name('finance.fee-structures.update');
        Route::get('invoices/export', [InvoiceController::class, 'export'])->name('finance.invoices.export');
        Route::post('invoices/generate', [InvoiceController::class, 'generate'])->name('finance.invoices.generate');
        Route::post('invoices/issue', [InvoiceController::class, 'issue'])->name('finance.invoices.issue');
        Route::get('invoices', [InvoiceController::class, 'index'])->name('finance.invoices.index');
        Route::get('arrears/export', [ArrearsController::class, 'export'])->name('finance.arrears.export');
        Route::get('arrears', [ArrearsController::class, 'index'])->name('finance.arrears.index');
        Route::get('payment-plans/export', [PaymentPlanController::class, 'export'])->name('finance.payment-plans.export');
        Route::get('payment-plans', [PaymentPlanController::class, 'index'])->name('finance.payment-plans.index');
        Route::post('payment-plans', [PaymentPlanController::class, 'store'])->name('finance.payment-plans.store');
        Route::get('adjustments/export', [FeeAdjustmentController::class, 'export'])->name('finance.adjustments.export');
        Route::get('adjustments', [FeeAdjustmentController::class, 'index'])->name('finance.adjustments.index');
        Route::post('adjustments', [FeeAdjustmentController::class, 'store'])->name('finance.adjustments.store');
        Route::get('receipts/manual', [ManualReceiptController::class, 'index'])->name('finance.receipts.manual');
        Route::post('receipts/manual', [ManualReceiptController::class, 'store'])->name('finance.receipts.store');
        Route::get('collections/export', [CollectionsController::class, 'export'])->name('finance.collections.export');
        Route::get('collections', [CollectionsController::class, 'index'])->name('finance.collections.index');
        Route::get('reconciliation/export', [ReconciliationController::class, 'export'])->name('finance.reconciliation.export');
        Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('finance.reconciliation.index');
    });

    Route::prefix('catalog')->middleware(['role:super_admin|admin|headmaster'])->group(function () {
        Route::get('glossary/export', [GlossaryController::class, 'export'])->name('catalog.glossary.export');
        Route::get('glossary', [GlossaryController::class, 'index'])->name('catalog.glossary.index');
        Route::post('glossary', [GlossaryController::class, 'store'])->name('catalog.glossary.store');
        Route::put('glossary/{glossaryItem}', [GlossaryController::class, 'update'])->name('catalog.glossary.update')->whereNumber('glossaryItem');
        Route::delete('glossary/{glossaryItem}', [GlossaryController::class, 'destroy'])->name('catalog.glossary.destroy')->whereNumber('glossaryItem');
        Route::get('certificates/export', [CourseCertificateController::class, 'export'])->name('catalog.certificates.export');
        Route::get('certificates', [CourseCertificateController::class, 'index'])->name('catalog.certificates.index');
        Route::post('certificates', [CourseCertificateController::class, 'store'])->name('catalog.certificates.store');
        Route::post('certificates/issue', [CourseCertificateController::class, 'issue'])->name('catalog.certificates.issue');
        Route::put('certificates/{template}', [CourseCertificateController::class, 'update'])->name('catalog.certificates.update')->whereNumber('template');
        Route::get('certificates/{certificate}/download', [CourseCertificateController::class, 'download'])->name('catalog.certificates.download')->whereNumber('certificate');
        Route::post('certificates/{certificate}/revoke', [CourseCertificateController::class, 'revoke'])->name('catalog.certificates.revoke')->whereNumber('certificate');
        Route::get('reports/completions/export', [CourseCompletionReportController::class, 'export'])->name('catalog.reports.completions.export');
        Route::get('reports/completions', [CourseCompletionReportController::class, 'index'])->name('catalog.reports.completions');
        Route::get('subjects/export', [CourseSubjectController::class, 'export'])->name('catalog.subjects.export');
        Route::get('subjects', [CourseSubjectController::class, 'index'])->name('catalog.subjects.index');
        Route::post('subjects', [CourseSubjectController::class, 'store'])->name('catalog.subjects.store');
        Route::put('subjects/{subject}', [CourseSubjectController::class, 'update'])->name('catalog.subjects.update');
        Route::get('audiences/export', [AudienceController::class, 'export'])->name('catalog.audiences.export');
        Route::get('audiences', [AudienceController::class, 'index'])->name('catalog.audiences.index');
        Route::post('audiences', [AudienceController::class, 'store'])->name('catalog.audiences.store');
        Route::put('audiences/{audience}', [AudienceController::class, 'update'])->name('catalog.audiences.update');
        Route::get('levels/export', [CourseLevelController::class, 'export'])->name('catalog.levels.export');
        Route::get('levels', [CourseLevelController::class, 'index'])->name('catalog.levels.index');
        Route::post('levels', [CourseLevelController::class, 'store'])->name('catalog.levels.store');
        Route::put('levels/{level}', [CourseLevelController::class, 'update'])->name('catalog.levels.update');
        Route::get('i18n-preview', [I18nPreviewController::class, 'show'])->name('catalog.i18n.preview');
        Route::get('offerings/export', [CourseOfferingController::class, 'export'])->name('catalog.offerings.export');
        Route::get('offerings', [CourseOfferingController::class, 'index'])->name('catalog.offerings.index');
        Route::post('offerings', [CourseOfferingController::class, 'store'])->name('catalog.offerings.store');
        Route::put('offerings/{offering}', [CourseOfferingController::class, 'update'])->name('catalog.offerings.update')->whereNumber('offering');
        Route::post('offerings/{offering}/pin', [CourseOfferingController::class, 'pin'])->name('catalog.offerings.pin')->whereNumber('offering');
        Route::get('offerings/{offering}/sessions/export', [OfferingSessionController::class, 'export'])->name('catalog.offerings.sessions.export')->whereNumber('offering');
        Route::get('offerings/{offering}/sessions', [OfferingSessionController::class, 'index'])->name('catalog.offerings.sessions.index')->whereNumber('offering');
        Route::post('offerings/{offering}/sessions', [OfferingSessionController::class, 'store'])->name('catalog.offerings.sessions.store')->whereNumber('offering');
        Route::put('offerings/{offering}/sessions/{session}', [OfferingSessionController::class, 'update'])->name('catalog.offerings.sessions.update')->whereNumber('offering')->whereNumber('session');
        Route::get('offerings/{offering}/sessions/{session}/attendance', [OfferingSessionController::class, 'attendance'])->name('catalog.offerings.sessions.attendance')->whereNumber('offering')->whereNumber('session');
        Route::post('offerings/{offering}/sessions/{session}/attendance', [OfferingSessionController::class, 'mark'])->name('catalog.offerings.sessions.attendance.mark')->whereNumber('offering')->whereNumber('session');
        Route::post('offerings/{offering}/sessions/{session}/attendance/bulk', [OfferingSessionController::class, 'bulk'])->name('catalog.offerings.sessions.attendance.bulk')->whereNumber('offering')->whereNumber('session');
        Route::post('offerings/{offering}/halaqa', [OfferingSessionController::class, 'storeHalaqa'])->name('catalog.offerings.halaqa.store')->whereNumber('offering');
        Route::post('offerings/{offering}/halaqa/sync', [OfferingSessionController::class, 'syncHalaqa'])->name('catalog.offerings.halaqa.sync')->whereNumber('offering');
        Route::post('offerings/{offering}/sessions/{session}/halaqa', [OfferingSessionController::class, 'storeHalaqaSession'])->name('catalog.offerings.sessions.halaqa.store')->whereNumber('offering')->whereNumber('session');
        Route::get('quran/export', [CatalogQuranReferenceController::class, 'export'])->name('catalog.quran.export');
        Route::get('quran', [CatalogQuranReferenceController::class, 'index'])->name('catalog.quran.index');
        Route::get('arabic/reports', [CatalogArabicReportController::class, 'index'])->name('catalog.arabic.reports');
        Route::get('arabic/export', [CatalogArabicReferenceController::class, 'export'])->name('catalog.arabic.export');
        Route::get('arabic', [CatalogArabicReferenceController::class, 'index'])->name('catalog.arabic.index');
        Route::post('arabic/letters', [CatalogArabicReferenceController::class, 'storeLetter'])->name('catalog.arabic.letters.store');
        Route::put('arabic/letters/{letter}', [CatalogArabicReferenceController::class, 'updateLetter'])->name('catalog.arabic.letters.update')->whereNumber('letter');
        Route::post('arabic/harakas', [CatalogArabicReferenceController::class, 'storeHarakah'])->name('catalog.arabic.harakas.store');
        Route::put('arabic/harakas/{harakah}', [CatalogArabicReferenceController::class, 'updateHarakah'])->name('catalog.arabic.harakas.update')->whereNumber('harakah');
        Route::get('reviews/export', [CatalogReviewController::class, 'export'])->name('catalog.reviews.export');
        Route::get('reviews', [CatalogReviewController::class, 'index'])->name('catalog.reviews.index');
        Route::post('reviews', [CatalogReviewController::class, 'store'])->name('catalog.reviews.store');
        Route::get('questions/export', [CatalogQuestionController::class, 'export'])->name('catalog.questions.export');
        Route::get('questions', [CatalogQuestionController::class, 'index'])->name('catalog.questions.index');
        Route::post('questions', [CatalogQuestionController::class, 'store'])->name('catalog.questions.store');
        Route::put('questions/{question}', [CatalogQuestionController::class, 'update'])->name('catalog.questions.update')->whereNumber('question');
        Route::get('courses/export', [EngineCourseController::class, 'export'])->name('catalog.courses.export');
        Route::get('courses', [EngineCourseController::class, 'index'])->name('catalog.courses.index');
        Route::post('courses', [EngineCourseController::class, 'store'])->name('catalog.courses.store');
        Route::put('courses/{course}', [EngineCourseController::class, 'update'])->name('catalog.courses.update')->whereNumber('course');
        Route::post('courses/{course}/transition', [EngineCourseController::class, 'transition'])->name('catalog.courses.transition')->whereNumber('course');
        Route::get('courses/{course}/assessments/export', [CatalogAssessmentController::class, 'export'])->name('catalog.courses.assessments.export')->whereNumber('course');
        Route::get('courses/{course}/assessments', [CatalogAssessmentController::class, 'index'])->name('catalog.courses.assessments.index')->whereNumber('course');
        Route::post('courses/{course}/assessments', [CatalogAssessmentController::class, 'store'])->name('catalog.courses.assessments.store')->whereNumber('course');
        Route::put('courses/{course}/assessments/{assessment}', [CatalogAssessmentController::class, 'update'])->name('catalog.courses.assessments.update')->whereNumber('course')->whereNumber('assessment');
        Route::post('courses/{course}/assessments/{assessment}/questions', [CatalogAssessmentController::class, 'attach'])->name('catalog.courses.assessments.questions.attach')->whereNumber('course')->whereNumber('assessment');
        Route::delete('courses/{course}/assessments/{assessment}/questions/{question}', [CatalogAssessmentController::class, 'detach'])->name('catalog.courses.assessments.questions.detach')->whereNumber('course')->whereNumber('assessment')->whereNumber('question');
        Route::get('courses/{course}/activities/export', [CatalogActivityController::class, 'export'])->name('catalog.courses.activities.export')->whereNumber('course');
        Route::get('courses/{course}/activities', [CatalogActivityController::class, 'index'])->name('catalog.courses.activities.index')->whereNumber('course');
        Route::post('courses/{course}/activities', [CatalogActivityController::class, 'store'])->name('catalog.courses.activities.store')->whereNumber('course');
        Route::put('courses/{course}/activities/{activity}', [CatalogActivityController::class, 'update'])->name('catalog.courses.activities.update')->whereNumber('course')->whereNumber('activity');
        Route::delete('courses/{course}/activities/{activity}', [CatalogActivityController::class, 'destroy'])->name('catalog.courses.activities.destroy')->whereNumber('course')->whereNumber('activity');
        Route::get('courses/{course}/outline', [CourseOutlineController::class, 'show'])->name('catalog.courses.outline')->whereNumber('course');
        Route::post('courses/{course}/modules', [CourseOutlineController::class, 'storeModule'])->name('catalog.courses.modules.store')->whereNumber('course');
        Route::post('courses/{course}/lessons', [CourseOutlineController::class, 'storeLesson'])->name('catalog.courses.lessons.store')->whereNumber('course');
        Route::post('courses/{course}/blocks', [CourseOutlineController::class, 'storeBlock'])->name('catalog.courses.blocks.store')->whereNumber('course');
        Route::post('courses/{course}/blocks/reorder', [CourseOutlineController::class, 'reorderBlocks'])->name('catalog.courses.blocks.reorder')->whereNumber('course');
        Route::delete('courses/{course}/blocks/{block}', [CourseOutlineController::class, 'destroyBlock'])->name('catalog.courses.blocks.destroy')->whereNumber('course');
        Route::post('courses/{course}/lessons/{lesson}/publish', [CourseOutlineController::class, 'publishLesson'])->name('catalog.courses.lessons.publish')->whereNumber('course');
        Route::post('courses/{course}/lessons/{lesson}/preview', [CourseOutlineController::class, 'togglePreview'])->name('catalog.courses.lessons.preview')->whereNumber('course');
        Route::post('courses/{course}/lessons/{lesson}/glossary', [CourseOutlineController::class, 'attachGlossary'])->name('catalog.courses.lessons.glossary.attach')->whereNumber('course');
        Route::delete('courses/{course}/lessons/{lesson}/glossary/{glossaryItem}', [CourseOutlineController::class, 'detachGlossary'])->name('catalog.courses.lessons.glossary.detach')->whereNumber('course')->whereNumber('glossaryItem');
        Route::get('player/{lesson}', [LessonPlayerController::class, 'show'])->name('catalog.player.show')->whereNumber('lesson');
        Route::get('media/{media}', [CatalogMediaController::class, 'show'])->name('catalog.media.show')->whereNumber('media');
    });

    Route::prefix('hr')->middleware(['role:super_admin|admin|headmaster'])->group(function () {
        Route::get('attendance/export', [StaffAttendanceController::class, 'export'])->name('hr.attendance.export');
        Route::post('attendance/import', [StaffAttendanceController::class, 'import'])->name('hr.attendance.import');
        Route::post('attendance/holidays', [StaffAttendanceController::class, 'fillHolidays'])->name('hr.attendance.holidays');
        Route::get('attendance/reports/export', [StaffAttendanceReportController::class, 'export'])->name('hr.attendance.reports.export');
        Route::get('attendance/reports', [StaffAttendanceReportController::class, 'index'])->name('hr.attendance.reports');
        Route::get('attendance', [StaffAttendanceController::class, 'index'])->name('hr.attendance.index');
        Route::post('attendance', [StaffAttendanceController::class, 'store'])->name('hr.attendance.store');
        Route::get('leave-types/export', [LeaveTypeController::class, 'export'])->name('hr.leave-types.export');
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('hr.leave-types.index');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('hr.leave-types.store');
        Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('hr.leave-types.update');
        Route::get('leave-balances/export', [LeaveBalanceController::class, 'export'])->name('hr.leave-balances.export');
        Route::post('leave-balances/carry-over', [LeaveBalanceController::class, 'carryOver'])->name('hr.leave-balances.carry-over');
        Route::post('leave-balances/{entitlement}/adjust', [LeaveBalanceController::class, 'adjust'])->name('hr.leave-balances.adjust');
        Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('hr.leave-balances.index');
        Route::post('leave-balances', [LeaveBalanceController::class, 'store'])->name('hr.leave-balances.store');
        Route::get('contracts/export', [StaffContractController::class, 'export'])->name('hr.contracts.export');
        Route::get('contracts', [StaffContractController::class, 'index'])->name('hr.contracts.index');
        Route::post('contracts', [StaffContractController::class, 'store'])->name('hr.contracts.store');
        Route::get('compliance/export', [ComplianceController::class, 'export'])->name('hr.compliance.export');
        Route::post('compliance/notify', [ComplianceController::class, 'notify'])->name('hr.compliance.notify');
        Route::get('compliance', [ComplianceController::class, 'index'])->name('hr.compliance.index');
        Route::get('postings/export', [JobPostingController::class, 'export'])->name('hr.postings.export');
        Route::get('postings', [JobPostingController::class, 'index'])->name('hr.postings.index');
        Route::post('postings', [JobPostingController::class, 'store'])->name('hr.postings.store');
        Route::put('postings/{jobPosting}', [JobPostingController::class, 'update'])->name('hr.postings.update');
        Route::get('applications/export', [JobApplicationController::class, 'export'])->name('hr.applications.export');
        Route::post('applications/{application}/hire', [JobApplicationController::class, 'hire'])->name('hr.applications.hire');
        Route::get('applications', [JobApplicationController::class, 'index'])->name('hr.applications.index');
        Route::post('applications', [JobApplicationController::class, 'store'])->name('hr.applications.store');
        Route::get('onboarding/export', [OnboardingController::class, 'export'])->name('hr.onboarding.export');
        Route::post('onboarding/seed', [OnboardingController::class, 'seed'])->name('hr.onboarding.seed');
        Route::post('onboarding/{item}/toggle', [OnboardingController::class, 'toggle'])->name('hr.onboarding.toggle');
        Route::get('onboarding', [OnboardingController::class, 'index'])->name('hr.onboarding.index');
        Route::get('appraisals/export', [AppraisalController::class, 'export'])->name('hr.appraisals.export');
        Route::post('appraisals/cycles', [AppraisalController::class, 'storeCycle'])->name('hr.appraisals.cycles.store');
        Route::get('appraisals', [AppraisalController::class, 'index'])->name('hr.appraisals.index');
        Route::post('appraisals', [AppraisalController::class, 'store'])->name('hr.appraisals.store');
        Route::get('observations/export', [LessonObservationController::class, 'export'])->name('hr.observations.export');
        Route::get('observations', [LessonObservationController::class, 'index'])->name('hr.observations.index');
        Route::post('observations', [LessonObservationController::class, 'store'])->name('hr.observations.store');
        Route::get('cpd/export', [CpdRecordController::class, 'export'])->name('hr.cpd.export');
        Route::get('cpd', [CpdRecordController::class, 'index'])->name('hr.cpd.index');
        Route::post('cpd', [CpdRecordController::class, 'store'])->name('hr.cpd.store');
        Route::get('payroll/{payrollPeriod}/export', [PayrollPeriodController::class, 'export'])->name('hr.payroll.export');
        Route::post('payroll/run', [PayrollPeriodController::class, 'run'])->name('hr.payroll.run');
        Route::post('payroll/{payrollPeriod}/approve', [PayrollPeriodController::class, 'approve'])->name('hr.payroll.approve');
        Route::post('payroll/{payrollPeriod}/pay', [PayrollPeriodController::class, 'pay'])->name('hr.payroll.pay');
        Route::post('payroll/{payrollPeriod}/lock', [PayrollPeriodController::class, 'lock'])->name('hr.payroll.lock');
        Route::get('payroll', [PayrollPeriodController::class, 'index'])->name('hr.payroll.index');
    });
});
