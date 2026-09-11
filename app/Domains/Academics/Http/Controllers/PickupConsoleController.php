<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\AdvancePickupNoticeAction;
use App\Domains\Academics\Actions\ListPickupNoticesAction;
use App\Domains\Academics\Actions\OpenPickupWindowAction;
use App\Domains\Academics\Models\PickupNotice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The office console. Thin (rule 5): every gate lives in the Actions, because
 * this is the module that releases a child.
 */
class PickupConsoleController extends Controller
{
    public function index(Request $request): Response
    {
        $date = (string) $request->query('date', now()->toDateString());
        $lists = app(ListPickupNoticesAction::class)->execute($date);

        return Inertia::render('Academics/Pickup/Console', [
            'date' => $date,
            'is_open' => app(OpenPickupWindowAction::class)->isOpen($date),
            'waiting' => $lists['waiting'],
            'left' => $lists['left'],
        ]);
    }

    public function open(Request $request, OpenPickupWindowAction $window): RedirectResponse
    {
        $window->open((int) $request->user()->id, (string) $request->input('date', now()->toDateString()));

        return back()->with('success', 'Pick-up is open.');
    }

    public function close(Request $request, OpenPickupWindowAction $window): RedirectResponse
    {
        $window->close((string) $request->input('date', now()->toDateString()));

        return back()->with('success', 'Pick-up is closed.');
    }

    public function send(Request $request, PickupNotice $notice, AdvancePickupNoticeAction $advance): RedirectResponse
    {
        $advance->send($notice, (int) $request->user()->id);

        return back()->with('success', 'Child sent to reception.');
    }

    public function cancel(PickupNotice $notice, AdvancePickupNoticeAction $advance): RedirectResponse
    {
        $advance->cancel($notice);

        return back()->with('success', 'Request cancelled.');
    }
}
