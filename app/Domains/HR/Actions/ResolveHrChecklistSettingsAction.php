<?php

namespace App\Domains\HR\Actions;

use Illuminate\Support\Facades\DB;

class ResolveHrChecklistSettingsAction
{
    /**
     * @return array{onboarding: list<string>, offboarding: list<string>}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['hr.onboarding_items', 'hr.offboarding_items'])
            ->pluck('value', 'key');

        return [
            'onboarding' => $this->decode($rows['hr.onboarding_items'] ?? null, [
                'Contract signed',
                'Documents collected',
                'Account roles assigned',
                'Induction completed',
            ]),
            'offboarding' => $this->decode($rows['hr.offboarding_items'] ?? null, [
                'Revoke roles',
                'Exit form signed',
                'Final-pay flagged',
            ]),
        ];
    }

    /**
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function decode(mixed $value, array $fallback): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_map('strval', $decoded));
            }
        }

        return $fallback;
    }
}
