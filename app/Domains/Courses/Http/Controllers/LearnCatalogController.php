<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\ListPublishedCoursesAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);
        $student = app(ResolveStudentForUserAction::class)->execute((int) $request->user()->id);

        return Inertia::render('Courses/Learn/Catalog', [
            'rows' => app(ListPublishedCoursesAction::class)->execute($student !== null ? $student['id'] : null),
        ]);
    }

    public function enroll(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(EnrollSelfLearningAction::class)->execute((int) $request->user()->id, $course);

        return redirect()->route('learn.courses.show', $course)->with('success', 'Enrolled.');
    }
}
