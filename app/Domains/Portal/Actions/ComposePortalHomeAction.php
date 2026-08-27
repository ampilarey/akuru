<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Courses\Actions\ListStudentPerformanceReportAction;
use App\Domains\ExamsGrades\Actions\ListPublishedExamResultsForStudentsAction;
use App\Domains\Finance\Actions\ListPortalInvoicesAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Support\Contracts\StudentHifzSummaryReader;

class ComposePortalHomeAction
{
    /**
     * @return array{title: string, students: list<array<string, mixed>>, csvUrl: string, sections: list<array{key: string, label: string, href: ?string}>}
     */
    public function execute(int $userId, bool $isParent = false): array
    {
        $people = $this->people($userId);
        $ids = array_map(fn (array $person): int => $person['id'], $people);
        $performance = collect(app(ListStudentPerformanceReportAction::class)->execute($userId)['students'] ?? [])
            ->keyBy('id');
        $invoices = app(ListPortalInvoicesAction::class)->execute($ids)->groupBy('student_id');
        $exams = app(ListPublishedExamResultsForStudentsAction::class)->execute($ids)->groupBy('student_id');
        $hifz = collect(app(StudentHifzSummaryReader::class)->summariesForStudents($ids))->groupBy('student_id');
        $attendance = app(ListClassAttendanceAction::class);

        $students = [];
        foreach ($people as $person) {
            $id = $person['id'];
            $invoiceRows = $invoices->get($id, collect())->values();
            $students[] = [
                'id' => $id,
                'name' => $person['name'],
                'relationship' => $person['relationship'],
                'attendance_summary' => $attendance->studentSummary($id)->first(),
                'attendance' => $attendance->execute(['student_id' => $id])->take(8)->values()->all(),
                'exams' => $exams->get($id, collect())->take(8)->values()->all(),
                'invoices' => $invoiceRows->take(8)->all(),
                'invoice_balance' => number_format(
                    (float) $invoiceRows->sum(fn (array $row): float => (float) ($row['balance'] ?? 0)),
                    2,
                    '.',
                    '',
                ),
                'courses' => $performance->get($id)['rows'] ?? [],
                'hifz' => $hifz->get($id, collect())->values()->all(),
            ];
        }

        $hasChildren = collect($people)->contains(fn (array $person): bool => $person['relationship'] !== 'self');

        return [
            'title' => ($isParent || $hasChildren) ? 'Parent Dashboard' : 'Student Dashboard',
            'students' => $students,
            'csvUrl' => '/portal/home/export',
            'sections' => [
                ['key' => 'attendance', 'label' => 'Attendance', 'href' => '/portal/attendance'],
                ['key' => 'exams', 'label' => 'Exams / grades', 'href' => '/portal/exams'],
                ['key' => 'invoices', 'label' => 'Invoices', 'href' => '/portal/invoices'],
                ['key' => 'courses', 'label' => 'Course progress', 'href' => '/portal/performance'],
                ['key' => 'hifz', 'label' => 'Hifz', 'href' => null],
                ['key' => 'absence_notes', 'label' => 'Absence notes', 'href' => '/portal/absence-notes'],
                ['key' => 'meetings', 'label' => 'Meetings', 'href' => '/portal/meetings'],
            ],
        ];
    }

    /**
     * @return list<array{id: int, name: string, relationship: string}>
     */
    private function people(int $userId): array
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
        $unique = [];
        foreach ($people as $person) {
            if (isset($seen[$person['id']])) {
                continue;
            }
            $seen[$person['id']] = true;
            $unique[] = $person;
        }

        return $unique;
    }
}
