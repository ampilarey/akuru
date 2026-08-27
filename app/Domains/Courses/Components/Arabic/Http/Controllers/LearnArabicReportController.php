<?php

namespace App\Domains\Courses\Components\Arabic\Http\Controllers;

use App\Domains\Courses\Actions\ResolveLatestEnrollmentIdAction;
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
        $enrollmentId = $student
            ? app(ResolveLatestEnrollmentIdAction::class)->execute((int) $student['id'])
            : null;

        return Inertia::render('Courses/Learn/ArabicReport', app(ListArabicSkillReportAction::class)->execute(
            null,
            $enrollmentId ? (int) $enrollmentId : -1,
        ) + ['student' => $student]);
    }
}
