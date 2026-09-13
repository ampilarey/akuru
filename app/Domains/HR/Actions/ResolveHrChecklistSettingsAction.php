<?php

namespace App\Domains\HR\Actions;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

class ResolveHrChecklistSettingsAction
{
    public function __construct(private SettingsRepositoryInterface $settings) {}

    /**
     * @return array{onboarding: list<string>, offboarding: list<string>}
     */
    public function execute(): array
    {
        $rows = collect($this->settings->many(array_fill_keys([
            'hr.onboarding_items',
            'hr.offboarding_items',
        ], null)));

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
