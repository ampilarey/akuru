<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Print\PrintProductionAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NAME_TAGS_PLAN §4.8 (NT4): the print desk's queue — the jobs on paid
 * orders, with their print files, and the four steps the desk takes.
 * `/admin/bookshop/print/queue`, for `bookshop.manage`.
 */
class AdminPrintQueueController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $all = $request->boolean('all');

        return Inertia::render('Bookshop/PrintQueue', [
            't' => Phrases::once('print'),
            'rows' => app(PrintProductionAction::class)->queue($all, app()->getLocale()),
            'all' => $all,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(PrintProductionAction::class)->queue($request->boolean('all'), app()->getLocale());

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['job', 'name', 'status', 'order', 'order_status', 'customer', 'phone', 'delivery', 'sheets', 'paper', 'colour', 'finishing', 'total', 'ordered_at', 'due', 'reprints']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['id'], $r['name'], $r['status'], $r['order'], $r['order_status'], $r['customer'], $r['phone'], $r['delivery'], $r['sheets'], $r['paper'],
                    $r['colour'] ? 'yes' : 'no', implode('; ', $r['finishing']), number_format($r['total'], 2, '.', ''), $r['ordered_at'], $r['due'], $r['reprints']]);
            }
            fclose($out);
        }, 'bookstore-print-queue.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function step(Request $request, int $job, string $step): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $by = (int) $request->user()->id;
        $desk = app(PrintProductionAction::class);
        match ($step) {
            'start' => $desk->start($job, $by),
            'finish' => $desk->finish($job, $by),
            'reprint' => $desk->reprint($job, (string) $request->validate(['reason' => 'required|string|max:500'])['reason'], $by),
            'problem' => $desk->problem($job, (string) $request->validate(['note' => 'required|string|max:500'])['note'], $by),
        };

        return back()->with('success', __('print.flash_queue_'.$step));
    }
}
