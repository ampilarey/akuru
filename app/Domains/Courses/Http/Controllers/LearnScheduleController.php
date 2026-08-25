<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListStudentScheduleAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render(
            'Courses/Learn/Schedule',
            app(ListStudentScheduleAction::class)->execute((int) $request->user()->id),
        );
    }
}
