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
use App\Domains\Hifz\Http\Controllers\QuranProgressController;
use App\Domains\HR\Http\Controllers\InstructorController as AdminInstructorController;
use App\Domains\Identity\Http\Controllers\ProfileController;
use App\Domains\Notifications\Http\Controllers\NotificationController;
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
use App\Domains\Portal\Http\Controllers\PortalAttendanceController;
use App\Domains\Portal\Http\Controllers\PortalAwardController;
use App\Domains\Portal\Http\Controllers\PortalBehaviorController;
use App\Domains\Portal\Http\Controllers\PortalExamController;
use App\Domains\Portal\Http\Controllers\PortalHolidayController;
use App\Domains\Portal\Http\Controllers\PortalReportCardController;
use App\Domains\Settings\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Domains\Settings\Http\Controllers\AnalyticsController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\CourseController as AdminCourseController;
use App\Domains\Website\Http\Controllers\Admin\PublicSite\PageController as AdminPageController;
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
    Route::get('/portal/children', [GuardianChildrenController::class, 'index'])->name('portal.children');
    Route::get('/portal/holidays', [PortalHolidayController::class, 'index'])->name('portal.holidays');
    Route::get('/portal/attendance', [PortalAttendanceController::class, 'index'])->name('portal.attendance');
    Route::get('/portal/behavior', [PortalBehaviorController::class, 'index'])->name('portal.behavior');
    Route::get('/portal/absence-notes', [PortalAbsenceNoteController::class, 'index'])->name('portal.absence-notes');
    Route::post('/portal/absence-notes', [PortalAbsenceNoteController::class, 'store'])->name('portal.absence-notes.store');
    Route::get('/portal/exams', [PortalExamController::class, 'index'])->name('portal.exams');
    Route::get('/portal/report-cards', [PortalReportCardController::class, 'index'])->name('portal.report-cards');
    Route::get('/portal/report-cards/{reportCard}/download', [PortalReportCardController::class, 'download'])->name('portal.report-cards.download');
    Route::get('/portal/transcript', [PortalReportCardController::class, 'transcript'])->name('portal.transcript');
    Route::get('/portal/awards', [PortalAwardController::class, 'index'])->name('portal.awards');

    Route::get('exams/{exam}/marks/export', [ExamMarkController::class, 'export'])->name('exams.marks.export');
    Route::get('exams/{exam}/marks', [ExamMarkController::class, 'show'])->name('exams.marks.show');
    Route::put('exams/{exam}/marks', [ExamMarkController::class, 'update'])->name('exams.marks.update');
    Route::post('exams/{exam}/marks/import', [ExamMarkController::class, 'import'])->name('exams.marks.import');

    Route::get('exams/gradebook/export', [GradebookController::class, 'export'])->name('exams.gradebook.export');
    Route::get('exams/gradebook', [GradebookController::class, 'index'])->name('exams.gradebook.index');
    Route::post('exams/gradebook/compute', [GradebookController::class, 'compute'])->name('exams.gradebook.compute');
    Route::post('exams/competencies/assess', [CompetencyController::class, 'assess'])->name('exams.competencies.assess');

    Route::get('academics/registers/today', [TeacherRegisterController::class, 'today'])->name('academics.registers.today');
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
    });

    Route::prefix('people')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function () {
        Route::get('students/export', [StudentDirectoryController::class, 'export'])->name('people.students.export');
        Route::get('students', [StudentDirectoryController::class, 'index'])->name('people.students.index');
        Route::get('students/{student}', [StudentDirectoryController::class, 'show'])->name('people.students.show');
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
        Route::post('classes/{classRoom}/assign', [ClassDirectoryController::class, 'assign'])->name('academics.classes.assign');

        Route::get('rooms/export', [RoomDirectoryController::class, 'export'])->name('academics.rooms.export');
        Route::get('rooms', [RoomDirectoryController::class, 'index'])->name('academics.rooms.index');
        Route::post('rooms', [RoomDirectoryController::class, 'store'])->name('academics.rooms.store');
        Route::put('rooms/{room}', [RoomDirectoryController::class, 'update'])->name('academics.rooms.update');

        Route::get('bookings/export', [RoomBookingController::class, 'export'])->name('academics.bookings.export');
        Route::get('bookings', [RoomBookingController::class, 'index'])->name('academics.bookings.index');
        Route::post('bookings', [RoomBookingController::class, 'store'])->name('academics.bookings.store');
        Route::put('bookings/{roomBooking}', [RoomBookingController::class, 'update'])->name('academics.bookings.update');
        Route::delete('bookings/{roomBooking}', [RoomBookingController::class, 'destroy'])->name('academics.bookings.destroy');

        Route::get('calendar/export', [CalendarDayController::class, 'export'])->name('academics.calendar.export');
        Route::get('calendar', [CalendarDayController::class, 'index'])->name('academics.calendar.index');
        Route::post('calendar', [CalendarDayController::class, 'store'])->name('academics.calendar.store');
        Route::put('calendar/{calendarDay}', [CalendarDayController::class, 'update'])->name('academics.calendar.update');
        Route::delete('calendar/{calendarDay}', [CalendarDayController::class, 'destroy'])->name('academics.calendar.destroy');

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
});
