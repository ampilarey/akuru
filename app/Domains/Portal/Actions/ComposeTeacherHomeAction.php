<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListAnnouncementsForUserAction;
use App\Domains\Academics\Actions\ListDayTimetableForTeacherAction;
use App\Domains\Academics\Actions\ListTeacherTodayRegistersAction;
use App\Domains\Academics\Actions\ListUnfilledRegistersAction;
use App\Domains\Academics\Actions\ResolveTeacherIdForUserAction;
use App\Domains\Notifications\Actions\ListMessageInboxAction;
use Carbon\Carbon;

/**
 * A teacher's own home.
 *
 * E1 shipped the family home — tiles with live counts, a next-school-day strip.
 * A teacher got none of it: `/dashboard` redirected them straight into the
 * register list, which is a task queue, not a home. They had no glanceable
 * answer to "what am I teaching, what do I owe, has anyone written to me".
 *
 * This is the teacher half of E1's open decision, answered as **their own home,
 * not the school's report**. `ComposeStaffOverviewAction` already exists and is
 * deliberately left alone: it is school-wide (every teacher's fill rate, every
 * unfilled register) which is what a head needs and noise to a class teacher.
 * One grid for everyone would have had to be one or the other.
 *
 * Composes from other domains' Actions only — Portal owns no tables (rule 3).
 * Every count is derived from the same read as the page it links to, so a tile
 * can never disagree with what it points at.
 */
class ComposeTeacherHomeAction
{
    /** How far ahead to look for the next day that actually has lessons. */
    private const LOOKAHEAD_DAYS = 7;

    /**
     * @param  list<string>  $roleNames  the caller's roles, for audience-targeted
     *                                   reads. Portal may not import
     *                                   Identity\Models (rule 3), so the
     *                                   controller passes them in.
     * @return array{title: string, teacherId: ?int, today: array<string, mixed>, next: ?array<string, mixed>, unfilled: list<array<string, mixed>>, tiles: list<array<string, mixed>>}
     */
    public function execute(int $userId, array $roleNames = []): array
    {
        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($userId);
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        // A staff account with no teacher record still gets the home rather than
        // an error: the messages and noticeboard tiles are true for anybody, and
        // an empty page would be worse than a partial one.
        $timetable = app(ListDayTimetableForTeacherAction::class);

        $todayPeriods = $teacherId === null
            ? ['date' => $today->toDateString(), 'is_school_day' => true, 'note' => null, 'periods' => []]
            : $timetable->execute($teacherId, $today->toDateString());

        $unfilled = $teacherId === null
            ? []
            : app(ListUnfilledRegistersAction::class)->execute(null, $teacherId)->values()->all();

        return [
            'title' => 'My day',
            'teacherId' => $teacherId,
            'today' => $todayPeriods,
            'next' => $teacherId === null ? null : $this->nextTeachingDay($teacherId, $today),
            'registersToday' => $teacherId === null
                ? []
                : app(ListTeacherTodayRegistersAction::class)
                    ->execute($teacherId, $today->toDateString())->values()->all(),
            'unfilled' => $unfilled,
            'tiles' => $this->tiles($userId, $roleNames, $unfilled, $todayPeriods),
        ];
    }

    /**
     * The next day this teacher actually has lessons, not simply tomorrow.
     *
     * "Tomorrow" is the organising idea, but a strip that says "nothing
     * tomorrow" on a Thursday and stays silent about a full Sunday is worse
     * than useless — so it scans forward and names the day it found.
     *
     * @return ?array<string, mixed>
     */
    private function nextTeachingDay(int $teacherId, Carbon $from): ?array
    {
        $timetable = app(ListDayTimetableForTeacherAction::class);

        for ($offset = 1; $offset <= self::LOOKAHEAD_DAYS; $offset++) {
            $day = $from->copy()->addDays($offset);
            $found = $timetable->execute($teacherId, $day->toDateString());

            if ($found['periods'] !== []) {
                return [...$found, 'day_name' => $day->englishDayOfWeek];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $roleNames
     * @param  list<array<string, mixed>>  $unfilled
     * @param  array<string, mixed>  $todayPeriods
     * @return list<array<string, mixed>>
     */
    private function tiles(int $userId, array $roleNames, array $unfilled, array $todayPeriods): array
    {
        $owed = count($unfilled);
        $lessons = count($todayPeriods['periods'] ?? []);
        $covering = collect($todayPeriods['periods'] ?? [])
            ->filter(fn (array $period): bool => ($period['is_mine'] ?? true) === false)
            ->count();

        $tiles = [
            [
                'key' => 'registers',
                'label' => 'Registers',
                'href' => '/academics/registers/today',
                // The badge is what is *owed*, not what exists. A count that
                // never reaches zero stops being read.
                'badge' => $owed ?: null,
                'status' => $owed === 0
                    ? 'All filled'
                    : $owed.' to fill',
            ],
            [
                'key' => 'timetable',
                'label' => 'My timetable',
                'href' => '/academics/timetable',
                'badge' => null,
                'status' => ($todayPeriods['is_school_day'] ?? true) === false
                    ? (string) ($todayPeriods['note'] ?? 'No lessons today')
                    : ($lessons === 0
                        ? 'No lessons today'
                        : $lessons.' lesson'.($lessons === 1 ? '' : 's').' today'
                            .($covering > 0 ? ' · '.$covering.' covering' : '')),
            ],
        ];

        $unread = app(ListMessageInboxAction::class)->unreadCount($userId);
        $tiles[] = [
            'key' => 'messages',
            'label' => 'Messages',
            'href' => '/portal/messages',
            'badge' => $unread ?: null,
            'status' => $unread === 0 ? 'Nothing new' : $unread.' unread',
        ];

        // E4: the badge counts only urgent notices, because there is no
        // per-user read state — a badge counting everything would never clear.
        $notices = app(ListAnnouncementsForUserAction::class)->summary($userId, $roleNames);
        $tiles[] = [
            'key' => 'announcements',
            'label' => 'Noticeboard',
            'href' => '/portal/announcements',
            'badge' => $notices['urgent'] ?: null,
            'status' => $notices['total'] === 0
                ? 'Nothing posted'
                : $notices['total'].' notice'.($notices['total'] === 1 ? '' : 's'),
        ];

        $tiles[] = [
            'key' => 'materials',
            'label' => 'Materials',
            'href' => '/academics/materials',
            'badge' => null,
            'status' => 'Reusable library',
        ];
        $tiles[] = [
            'key' => 'plans',
            'label' => 'Plans',
            'href' => '/academics/plans',
            'badge' => null,
            'status' => 'Topics and adherence',
        ];

        $prayer = app(ComposeDashboardPrayerAction::class)->execute();
        $next = $prayer['currentPrayer']['prayer'] ?? null;
        if ($next !== null) {
            $tiles[] = [
                'key' => 'prayer',
                'label' => 'Prayer times',
                'href' => '/prayer-times',
                'badge' => null,
                'status' => (string) $next,
            ];
        }

        return $tiles;
    }
}
