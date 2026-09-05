<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ListDayTimetableForStudentAction;
use App\Domains\Courses\Actions\ListStudentPerformanceReportAction;
use App\Domains\ExamsGrades\Actions\ListPublishedExamResultsForStudentsAction;
use App\Domains\Finance\Actions\ListPortalInvoicesAction;
use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Support\Contracts\StudentHifzSummaryReader;

class ComposePortalHomeAction
{
    /**
     * @return array{title: string, students: list<array<string, mixed>>, csvUrl: string, tiles: list<array<string, mixed>>, nextSchoolDay: ?array<string, mixed>, sections: list<array{key: string, label: string, href: ?string}>}
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
            // E1: tiles carry live status, not just navigation. Every count is
            // derived from data already loaded above — no extra queries — so a
            // tile can never disagree with the page it links to.
            'tiles' => $this->tiles($students, $userId),
            'nextSchoolDay' => $this->nextSchoolDay($students),
            'sections' => [
                ['key' => 'attendance', 'label' => 'Attendance', 'href' => '/portal/attendance'],
                ['key' => 'exams', 'label' => 'Exams / grades', 'href' => '/portal/exams'],
                ['key' => 'invoices', 'label' => 'Invoices', 'href' => '/portal/invoices'],
                ['key' => 'courses', 'label' => 'Course progress', 'href' => '/portal/performance'],
                ['key' => 'hifz', 'label' => 'Hifz', 'href' => null],
                ['key' => 'messages', 'label' => 'Messages', 'href' => '/portal/messages'],
                ['key' => 'absence_notes', 'label' => 'Absence notes', 'href' => '/portal/absence-notes'],
                ['key' => 'meetings', 'label' => 'Meetings', 'href' => '/portal/meetings'],
            ],
        ];
    }

    /**
     * Tiles summarise; they are not links with a label.
     *
     * Counts come from the already-composed $students payload rather than
     * fresh queries, which is both cheaper and the only way to guarantee a
     * tile matches the page it points at.
     *
     * @param  list<array<string, mixed>>  $students
     * @return list<array<string, mixed>>
     */
    private function tiles(array $students, int $userId): array
    {
        $rows = collect($students);

        $unpaid = $rows->sum(fn (array $student): int => collect($student['invoices'])
            ->filter(fn (array $invoice): bool => (float) ($invoice['balance'] ?? 0) > 0)
            ->count());
        $balance = $rows->sum(fn (array $student): float => (float) $student['invoice_balance']);

        $absences = $rows->sum(fn (array $student): int => (int) ($student['attendance_summary']['absent'] ?? 0));
        $percents = $rows->pluck('attendance_summary.percent')->filter(fn ($value): bool => $value !== null);

        $exams = $rows->sum(fn (array $student): int => count($student['exams']));
        $courses = $rows->sum(fn (array $student): int => count($student['courses']));
        $hifz = $rows->sum(fn (array $student): int => count($student['hifz']));

        $tiles = [
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'href' => '/portal/attendance',
                'badge' => $absences ?: null,
                'status' => $percents->isEmpty()
                    ? 'No records yet'
                    : round((float) $percents->avg(), 1).'% present',
            ],
            [
                'key' => 'invoices',
                'label' => 'Invoices',
                'href' => '/portal/invoices',
                'badge' => $unpaid ?: null,
                'status' => $unpaid === 0
                    ? 'Nothing due'
                    : $unpaid.' unpaid · MVR '.number_format($balance, 2),
            ],
            [
                'key' => 'exams',
                'label' => 'Exams / grades',
                'href' => '/portal/exams',
                'badge' => $exams ?: null,
                'status' => $exams === 0 ? 'No results yet' : $exams.' published',
            ],
            [
                'key' => 'courses',
                'label' => 'Course progress',
                'href' => '/portal/performance',
                'badge' => $courses ?: null,
                'status' => $courses === 0 ? 'No courses' : $courses.' enrolled',
            ],
        ];

        // Hifz only applies to students who have it; an empty tile would be
        // noise on a home screen meant to be glanceable.
        if ($hifz > 0) {
            $tiles[] = [
                'key' => 'hifz',
                'label' => 'Hifz',
                'href' => null,
                'badge' => $hifz,
                'status' => $hifz.' tracked',
            ];
        }

        // E2a: an unread badge is the only reason a messages tile earns its
        // place on a glanceable home screen.
        $unreadMessages = app(ListMessageInboxAction::class)->unreadCount($userId);
        $tiles[] = [
            'key' => 'messages',
            'label' => 'Messages',
            'href' => '/portal/messages',
            'badge' => $unreadMessages ?: null,
            'status' => $unreadMessages === 0 ? 'Nothing new' : $unreadMessages.' unread',
        ];

        $tiles[] = [
            'key' => 'absence_notes',
            'label' => 'Absence notes',
            'href' => '/portal/absence-notes',
            'badge' => null,
            'status' => 'Send a note',
        ];
        $tiles[] = [
            'key' => 'meetings',
            'label' => 'Meetings',
            'href' => '/portal/meetings',
            'badge' => null,
            'status' => 'Book a slot',
        ];

        $prayer = app(ComposeDashboardPrayerAction::class)->execute();
        $next = $prayer['currentPrayer']['prayer'] ?? null;
        if ($next !== null) {
            // The one tile EduPage has no answer to.
            $tiles[] = [
                'key' => 'prayer',
                'label' => 'Prayer times',
                'href' => '/prayer-times',
                'badge' => null,
                'status' => ucfirst((string) $next).' · '.($prayer['currentPrayer']['time'] ?? ''),
            ];
        }

        return $tiles;
    }

    /**
     * The next day that actually has lessons, for the "tomorrow" strip.
     *
     * Starts at tomorrow and looks forward a week, because in the Maldives the
     * weekend means a Thursday visitor would otherwise see an empty strip. The
     * date is returned so the UI can say "Tomorrow" or name the weekday
     * honestly rather than mislabelling a Sunday as tomorrow.
     *
     * @param  list<array<string, mixed>>  $students
     */
    private function nextSchoolDay(array $students): ?array
    {
        $first = $students[0] ?? null;
        if ($first === null) {
            return null;
        }

        $reader = app(ListDayTimetableForStudentAction::class);

        for ($offset = 1; $offset <= 7; $offset++) {
            $date = now()->timezone(config('app.timezone'))->addDays($offset)->toDateString();
            $day = $reader->execute((int) $first['id'], $date);

            if ($day['periods'] !== []) {
                return [...$day, 'student_name' => $first['name'], 'is_tomorrow' => $offset === 1];
            }
        }

        return null;
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
