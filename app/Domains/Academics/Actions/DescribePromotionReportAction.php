<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\ClassRoom;
use App\Domains\People\Actions\ListStudentsByIdsAction;

/**
 * The promotion wizard's report with each pupil and class named.
 *
 * `PromoteStudentsAction` reports what it moved by id, which is right for the
 * move, and the wizard printed those ids — a column of numbers nobody in an
 * office can check a promotion against (BACKLOG C21, slice OA2). The names
 * are looked up when the report is shown, so the session holds only what the
 * action returned.
 */
class DescribePromotionReportAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(mixed $report): ?array
    {
        if (! is_array($report)) {
            return null;
        }

        $outcomes = array_values(array_filter($report['outcomes'] ?? [], 'is_array'));
        $students = app(ListStudentsByIdsAction::class)
            ->execute(array_map('intval', array_column($outcomes, 'student_id')))
            ->keyBy('id');
        $classIds = array_filter(array_merge(
            array_column($outcomes, 'source_class_id'),
            array_column($outcomes, 'target_class_id'),
        ));
        $classes = ClassRoom::query()
            ->whereIn('id', $classIds)
            ->get(['id', 'name', 'section'])
            ->mapWithKeys(fn (ClassRoom $class) => [$class->id => trim($class->name.' '.$class->section)]);

        $report['outcomes'] = array_map(fn (array $row) => $row + [
            'student_name' => $students->get((int) ($row['student_id'] ?? 0))['name'] ?? null,
            'source_class_name' => $classes->get((int) ($row['source_class_id'] ?? 0)),
            'target_class_name' => $classes->get((int) ($row['target_class_id'] ?? 0)),
        ], $outcomes);

        return $report;
    }
}
