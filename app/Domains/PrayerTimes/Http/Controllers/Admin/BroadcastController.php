<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ConfirmPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\ListPrayerBroadcastsAction;
use App\Domains\PrayerTimes\Actions\ListPrayerIslandsAction;
use App\Domains\PrayerTimes\Actions\PreviewPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\SavePrayerBroadcastAction;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BroadcastController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return view('admin.prayer-times.broadcasts.index', [
            'broadcasts' => app(ListPrayerBroadcastsAction::class)->execute($request->only(['status', 'mode', 'date'])),
            'filters' => $request->only(['status', 'mode', 'date']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $rows = app(ListPrayerBroadcastsAction::class)->csvRows($request->only(['status', 'mode']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'mode', 'status', 'island', 'date_from', 'date_to', 'sent', 'failed', 'estimated_cost', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'prayer-broadcasts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return view('admin.prayer-times.broadcasts.form', [
            'broadcast' => null,
            'islands' => app(ListPrayerIslandsAction::class)->execute(true),
            'groups' => PrayerRecipientGroup::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $row = app(SavePrayerBroadcastAction::class)->execute($request->all(), null, (int) $request->user()->id);

        return redirect()->route('admin.prayer-times.broadcasts.edit', $row)->with('success', 'Draft saved.');
    }

    public function edit(PrayerBroadcast $broadcast)
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $broadcast->load('recipients');

        return view('admin.prayer-times.broadcasts.form', [
            'broadcast' => $broadcast,
            'islands' => app(ListPrayerIslandsAction::class)->execute(true),
            'groups' => PrayerRecipientGroup::query()->where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, PrayerBroadcast $broadcast): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        app(SavePrayerBroadcastAction::class)->execute($request->all(), $broadcast, (int) $request->user()->id);

        return back()->with('success', 'Draft saved.');
    }

    public function preview(PrayerBroadcast $broadcast): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        app(PreviewPrayerBroadcastAction::class)->execute($broadcast);

        return back()->with('success', 'Preview ready. Review the snapshot then confirm.');
    }

    public function confirm(Request $request, PrayerBroadcast $broadcast): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        try {
            app(ConfirmPrayerBroadcastAction::class)->execute($broadcast, (int) $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return back()->with('success', 'Broadcast queued.');
    }
}
