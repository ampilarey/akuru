<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\AppraisalCycle;

class SaveAppraisalCycleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AppraisalCycle
    {
        return AppraisalCycle::query()->create([
            'name' => $data['name'],
            'academic_year_id' => (int) $data['academic_year_id'],
            'opens_at' => $data['opens_at'],
            'closes_at' => $data['closes_at'],
            'template' => $data['template'] ?? ['scale' => 5, 'sections' => ['Teaching', 'Professionalism']],
            'status' => $data['status'] ?? 'open',
        ]);
    }
}
