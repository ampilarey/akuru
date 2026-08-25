<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Models\FeeItem;
use Illuminate\Validation\ValidationException;

class SaveFeeItemAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?FeeItem $item = null): FeeItem
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $amount = $data['default_amount'] ?? null;
        if ($amount === null || $amount === '' || (float) $amount < 0) {
            throw ValidationException::withMessages(['default_amount' => 'Amount must be zero or more.']);
        }

        $payload = [
            'name' => $name,
            'name_arabic' => $this->nullable($data['name_arabic'] ?? null),
            'name_dhivehi' => $this->nullable($data['name_dhivehi'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'default_amount' => $amount,
            'currency' => strtoupper((string) ($data['currency'] ?? 'MVR')),
            'type' => FeeItemType::from((string) ($data['type'] ?? FeeItemType::Other->value)),
            'frequency' => FeeFrequency::from((string) ($data['frequency'] ?? FeeFrequency::OneTime->value)),
            'is_mandatory' => array_key_exists('is_mandatory', $data) ? (bool) $data['is_mandatory'] : true,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'applicable_grades' => $data['applicable_grades'] ?? null,
        ];

        if ($item === null) {
            return FeeItem::query()->create($payload);
        }

        $item->fill($payload);
        $item->save();

        return $item->refresh();
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
