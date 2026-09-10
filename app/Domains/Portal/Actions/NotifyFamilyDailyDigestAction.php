<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Academics\Actions\ListAnnouncementsForUserAction;
use App\Domains\Academics\Actions\ListDayTimetableForStudentAction;
use App\Domains\Academics\Actions\ListHomeworkForStudentAction;
use App\Domains\Academics\Actions\ResolveNotificationSettingsAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One evening summary per family: tomorrow's lessons, homework coming due, and
 * notices they have not read.
 *
 * EduPage pairs its notification centre with a digest that says what is due
 * tomorrow, and it is the most parent-friendly idea in their design — it turns
 * a system parents must remember to check into one that reaches them.
 *
 * Every ingredient already exists and is already visible in the portal:
 * E1's next-school-day reader, E3a's homework list, E4's audience-targeted
 * notices, and E22a's notification centre to read the result in. This composes
 * them; it computes nothing new, so the digest cannot disagree with the pages
 * it summarises.
 *
 * Follows the admin digest exactly: **off by default**, once per recipient per
 * day via the cache, in-app only.
 */
class NotifyFamilyDailyDigestAction
{
    public function execute(): int
    {
        if (! app(ResolveNotificationSettingsAction::class)->execute()['family_daily_digest']) {
            return 0;
        }

        $today = now()->timezone(config('app.timezone'))->toDateString();
        $sent = 0;

        foreach ($this->familyUserIds() as $userId) {
            $key = 'family-digest:'.$userId.':'.$today;
            if (! Cache::add($key, true, now()->endOfDay())) {
                continue;
            }

            $summary = $this->summaryFor($userId);

            // A digest that says "nothing" every evening teaches people to
            // ignore it, and the next one that matters goes unread too.
            if ($summary === null) {
                Cache::forget($key);

                continue;
            }

            app(SendUserNotificationAction::class)->execute(
                $userId,
                trans('notifications.family_digest.title', ['date' => $summary['date']]),
                trans('notifications.family_digest.body', [
                    'lessons' => $summary['lessons_line'],
                    'homework' => $summary['homework'],
                    'notices' => $summary['notices'],
                ]),
                [
                    // Its own category, not 'message': the whole point of
                    // E22c is that a parent can mute the nightly summary
                    // without muting a teacher writing to them.
                    'category' => 'digest',
                    'href' => '/portal/home',
                    'date' => $summary['date'],
                ],
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Null when there is genuinely nothing worth an evening message.
     *
     * @return ?array{date: string, lessons_line: string, homework: int, notices: int}
     */
    private function summaryFor(int $userId): ?array
    {
        $studentIds = $this->studentIdsFor($userId);
        if ($studentIds === []) {
            return null;
        }

        $tomorrow = now()->timezone(config('app.timezone'))->addDay()->toDateString();
        $timetable = app(ListDayTimetableForStudentAction::class);
        $homeworkReader = app(ListHomeworkForStudentAction::class);

        $periods = [];
        $homework = 0;
        foreach ($studentIds as $studentId) {
            $day = $timetable->execute($studentId, $tomorrow);
            $periods = array_merge($periods, $day['periods']);
            $homework += $homeworkReader->outstandingCount($studentId);
        }

        $notices = app(ListAnnouncementsForUserAction::class)
            ->summary($userId, $this->roleNamesFor($userId))['total'];

        if ($periods === [] && $homework === 0 && $notices === 0) {
            return null;
        }

        $first = $periods[0] ?? null;

        return [
            'date' => $tomorrow,
            'lessons_line' => $periods === []
                ? trans('notifications.family_digest.no_lessons')
                : trans('notifications.family_digest.lessons', [
                    'count' => count($periods),
                    'first' => trim(($first['subject'] ?? '').' '.($first['starts_at'] ?? '')),
                ]),
            'homework' => $homework,
            'notices' => $notices,
        ];
    }

    /**
     * Accounts that belong to a family: pupils with a login, and guardians.
     *
     * @return list<int>
     */
    private function familyUserIds(): array
    {
        $ids = DB::table('students')->whereNotNull('user_id')->pluck('user_id')
            ->merge(DB::table('parent_guardians')->whereNotNull('user_id')->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function studentIdsFor(int $userId): array
    {
        $ids = [];

        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $ids[] = (int) $self['id'];
        }

        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $ids[] = (int) $child->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Role names without importing Identity\Models (rule 3) — the same reason
     * ResolveDashboardLandingAction takes them as an array.
     *
     * @return list<string>
     */
    private function roleNamesFor(int $userId): array
    {
        // The morph value is derived, not hardcoded to 'user': the alias comes
        // from config/morph-map.php and getMorphClass() is what Spatie stored.
        $userModel = config('auth.providers.users.model');
        $morph = (new $userModel)->getMorphClass();

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $morph)
            ->pluck('roles.name')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }
}
