<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnhancedDashboardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\QuranProgressController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ELearningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Substitutions\TeacherAbsenceController;
use App\Http\Controllers\Substitutions\SubstitutionRequestController;
use App\Http\Controllers\Admin\PublicSite\PageController as AdminPageController;
use App\Http\Controllers\Admin\PublicSite\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Admin\InstructorController as AdminInstructorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnalyticsController;

// Authentication routes (using Breeze)
require __DIR__.'/auth.php';

// Language switching
Route::get('/locale/{locale}', [LocaleController::class, 'setLocale'])->name('locale');

// Dashboard routes
Route::middleware(['auth', 'trackActivity'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/enhanced-dashboard', [EnhancedDashboardController::class, 'index'])->name('enhanced.dashboard');
    
    // Student routes
    Route::resource('students', StudentController::class);
    Route::get('/students/{student}/quran-progress', [StudentController::class, 'quranProgress'])->name('students.quran-progress');
    
    // Teacher routes
    Route::resource('teachers', TeacherController::class);
    
    // Quran Progress routes
    Route::resource('quran-progress', QuranProgressController::class);
    Route::post('/quran-progress/{student}/update', [QuranProgressController::class, 'updateProgress'])->name('quran-progress.update-progress');
    
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
    Route::middleware(['role:admin|super_admin'])->group(function() {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/reports', [AnalyticsController::class, 'reports'])->name('analytics.reports');
        Route::post('/analytics/reports/generate', [AnalyticsController::class, 'generateReport'])->name('analytics.reports.generate');
        Route::get('/analytics/reports/{id}/download', [AnalyticsController::class, 'downloadReport'])->name('analytics.reports.download');
        Route::delete('/analytics/reports/{id}', [AnalyticsController::class, 'deleteReport'])->name('analytics.reports.delete');
    });
    
    // Substitution routes
    Route::middleware(['role:super_admin|admin|headmaster|supervisor|teacher'])->group(function() {
        Route::resource('substitutions/absences', TeacherAbsenceController::class)->except(['show']);
        Route::resource('substitutions/requests', SubstitutionRequestController::class)->names('substitutions.requests');
        Route::post('substitutions/requests/{request}/take', [SubstitutionRequestController::class, 'take'])->name('substitutions.requests.take');
        Route::post('substitutions/requests/{request}/assign', [SubstitutionRequestController::class, 'assign'])->name('substitutions.requests.assign');
    });
    
    // Admin user management (super_admin only)
    Route::prefix('admin/users')->middleware(['role:super_admin'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminUserController::class, 'index'])->name('admin.users.index');
        Route::delete('/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });

    // Admin enrollment management
    Route::prefix('admin/enrollments')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function() {
        Route::get('/', [AdminEnrollmentController::class, 'index'])->name('admin.enrollments.index');
        Route::get('/export', [AdminEnrollmentController::class, 'export'])->name('admin.enrollments.export');
        Route::get('/payments', [AdminEnrollmentController::class, 'payments'])->name('admin.enrollments.payments');
        Route::get('/{enrollment}', [AdminEnrollmentController::class, 'show'])->name('admin.enrollments.show');
        Route::patch('/{enrollment}/activate', [AdminEnrollmentController::class, 'activate'])->name('admin.enrollments.activate');
        Route::patch('/{enrollment}/reject', [AdminEnrollmentController::class, 'reject'])->name('admin.enrollments.reject');
    });

    // Instructor management
    Route::prefix('admin/instructors')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function() {
        Route::get('/', [AdminInstructorController::class, 'index'])->name('admin.instructors.index');
        Route::get('/create', [AdminInstructorController::class, 'create'])->name('admin.instructors.create');
        Route::post('/', [AdminInstructorController::class, 'store'])->name('admin.instructors.store');
        Route::get('/{instructor}/edit', [AdminInstructorController::class, 'edit'])->name('admin.instructors.edit');
        Route::put('/{instructor}', [AdminInstructorController::class, 'update'])->name('admin.instructors.update');
        Route::delete('/{instructor}', [AdminInstructorController::class, 'destroy'])->name('admin.instructors.destroy');
    });

    // Admin CMS routes
    Route::prefix('admin/public-site')->middleware(['role:super_admin|admin|headmaster|supervisor'])->group(function() {
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
});
