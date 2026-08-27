<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Academics\Actions\ListUnfilledRegistersAction;
use App\Domains\ExamsGrades\Actions\ListExamsAction;

class ComposeStaffOverviewAction
{
    /**
     * @return array{
     *     title: string,
     *     yearId: ?int,
     *     years: list<array<string, mixed>>,
     *     unfilled: list<array<string, mixed>>,
     *     fillRates: list<array<string, mixed>>,
     *     planAdherence: list<array<string, mixed>>,
     *     ungraded: list<array<string, mixed>>,
     *     csvUrl: string,
     *     sections: list<array{key: string, label: string, href: string}>
     * }
     */
    public function execute(?int $yearId = null): array
    {
        $years = app(ListAcademicYearsAction::class)->execute()->values()->all();
        if ($yearId === null) {
            $current = collect($years)->firstWhere('is_current', true) ?? ($years[0] ?? null);
            $yearId = isset($current['id']) ? (int) $current['id'] : null;
        }

        $registers = app(ListUnfilledRegistersAction::class);
        $exams = app(ListExamsAction::class);
        $query = $yearId ? '?academic_year_id='.$yearId : '';

        return [
            'title' => 'Staff overview',
            'yearId' => $yearId,
            'years' => $years,
            'unfilled' => $registers->execute($yearId)->values()->all(),
            'fillRates' => $registers->fillRates($yearId)->values()->all(),
            'planAdherence' => $registers->planAdherence($yearId)->values()->all(),
            'ungraded' => $exams->ungraded($exams->execute($yearId))->values()->all(),
            'csvUrl' => '/portal/overview/export'.$query,
            'sections' => [
                ['key' => 'unfilled', 'label' => 'Unfilled registers', 'href' => '/academics/registers'.$query],
                ['key' => 'ungraded', 'label' => 'Ungraded exams', 'href' => '/exams/schedule'.$query],
                ['key' => 'fill_rates', 'label' => 'Fill rate', 'href' => '/academics/registers'.$query],
                ['key' => 'plan_adherence', 'label' => 'Plan adherence', 'href' => '/academics/plans'],
            ],
        ];
    }
}
