<?php

namespace App\Domains\PrayerTimes\Http\Controllers\Admin;

use App\Domains\PrayerTimes\Actions\ConfirmPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\ListPrayerBroadcastsAction;
use App\Domains\PrayerTimes\Actions\ListPrayerIslandsAction;
use App\Domains\PrayerTimes\Actions\PreviewPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\SavePrayerBroadcastAction;
use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastLanguage;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastMode;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prayer broadcasts: the list with its filters, the draft form, the preview
 * snapshot and the confirm. Inertia since C9 slice 12 (STATUS §5jn), with
 * its strings keyed for Dhivehi and Arabic. The Actions' refusals (no
 * preview yet, a changing range, nobody consented) keep their own words.
 */
class BroadcastController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $filters = $request->only(['status', 'mode', 'date']);
        $rows = app(ListPrayerBroadcastsAction::class)->execute($filters)->withQueryString();

        return Inertia::render('PrayerTimes/Broadcasts', [
            'broadcasts' => collect($rows->items())->map(fn (PrayerBroadcast $row) => [
                'id' => $row->id,
                'mode' => $row->mode->value,
                'status' => $row->status->value,
                'island' => $row->island?->name_latin,
                'sent_count' => $row->sent_count,
                'failed_count' => $row->failed_count,
                'estimated_cost' => $row->estimated_cost,
            ])->values()->all(),
            'pagination' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'prev' => $rows->previousPageUrl(), 'next' => $rows->nextPageUrl()],
            'filters' => $filters,
            'modes' => array_column(PrayerBroadcastMode::cases(), 'value'),
            'statuses' => array_column(PrayerBroadcastStatus::cases(), 'value'),
            't' => Phrases::once('admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $rows = app(ListPrayerBroadcastsAction::class)->csvRows($request->only(['status', 'mode']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'mode', 'status', 'island', 'date_from', 'date_to', 'sent', 'failed', 'estimated_cost', 'created_at']);
            foreach ($rows as $row) {
                Csv::put($out, $row);
            }
            fclose($out);
        }, 'prayer-broadcasts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        $row = app(SavePrayerBroadcastAction::class)->execute($request->all(), null, (int) $request->user()->id);

        return redirect()->route('admin.prayer-times.broadcasts.edit', $row)->with('success', trans('admin.prayer_flash_draft_saved'));
    }

    public function edit(PrayerBroadcast $broadcast): Response
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);

        return $this->form($broadcast);
    }

    public function update(Request $request, PrayerBroadcast $broadcast): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        app(SavePrayerBroadcastAction::class)->execute($request->all(), $broadcast, (int) $request->user()->id);

        return back()->with('success', trans('admin.prayer_flash_draft_saved'));
    }

    public function preview(PrayerBroadcast $broadcast): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        app(PreviewPrayerBroadcastAction::class)->execute($broadcast);

        return back()->with('success', trans('admin.prayer_flash_preview_ready'));
    }

    public function confirm(Request $request, PrayerBroadcast $broadcast): RedirectResponse
    {
        abort_unless(auth()->user()?->can('prayer.manage'), 403);
        try {
            app(ConfirmPrayerBroadcastAction::class)->execute($broadcast, (int) $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }

        return back()->with('success', trans('admin.prayer_flash_queued'));
    }

    private function form(?PrayerBroadcast $broadcast): Response
    {
        return Inertia::render('PrayerTimes/BroadcastForm', [
            'broadcast' => $broadcast === null ? null : $this->present($broadcast),
            'islands' => app(ListPrayerIslandsAction::class)->execute(true)->map(fn (IslandDTO $island) => ['id' => $island->id, 'name' => $island->nameEn])->values()->all(),
            'groups' => PrayerRecipientGroup::query()->where('is_active', true)->get()->map(fn (PrayerRecipientGroup $group) => ['id' => $group->id, 'name' => $group->name_en])->values()->all(),
            'modes' => array_column(PrayerBroadcastMode::cases(), 'value'),
            'languages' => array_column(PrayerBroadcastLanguage::cases(), 'value'),
            't' => Phrases::once('admin'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PrayerBroadcast $broadcast): array
    {
        $snapshot = is_array($broadcast->preview_snapshot) ? $broadcast->preview_snapshot : null;

        return [
            'id' => $broadcast->id,
            'mode' => $broadcast->mode->value,
            'status' => $broadcast->status->value,
            'island_id' => $broadcast->island_id,
            'date_from' => $broadcast->date_from?->toDateString(),
            'date_to' => $broadcast->date_to?->toDateString(),
            'language' => $broadcast->language?->value ?? 'en',
            'recipient_group_id' => $broadcast->recipient_group_id,
            'recipient_refs' => $broadcast->recipient_refs ? json_encode($broadcast->recipient_refs, JSON_UNESCAPED_UNICODE) : '',
            'snapshot' => $snapshot === null ? null : [
                'included_count' => (int) ($snapshot['included_count'] ?? 0),
                'excluded_count' => (int) ($snapshot['excluded_count'] ?? 0),
                'estimated_cost' => $snapshot['estimated_cost'] ?? 0,
                'needs_split' => ! empty($snapshot['needs_split']),
                'blocks' => json_encode($snapshot['range']['blocks'] ?? [], JSON_PRETTY_PRINT),
                'messages' => [
                    'en' => (string) ($snapshot['messages']['en'] ?? ''),
                    'dv' => (string) ($snapshot['messages']['dv'] ?? ''),
                    'ar' => (string) ($snapshot['messages']['ar'] ?? ''),
                ],
            ],
        ];
    }
}
