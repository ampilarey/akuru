<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\LeaveTypeCode;
use App\Domains\HR\Models\LeaveType;
use Illuminate\Validation\ValidationException;

class SaveLeaveTypeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?LeaveType $type = null): LeaveType
    {
        $code = LeaveTypeCode::tryFrom((string) ($data['code'] ?? ''));
        if ($code === null) {
            throw ValidationException::withMessages(['code' => 'Invalid leave type code.']);
        }

        $payload = [
            'code' => $code,
            'name' => (string) $data['name'],
            'name_arabic' => $data['name_arabic'] ?? null,
            'name_dhivehi' => $data['name_dhivehi'] ?? null,
            'days_per_year' => (float) ($data['days_per_year'] ?? 0),
            'carry_over_max' => (float) ($data['carry_over_max'] ?? 0),
            'requires_document' => (bool) ($data['requires_document'] ?? false),
            'paid' => array_key_exists('paid', $data) ? (bool) $data['paid'] : true,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];

        if ($type === null) {
            return LeaveType::query()->create($payload);
        }

        $type->fill($payload);
        $type->save();

        return $type->refresh();
    }
}
