<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\ListStudentQuranDashboardAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F4 student dashboard (§52.7 non-AI subset) — replaces the frozen Hifz
 * Blade student views for engine-keyed data.
 */
class LearnQuranController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);
        $student = app(ResolveStudentForUserAction::class)->execute((int) $request->user()->id);
        $payload = $student
            ? app(ListStudentQuranDashboardAction::class)->execute((int) $student['id'])
            : ['submissions' => [], 'progress' => [], 'schedules' => []];

        return Inertia::render('Courses/Learn/Quran', $payload + ['student' => $student]);
    }
}
