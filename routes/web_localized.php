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
use App\Domains\Portal\Http\Controllers\PortalBehaviorController;
use App\Domains\Portal\Http\Controllers\PortalHolidayController;
use App\Domains\Portal\Http\Controllers\PortalInvoiceController;
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
    Route::get('/portal/invoices', [PortalInvoiceController::class, 'index'])->name('portal.invoices');
    Route::post('/portal/invoices/{invoice}/pay', [PortalInvoiceController::class, 'pay'])->name('portal.invoices.pay');
    Route::get('/finance/receipts/{receipt}/document', [ReceiptDocumentController::class, 'show'])->name('finance.receipts.show')->whereNumber('receipt');

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
});
