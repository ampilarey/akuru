<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListCalendarHolidaysAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalHolidayController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render('Portal/Holidays', [
            'holidays' => app(ListCalendarHolidaysAction::class)->execute(),
        ]);
    }
}
