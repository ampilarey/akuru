<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Period;
use Illuminate\Validation\ValidationException;

class SavePeriodAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Period $period = null): Period
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('academics.error_name_required')]);
        }

        $start = (string) ($data['start_time'] ?? '');
        $end = (string) ($data['end_time'] ?? '');
        if ($start === '' || $end === '') {
            $errors = [];
            if ($start === '') {
                $errors['start_time'] = __('academics.error_start_required');
            }
            if ($end === '') {
                $errors['end_time'] = __('academics.error_end_required');
            }
            throw ValidationException::withMessages($errors);
        }

        if ($end <= $start) {
            throw ValidationException::withMessages(['end_time' => __('academics.error_end_after_start')]);
        }

        $schoolId = $data['school_id'] ?? app(ResolveDefaultSchoolIdAction::class)->execute();
        $order = (int) ($data['order'] ?? 0);

        $duplicate = Period::query()
            ->where('school_id', $schoolId)
            ->where('order', $order)
            ->when($period !== null, fn ($query) => $query->where('id', '!=', $period->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['order' => __('academics.error_period_order_taken')]);
        }

        $payload = [
            'school_id' => $schoolId,
            'name' => $name,
            'name_arabic' => $this->nullableString($data['name_arabic'] ?? null),
            'name_dhivehi' => $this->nullableString($data['name_dhivehi'] ?? null),
            'start_time' => $start,
            'end_time' => $end,
            'order' => $order,
            'is_break' => (bool) ($data['is_break'] ?? false),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];

        if ($period === null) {
            return Period::query()->create($payload);
        }

        $period->fill($payload);
        $period->save();

        return $period->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
