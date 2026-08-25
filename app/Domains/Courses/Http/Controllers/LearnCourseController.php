<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseLearningAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnCourseController extends Controller
{
    public function show(Request $request, int $course): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render(
            'Courses/Learn/Show',
            app(ListCourseLearningAction::class)->execute($course, (int) $request->user()->id),
        );
    }
}
