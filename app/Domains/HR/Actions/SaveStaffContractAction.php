<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\StaffContractStatus;
use App\Domains\HR\Enums\StaffContractType;
use App\Domains\HR\Models\StaffContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveStaffContractAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?StaffContract $contract = null): StaffContract
    {
        $type = StaffContractType::tryFrom((string) ($data['contract_type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['contract_type' => 'Invalid contract type.']);
        }

        $status = StaffContractStatus::tryFrom((string) ($data['status'] ?? StaffContractStatus::Active->value))
            ?? StaffContractStatus::Active;

        $payload = [
            'staff_profile_id' => (int) $data['staff_profile_id'],
            'contract_type' => $type,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'probation_until' => $data['probation_until'] ?? null,
            'basic_salary' => $data['basic_salary'] ?? 0,
            'allowances' => $data['allowances'] ?? [],
            'working_hours_per_week' => $data['working_hours_per_week'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'status' => $status,
        ];

        return DB::transaction(function () use ($payload, $status, $contract): StaffContract {
            if ($status === StaffContractStatus::Active) {
                StaffContract::query()
                    ->where('staff_profile_id', $payload['staff_profile_id'])
                    ->where('status', StaffContractStatus::Active)
                    ->when($contract !== null, fn ($query) => $query->where('id', '!=', $contract->id))
                    ->update(['status' => StaffContractStatus::Superseded]);
            }

            if ($contract === null) {
                return StaffContract::query()->create($payload);
            }

            $contract->fill($payload);
            $contract->save();

            return $contract->refresh();
        });
    }
}
