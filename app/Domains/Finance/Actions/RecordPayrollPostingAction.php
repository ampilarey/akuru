<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\PayrollPosting;

class RecordPayrollPostingAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $year, int $month, float $totalNet, int $staffCount): array
    {
        $row = PayrollPosting::query()->updateOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'total_net' => $totalNet,
                'staff_count' => $staffCount,
                'posted_at' => now(),
            ],
        );

        return [
            'id' => $row->id,
            'year' => $row->year,
            'month' => $row->month,
            'total_net' => (string) $row->total_net,
            'staff_count' => $row->staff_count,
        ];
    }
}
