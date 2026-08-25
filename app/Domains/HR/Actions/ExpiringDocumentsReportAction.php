<?php

namespace App\Domains\HR\Actions;

use App\Domains\Media\Actions\ListExpiringDocumentsAction;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ExpiringDocumentsReportAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $withinDays = 90): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        return app(ListExpiringDocumentsAction::class)
            ->execute($withinDays)
            ->filter(fn (array $document): bool => $document['documentable_type'] === 'staff_profile')
            ->map(function (array $document) use ($staff): array {
                $profile = $staff->get((int) $document['documentable_id']);

                return [
                    ...$document,
                    'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                    'staff_profile_id' => (int) $document['documentable_id'],
                    'user_id' => $profile->user_id ?? null,
                ];
            })
            ->values();
    }
}
