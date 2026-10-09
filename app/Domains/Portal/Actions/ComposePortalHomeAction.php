<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListAnnouncementsForUserAction;
use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ListDayTimetableForStudentAction;
use App\Domains\Academics\Actions\ListHomeworkForStudentAction;
use App\Domains\Courses\Actions\ListStudentPerformanceReportAction;
use App\Domains\ExamsGrades\Actions\ListPublishedExamResultsForStudentsAction;
use App\Domains\Finance\Actions\ListPortalInvoicesAction;
use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Support\Contracts\StudentHifzSummaryReader;
use App\Support\PersonName;

class ComposePortalHomeAction
{
    /**
     * @param  list<string>  $roleNames  the caller's roles, for audience-targeted
     *                                   reads such as the noticeboard. Portal may
     *                                   not import Identity\Models (rule 3), so the
     *                                   controller passes them in.
     * @return array{title: string, students: list<array<string, mixed>>, csvUrl: string, tiles: list<array<string, mixed>>, nextSchoolDay: ?array<string, mixed>, sections: list<array{key: string, label: string, href: ?string}>}
     */
    public function execute(int $userId, bool $isParent = false, array $roleNames = []): array
    {
        $people = $this->people($userId);
        $ids = array_map(fn (array $person): int => $person['id'], $people);
        $performance = collect(app(ListStudentPerformanceReportAction::class)->execute($userId)['students'] ?? [])
            ->keyBy('id');
        $invoices = app(ListPortalInvoicesAction::class)->execute($ids)->groupBy('student_id');
        $exams = app(ListPublishedExamResultsForStudentsAction::class)->execute($ids)->groupBy('student_id');
        $hifz = collect(app(StudentHifzSummaryReader::class)->summariesForStudents($ids))->groupBy('student_id');
        $attendance = app(ListClassAttendanceAction::class);
        $homework = app(ListHomeworkForStudentAction::class);

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
                // E3a: counted per student here so the tile badge is derived
                // from the same read as the homework page.
                'homework_outstanding' => $homework->outstandingCount($id),
            ];
        }

        $hasChildren = collect($people)->contains(fn (array $person): bool => $person['relationship'] !== 'self');

        return [
            'title' => ($isParent || $hasChildren) ? __('portal.home_title_parent') : __('portal.home_title_student'),
            'students' => $students,
            'csvUrl' => '/portal/home/export',
            // E1: tiles carry live status, not just navigation. Every count is
            // derived from data already loaded above — no extra queries — so a
            // tile can never disagree with the page it links to.
            'tiles' => $this->tiles($students, $userId, $roleNames),
            'nextSchoolDay' => $this->nextSchoolDay($students),
            // In the page's language (BACKLOG C21, slice PT1a). A section
            // named like a menu item reads the `nav` book, so the home and the
            // menu never call one screen two things.
            'sections' => [
                ['key' => 'attendance', 'label' => __('nav.attendance'), 'href' => '/portal/attendance'],
                ['key' => 'exams', 'label' => __('portal.label_exams'), 'href' => '/portal/exams'],
                ['key' => 'invoices', 'label' => __('nav.invoices'), 'href' => '/portal/invoices'],
                ['key' => 'courses', 'label' => __('portal.label_courses'), 'href' => '/portal/performance'],
                ['key' => 'hifz', 'label' => __('portal.label_hifz'), 'href' => null],
                ['key' => 'announcements', 'label' => __('portal.label_noticeboard'), 'href' => '/portal/announcements'],
                ['key' => 'homework', 'label' => __('nav.homework'), 'href' => '/portal/homework'],
                ['key' => 'messages', 'label' => __('nav.messages'), 'href' => '/portal/messages'],
                ['key' => 'absence_notes', 'label' => __('nav.absence_notes'), 'href' => '/portal/absence-notes'],
                ['key' => 'meetings', 'label' => __('nav.meetings'), 'href' => '/portal/meetings'],
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
     * @param  list<string>  $roleNames
     * @return list<array<string, mixed>>
     */
    private function tiles(array $students, int $userId, array $roleNames): array
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
                'label' => __('nav.attendance'),
                'href' => '/portal/attendance',
                'badge' => $absences ?: null,
                'status' => $percents->isEmpty()
                    ? __('portal.tile_attendance_none')
                    : __('portal.tile_attendance_present', ['percent' => round((float) $percents->avg(), 1)]),
            ],
            [
                'key' => 'invoices',
                'label' => __('nav.invoices'),
                'href' => '/portal/invoices',
                'badge' => $unpaid ?: null,
                'status' => $unpaid === 0
                    ? __('portal.tile_invoices_none')
                    : __('portal.tile_invoices_unpaid', ['count' => $unpaid, 'amount' => number_format($balance, 2)]),
            ],
            [
                'key' => 'exams',
                'label' => __('portal.label_exams'),
                'href' => '/portal/exams',
                'badge' => $exams ?: null,
                'status' => $exams === 0 ? __('portal.tile_exams_none') : __('portal.tile_exams_count', ['count' => $exams]),
            ],
            [
                'key' => 'courses',
                'label' => __('portal.label_courses'),
                'href' => '/portal/performance',
                'badge' => $courses ?: null,
                'status' => $courses === 0 ? __('portal.tile_courses_none') : __('portal.tile_courses_count', ['count' => $courses]),
            ],
        ];

        // Hifz only applies to students who have it; an empty tile would be
        // noise on a home screen meant to be glanceable.
        if ($hifz > 0) {
            $tiles[] = [
                'key' => 'hifz',
                'label' => __('portal.label_hifz'),
                'href' => null,
                'badge' => $hifz,
                'status' => __('portal.tile_hifz_count', ['count' => $hifz]),
            ];
        }

        // E4: the badge counts only urgent notices, because there is no
        // per-user read state — a badge counting everything would never clear.
        $notices = app(ListAnnouncementsForUserAction::class)->summary($userId, $roleNames);
        $tiles[] = [
            'key' => 'announcements',
            'label' => __('portal.label_noticeboard'),
            'href' => '/portal/announcements',
            'badge' => $notices['urgent'] ?: null,
            'status' => $notices['total'] === 0
                ? __('portal.tile_notices_none')
                : trans_choice('portal.tile_notices_count', $notices['total'], ['count' => $notices['total']]),
        ];

        $outstandingHomework = $rows->sum(fn (array $student): int => (int) ($student['homework_outstanding'] ?? 0));
        $tiles[] = [
            'key' => 'homework',
            'label' => __('nav.homework'),
            'href' => '/portal/homework',
            'badge' => $outstandingHomework ?: null,
            'status' => $outstandingHomework === 0
                ? __('portal.tile_homework_none')
                : __('portal.tile_homework_count', ['count' => $outstandingHomework]),
        ];

        // E2a: an unread badge is the only reason a messages tile earns its
        // place on a glanceable home screen.
        $unreadMessages = app(ListMessageInboxAction::class)->unreadCount($userId);
        $tiles[] = [
            'key' => 'messages',
            'label' => __('nav.messages'),
            'href' => '/portal/messages',
            'badge' => $unreadMessages ?: null,
            'status' => $unreadMessages === 0
                ? __('portal.tile_messages_none')
                : __('portal.tile_messages_count', ['count' => $unreadMessages]),
        ];

        $tiles[] = [
            'key' => 'absence_notes',
            'label' => __('nav.absence_notes'),
            'href' => '/portal/absence-notes',
            'badge' => null,
            'status' => __('portal.tile_absence_notes'),
        ];
        $tiles[] = [
            'key' => 'meetings',
            'label' => __('nav.meetings'),
            'href' => '/portal/meetings',
            'badge' => null,
            'status' => __('portal.tile_meetings'),
        ];

        $prayer = app(ComposeDashboardPrayerAction::class)->execute();
        $next = $prayer['currentPrayer']['prayer'] ?? null;
        if ($next !== null) {
            // The one tile EduPage has no answer to. The prayer is a code
            // (`fajr` …); one the book does not name keeps its own word
            // rather than showing a key.
            $prayerKey = 'portal.prayer_'.$next;
            $tiles[] = [
                'key' => 'prayer',
                'label' => __('nav.prayer_times'),
                'href' => '/prayer-times',
                'badge' => null,
                'status' => (trans()->has($prayerKey) ? __($prayerKey) : ucfirst((string) $next)).' · '.($prayer['currentPrayer']['time'] ?? ''),
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
                'name' => PersonName::ofStudent($self),
                'relationship' => 'self',
            ];
        }
        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $people[] = [
                'id' => (int) $child->id,
                'name' => PersonName::ofStudent($child),
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

        // A parent's home is their children. Their own learning, when they
        // have some, is *My learning*'s (docs/SIGN_IN_PLAN.md ID2a) — the
        // owner: "when he is in parent, he sees all his children". A pupil,
        // who has no children, keeps their own record here.
        $children = array_values(array_filter($unique, fn (array $person): bool => $person['relationship'] !== 'self'));

        return $children !== [] ? $children : $unique;
    }
}
