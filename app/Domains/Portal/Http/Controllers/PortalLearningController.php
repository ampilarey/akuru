<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Courses\Actions\ListGuardianLearningAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalLearningController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render(
            'Portal/Learning',
            app(ListGuardianLearningAction::class)->execute((int) $request->user()->id),
        );
    }
}
