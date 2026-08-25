<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListArabicSkillReportAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnArabicReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);
        $student = app(ResolveStudentForUserAction::class)->execute((int) $request->user()->id);
        $enrollmentId = $student
            ? CourseEnrollment::query()
                ->where('unified_student_id', $student['id'])
                ->whereIn('status', ['active', 'approved', 'completed'])
                ->orderByDesc('enrolled_at')
                ->value('id')
            : null;

        return Inertia::render('Courses/Learn/ArabicReport', app(ListArabicSkillReportAction::class)->execute(
            null,
            $enrollmentId ? (int) $enrollmentId : -1,
        ) + ['student' => $student]);
    }
}
