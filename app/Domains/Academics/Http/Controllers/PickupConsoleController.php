<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\AdvancePickupNoticeAction;
use App\Domains\Academics\Actions\ListPickupNoticesAction;
use App\Domains\Academics\Actions\OpenPickupWindowAction;
use App\Domains\Academics\Models\PickupNotice;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * CLAUDE.md: *"every listing gets CSV export."*
     *
     * A day's release log: who asked for each child, who released them, and
     * when. Both halves of the console in one file with a `list` column, since
     * "waiting" and "left" are the same records at different moments and
     * splitting them into two downloads would make the day harder to read, not
     * easier.
     *
     * Scoped to the date on screen, like `index()` — this console is one day's
     * work by design, and an export of every pick-up ever would be a different
     * thing entirely.
     */
    public function export(Request $request): StreamedResponse
    {
        $date = (string) $request->query('date', now()->toDateString());
        $lists = app(ListPickupNoticesAction::class)->execute($date);

        return response()->streamDownload(function () use ($lists): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['list', 'id', 'student', 'student_number', 'guardian', 'status', 'note', 'requested_at', 'sent_at', 'collected_at']);

            foreach (['waiting', 'left'] as $which) {
                foreach ($lists[$which] as $row) {
                    Csv::put($handle, [
                        $which,
                        $row['id'],
                        $row['student'],
                        $row['student_number'],
                        $row['guardian'],
                        $row['status'],
                        $row['note'],
                        $row['requested_at'],
                        $row['sent_at'],
                        $row['collected_at'],
                    ]);
                }
            }

            fclose($handle);
        }, 'pickup-'.$date.'.csv', ['Content-Type' => 'text/csv']);
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
