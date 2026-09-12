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
}
