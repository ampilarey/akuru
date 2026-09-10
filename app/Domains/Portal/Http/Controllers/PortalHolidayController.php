<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListPublicCalendarAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school calendar, as families and teachers see it.
 *
 * The route keeps its `/portal/holidays` path: families may have it bookmarked
 * and a working URL is worth more than a tidy one. What it *shows* is no longer
 * only holidays — E11b — because a sports day or an exam week was recorded by
 * the office and read by nobody.
 */
class PortalHolidayController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render('Portal/SchoolCalendar', app(ListPublicCalendarAction::class)->execute());
    }
}
