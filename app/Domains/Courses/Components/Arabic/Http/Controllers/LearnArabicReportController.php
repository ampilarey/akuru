<?php

namespace App\Domains\Courses\Components\Arabic\Http\Controllers;

use App\Domains\Courses\Actions\ListLiveEnrollmentIdsAction;
use App\Domains\Courses\Components\Arabic\Actions\ListArabicSkillReportAction;
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

        // Every enrolment the student holds, not the latest one: the report
        // is theirs, across courses. Reading the latest only sent every
        // attempt on an earlier course to zero the day they enrolled on
        // another (STATUS §5fx). No student, or no enrolment, reads nothing.
        $enrollmentIds = $student
            ? app(ListLiveEnrollmentIdsAction::class)->execute((int) $student['id'])
            : [];

        return Inertia::render('Courses/Learn/ArabicReport', app(ListArabicSkillReportAction::class)->execute(
            null,
            $enrollmentIds,
        ) + ['student' => $student]);
    }
}
