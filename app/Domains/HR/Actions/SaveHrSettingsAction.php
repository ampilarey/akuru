<?php

namespace App\Domains\HR\Actions;

use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Validation\ValidationException;

/**
 * The school's HR policy, set by the school.
 *
 * `hr.staff_self_checkin` gates the portal check-in button
 * (`ResolveHrSettingsAction`); `hr.onboarding_items` and
 * `hr.offboarding_items` are the checklists every new hire and every leaver
 * gets (`ResolveHrChecklistSettingsAction`, `SeedStaffChecklistAction`). All
 * three were read on every request and editable from no screen — the ninth
 * *configured, enforced, unreachable* (S5 audit D3, STATUS §5fe). Same shape
 * as the finance and attendance settings: every value validated here.
 */
class SaveHrSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        $onboarding = $this->items($data['onboarding_items'] ?? null, 'onboarding_items');
        $offboarding = $this->items($data['offboarding_items'] ?? null, 'offboarding_items');
        $selfCheckIn = filter_var($data['staff_self_checkin'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $set = app(SetSettingAction::class);
        $set->execute('hr.staff_self_checkin', $selfCheckIn, 'boolean', 'hr', 'Staff may check themselves in from the portal');
        $set->execute('hr.onboarding_items', $onboarding, 'json', 'hr', 'Onboarding checklist items');
        $set->execute('hr.offboarding_items', $offboarding, 'json', 'hr', 'Offboarding checklist items');
    }

    /**
     * One item per line, or a list. Blank lines dropped; at least one item,
     * at most thirty, none longer than a sentence.
     *
     * @return list<string>
     */
    private function items(mixed $value, string $field): array
    {
        $lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
        $items = array_values(array_filter(array_map(fn ($line) => trim((string) $line), $lines ?: []), fn ($line) => $line !== ''));

        if ($items === []) {
            throw ValidationException::withMessages([$field => 'At least one item — the checklist is seeded for every new profile.']);
        }
        if (count($items) > 30) {
            throw ValidationException::withMessages([$field => 'At most thirty items.']);
        }
        foreach ($items as $item) {
            if (mb_strlen($item) > 120) {
                throw ValidationException::withMessages([$field => 'Keep each item under 120 characters.']);
            }
        }

        return $items;
    }
}
