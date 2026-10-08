<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryReadingAlert;
use App\Domains\Library\Models\LibraryReadingEvent;
use Illuminate\Support\Facades\DB;

/**
 * LIBRARY_PLAN §29 admin analytics lists "suspicious activity" as something the
 * admin dashboard shows. This is that list.
 *
 * Reader names are shown because a reviewer has to be able to talk to the
 * person; nothing else about their reading is — §10's rule that private notes
 * are never exposed applies just as much to an abuse screen as to a writer's.
 */
class ListLibraryReadingAlertsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(bool $openOnly = true): array
    {
        $alerts = LibraryReadingAlert::query()
            ->when($openOnly, fn ($q) => $q->whereNull('reviewed_at'))
            ->with('item:id,title,slug')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Rule 3: names come off the table, not out of `Identity\Models\User`.
        // The same shape `ListSensitiveNotesAction` and the Notifications
        // inbox use — a display name is not worth a cross-domain model import.
        $userIds = $alerts->pluck('user_id')->filter()->unique()->all();
        $names = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

        return [
            'alerts' => $alerts->map(fn (LibraryReadingAlert $alert): array => [
                'id' => $alert->id,
                'signal' => $alert->signal?->value,
                'signal_label' => $alert->signal?->label(),
                'reader' => $names[$alert->user_id] ?? '—',
                'item_title' => $alert->item?->title,
                'observed' => $alert->observed,
                'threshold' => $alert->threshold,
                'detail' => $alert->detail,
                // LT3: the detector writes its detail in English (it is a
                // record, and the CSV keeps it). The page says it in its own
                // language when the detail has one of the detector's shapes.
                'detail_said' => $this->detailSaid($alert),
                'raised_at' => $alert->created_at?->toDateTimeString(),
                'reviewed_at' => $alert->reviewed_at?->toDateTimeString(),
                'outcome' => $alert->outcome,
            ])->values()->all(),
            'open_only' => $openOnly,
            // Enforcement state is shown rather than assumed: an admin looking
            // at this list needs to know whether anyone is actually being
            // stopped, or whether these are observations only.
            'enforcing' => (bool) config('library.abuse.enforce', false),
            'events_logged' => LibraryReadingEvent::query()->count(),
        ];
    }

    /**
     * `DetectLibraryReadingAbuseAction` writes "%d pages in %d seconds." and
     * its two siblings; read back, they are said in the page's language.
     * Anything else is returned as it was written.
     */
    private function detailSaid(LibraryReadingAlert $alert): ?string
    {
        $detail = (string) $alert->detail;
        $shapes = [
            'rapid_pages' => '/^(\d+) pages in (\d+) seconds\.$/',
            'many_devices' => '/^(\d+) distinct devices in (\d+) hours\.$/',
            'concurrent_sessions' => '/^(\d+) sessions active in (\d+) minutes\.$/',
        ];
        $signal = $alert->signal?->value;
        if ($detail === '' || $signal === null || ! isset($shapes[$signal]) || preg_match($shapes[$signal], $detail, $found) !== 1) {
            return $detail === '' ? null : $detail;
        }

        return __('admin.library_alerts_detail_'.$signal, ['observed' => $found[1], 'window' => $found[2]]);
    }
}
