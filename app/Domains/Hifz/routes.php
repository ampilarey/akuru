<?php

use App\Domains\Hifz\Http\Controllers\DeanHifzDashboardController;
use App\Domains\Hifz\Http\Controllers\HifzEnrollmentController;
use App\Domains\Hifz\Http\Controllers\HifzHubController;
use App\Domains\Hifz\Http\Controllers\HifzMilestoneController;
use App\Domains\Hifz\Http\Controllers\HifzMistakeController;
use App\Domains\Hifz\Http\Controllers\HifzProgramController;
use App\Domains\Hifz\Http\Controllers\HifzReportController;
use App\Domains\Hifz\Http\Controllers\HifzSessionController;
use App\Domains\Hifz\Http\Controllers\HifzSessionRecordController;
use App\Domains\Hifz\Http\Controllers\ParentHifzDashboardController;
use App\Domains\Hifz\Http\Controllers\QuranMushafController;
use App\Domains\Hifz\Http\Controllers\QuranPageController;
use App\Domains\Hifz\Http\Controllers\StudentHifzDashboardController;
use App\Domains\Hifz\Http\Controllers\SupervisorHifzDashboardController;
use App\Domains\Hifz\Http\Controllers\TeacherHifzDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('hifz')->name('hifz.')->middleware(['auth', 'trackActivity'])->group(function () {
    Route::get('/', [HifzHubController::class, 'index'])->name('hub');

    Route::get('/teacher', [TeacherHifzDashboardController::class, 'index'])->name('teacher.dashboard');
    Route::get('/supervisor', [SupervisorHifzDashboardController::class, 'index'])->name('supervisor.dashboard');
    Route::get('/dean', [DeanHifzDashboardController::class, 'index'])->name('dean.dashboard');
    Route::get('/student', [StudentHifzDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/parent', [ParentHifzDashboardController::class, 'index'])->name('parent.dashboard');

    Route::resource('programs', HifzProgramController::class)->parameters(['programs' => 'program']);
    Route::post('programs/{program}/assign-supervisor', [HifzProgramController::class, 'assignSupervisor'])->name('programs.assign-supervisor');

    Route::get('programs/{program}/enrollments', [HifzEnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('programs/{program}/enrollments/create', [HifzEnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('programs/{program}/enrollments', [HifzEnrollmentController::class, 'store'])->name('enrollments.store');

    Route::get('sessions', [HifzSessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions/create', [HifzSessionController::class, 'create'])->name('sessions.create');
    Route::get('sessions/{session}/edit', [HifzSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('sessions/{session}', [HifzSessionController::class, 'update'])->name('sessions.update');
    Route::post('sessions/{session}/records', [HifzSessionController::class, 'bulkUpdateRecords'])->name('sessions.records.bulk');
    Route::post('sessions/{session}/review', [HifzSessionController::class, 'review'])->name('sessions.review');

    Route::put('session-records/{record}', [HifzSessionRecordController::class, 'update'])->name('session-records.update');
    Route::post('session-records/{record}/review', [HifzSessionRecordController::class, 'review'])->name('session-records.review');
    Route::get('session-records/{record}/quran-page', [HifzSessionRecordController::class, 'quranPage'])->name('session-records.quran-page');

    Route::get('students/{student}/history', [HifzSessionRecordController::class, 'history'])->name('students.history');

    Route::post('mistakes', [HifzMistakeController::class, 'store'])->name('mistakes.store');
    Route::delete('mistakes/{mistake}', [HifzMistakeController::class, 'destroy'])->name('mistakes.destroy');

    Route::get('milestones', [HifzMilestoneController::class, 'index'])->name('milestones.index');
    Route::post('milestones', [HifzMilestoneController::class, 'store'])->name('milestones.store');
    Route::post('milestones/{milestone}/supervisor-review', [HifzMilestoneController::class, 'supervisorReview'])->name('milestones.supervisor-review');
    Route::post('milestones/{milestone}/approve', [HifzMilestoneController::class, 'approve'])->name('milestones.approve');
    Route::post('milestones/{milestone}/reject', [HifzMilestoneController::class, 'reject'])->name('milestones.reject');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [HifzReportController::class, 'index'])->name('index');
        Route::get('/weak-students', [HifzReportController::class, 'weakStudents'])->name('weak-students');
        Route::get('/haraka-mistakes', [HifzReportController::class, 'harakaMistakes'])->name('haraka-mistakes');
        Route::get('/parent-follow-up', [HifzReportController::class, 'parentFollowUp'])->name('parent-follow-up');
        Route::get('/teacher-completion', [HifzReportController::class, 'teacherCompletion'])->name('teacher-completion');
        Route::get('/milestones', [HifzReportController::class, 'milestones'])->name('milestones');
        Route::get('/export', [HifzReportController::class, 'export'])->name('export');
    });

    Route::prefix('quran')->name('quran.')->group(function () {
        Route::resource('mushafs', QuranMushafController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('mushafs/{mushaf}/approve', [QuranMushafController::class, 'approve'])->name('mushafs.approve');
        Route::post('mushafs/{mushaf}/lock', [QuranMushafController::class, 'lock'])->name('mushafs.lock');
        Route::post('mushafs/{mushaf}/import-ayah', [QuranMushafController::class, 'importAyah'])->name('mushafs.import-ayah');
        Route::get('mushafs/{mushaf}/pages/{pageNumber}', [QuranPageController::class, 'show'])->name('pages.show');
        Route::get('mushafs/{mushaf}/words', [QuranPageController::class, 'words'])->name('words.index');
        Route::post('mushafs/{mushaf}/pages/{page}/positions', [QuranPageController::class, 'storePosition'])->name('pages.positions.store');
    });
});
