<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Academics\Actions\ListUnfilledRegistersAction;
use App\Domains\ExamsGrades\Actions\ListExamsAction;
use App\Domains\ExamsGrades\Actions\ListReportCardsAction;

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
     *     unpublishedReportCards: list<array<string, mixed>>,
     *     csvUrl: string,
     *     sections: list<array{key: string, label: string, href: string}>
     * }
     */
    public function execute(?int $yearId = null): array
    {
        $years = app(ListAcademicYearsAction::class)->execute()->values()->all();
        if ($yearId === null) {
            $current = collect($years)->firstWhere('status', 'active') ?? ($years[0] ?? null);
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
            'unpublishedReportCards' => app(ListReportCardsAction::class)->unpublished($yearId)->values()->all(),
            'csvUrl' => '/portal/overview/export'.$query,
            'sections' => [
                ['key' => 'unfilled', 'label' => 'Unfilled registers', 'href' => '/academics/registers'.$query],
                ['key' => 'ungraded', 'label' => 'Ungraded exams', 'href' => '/exams/schedule'.$query],
                ['key' => 'unpublished_report_cards', 'label' => 'Unpublished report cards', 'href' => '/exams/report-cards'],
                ['key' => 'fill_rates', 'label' => 'Fill rate', 'href' => '/academics/registers'.$query],
                ['key' => 'plan_adherence', 'label' => 'Plan adherence', 'href' => '/academics/plans'],
            ],
        ];
    }

    /**
     * The overview as CSV rows, one shape for five sections: the controller
     * streams them and owns nothing about what a row means.
     *
     * @param  array<string, mixed>  $payload  what execute() returned
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    public function csvRows(array $payload): array
    {
        $rows = [];

        foreach ($payload['unfilled'] as $row) {
            $rows[] = ['unfilled', $row['class_name'] ?? '', trim(($row['subject_name'] ?? '').' '.($row['period_name'] ?? '')), $row['status'] ?? '', $row['date'] ?? '', ''];
        }
        foreach ($payload['ungraded'] as $row) {
            $rows[] = ['ungraded', $row['name'] ?? '', trim(($row['class_name'] ?? '').' '.($row['subject_name'] ?? '')), $row['status'] ?? '', $row['exam_date'] ?? '', ''];
        }
        foreach ($payload['unpublishedReportCards'] as $row) {
            $rows[] = ['unpublished_report_card', $row['student_name'] ?? '', trim(($row['class_name'] ?? '').' '.($row['term_name'] ?? '')), $row['status'] ?? '', $row['generated_at'] ?? '', ''];
        }
        foreach ($payload['fillRates'] as $row) {
            $rows[] = ['fill_rate', $row['teacher_name'] ?: ('Teacher #'.($row['teacher_id'] ?? '')), '', '', ($row['filled'] ?? '').'/'.($row['total'] ?? ''), (string) ($row['rate'] ?? '')];
        }
        foreach ($payload['planAdherence'] as $row) {
            $rows[] = ['plan_adherence', $row['title'] ?? '', '', '', ($row['completed'] ?? '').'/'.($row['total'] ?? ''), (string) ($row['rate'] ?? '')];
        }

        return $rows;
    }
}
