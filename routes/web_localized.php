<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
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

// Authentication routes (using Breeze)
require __DIR__.'/auth.php';

// Language switching
Route::get('/locale/{locale}', [LocaleController::class, 'setLocale'])->name('locale');

// Dashboard routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Student routes
    Route::resource('students', StudentController::class);
    Route::get('/students/{student}/quran-progress', [StudentController::class, 'quranProgress'])->name('students.quran-progress');
    
    // Teacher routes
    Route::resource('teachers', TeacherController::class);
    
    // Quran Progress routes
    Route::resource('quran-progress', QuranProgressController::class);
    Route::post('/quran-progress/{student}/update', [QuranProgressController::class, 'updateProgress'])->name('quran-progress.update');
    
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
    
    // Substitution routes (for admin, headmaster, supervisor, teacher roles)
    Route::middleware(['role:admin|headmaster|supervisor|teacher'])->group(function() {
        Route::resource('substitutions/absences', TeacherAbsenceController::class)->except(['show']);
        Route::resource('substitutions/requests', SubstitutionRequestController::class);
        Route::post('substitutions/requests/{request}/take', [SubstitutionRequestController::class, 'take'])->name('substitutions.requests.take');
        Route::post('substitutions/requests/{request}/assign', [SubstitutionRequestController::class, 'assign'])->name('substitutions.requests.assign');
    });
    
    // Admin CMS routes (for admin, headmaster, supervisor roles)
    Route::prefix('admin/public-site')->middleware(['role:admin|headmaster|supervisor'])->group(function() {
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
