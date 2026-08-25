<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\OnboardingKind;
use App\Domains\HR\Models\StaffOnboardingItem;

class SeedStaffChecklistAction
{
    public function execute(int $staffProfileId, OnboardingKind $kind): int
    {
        $settings = app(ResolveHrChecklistSettingsAction::class)->execute();
        $items = $kind === OnboardingKind::Offboarding ? $settings['offboarding'] : $settings['onboarding'];
        $created = 0;

        foreach ($items as $item) {
            $exists = StaffOnboardingItem::query()
                ->where('staff_profile_id', $staffProfileId)
                ->where('kind', $kind)
                ->where('item', $item)
                ->exists();

            if ($exists) {
                continue;
            }

            StaffOnboardingItem::query()->create([
                'staff_profile_id' => $staffProfileId,
                'kind' => $kind,
                'item' => $item,
            ]);
            $created++;
        }

        return $created;
    }
}
