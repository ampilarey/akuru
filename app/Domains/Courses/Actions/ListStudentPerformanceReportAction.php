<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;

class ListStudentPerformanceReportAction
{
    /**
     * @return array{students: list<array<string, mixed>>}
     */
    public function execute(int $userId): array
    {
        $people = [];
        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $people[] = [
                'id' => $self['id'],
                'name' => trim($self['first_name'].' '.$self['last_name']),
                'relationship' => 'self',
            ];
        }

        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $people[] = [
                'id' => (int) $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
                'relationship' => (string) ($child->relationship ?? 'child'),
            ];
        }

        $seen = [];
        $students = [];
        foreach ($people as $person) {
            if (isset($seen[$person['id']])) {
                continue;
            }
            $seen[$person['id']] = true;
            $enrollments = CourseEnrollment::query()
                ->where('unified_student_id', $person['id'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->orderByDesc('id')
                ->get();
            $students[] = [
                'id' => $person['id'],
                'name' => $person['name'],
                'relationship' => $person['relationship'],
                'rows' => app(BuildEnrollmentReportRowsAction::class)->execute($enrollments)->all(),
            ];
        }

        return ['students' => $students];
    }
}
