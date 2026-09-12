<?php

use App\Domains\Hifz\Http\Controllers\DeanHifzDashboardController;
use App\Domains\Hifz\Http\Controllers\HifzEnrollmentController;
use App\Domains\Hifz\Http\Controllers\HifzHubController;
use App\Domains\Hifz\Http\Controllers\HifzMilestoneController;
use App\Domains\Hifz\Http\Controllers\HifzProgramController;
use App\Domains\Hifz\Http\Controllers\HifzReportController;
use App\Domains\Hifz\Http\Controllers\ParentHifzDashboardController;
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

    // `HifzProgramController` implements every resource action except `destroy`,
    // so the DELETE route `Route::resource` registered for it answered 500.
    // Removing an unroutable route is a route change, which rule 7's freeze
    // permits; no Hifz behaviour moves.
    Route::resource('programs', HifzProgramController::class)
        ->except('destroy')
        ->parameters(['programs' => 'program']);
    Route::post('programs/{program}/assign-supervisor', [HifzProgramController::class, 'assignSupervisor'])->name('programs.assign-supervisor');

    Route::get('programs/{program}/enrollments', [HifzEnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('programs/{program}/enrollments/create', [HifzEnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('programs/{program}/enrollments', [HifzEnrollmentController::class, 'store'])->name('enrollments.store');

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
});
