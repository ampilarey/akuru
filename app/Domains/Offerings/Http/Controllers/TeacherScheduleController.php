<?php

namespace App\Domains\Offerings\Http\Controllers;

use App\Domains\Offerings\Actions\ListScheduleSessionsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render('Offerings/Teacher/Schedule', [
            'sessions' => app(ListScheduleSessionsAction::class)->execute(
                offeringIds: [],
                teacherUserId: (int) $request->user()->id,
            ),
        ]);
    }
}
