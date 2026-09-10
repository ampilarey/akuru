<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\Announcement;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * E4 — the noticeboard, read by the people it was aimed at.
 *
 * `announcements` has carried `target_audience` and `target_classes` since
 * 2025 and the admin form writes both. **Nothing has ever read them.** The one
 * apparent reader, `EnhancedDashboardController::getRecentAnnouncements()`,
 * is a stub returning `0`, and families land on `/portal/home` and never reach
 * that controller anyway — so no family has ever seen an announcement.
 *
 * Targeting is honoured as written, and the two "unset" cases mean *everyone*
 * rather than *no one*: an admin who leaves the audience blank means the whole
 * school, and treating a blank as a filter would silently hide every existing
 * row.
 */
class ListAnnouncementsForUserAction
{
    /** Role name → the audience key an author would have ticked. */
    private const ROLE_AUDIENCE = [
        'student' => 'students',
        'parent' => 'parents',
        'teacher' => 'teachers',
        'admin' => 'teachers',
        'headmaster' => 'teachers',
        'supervisor' => 'teachers',
        'super_admin' => 'teachers',
    ];

    /**
     * @param  list<string>  $roleNames
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $userId, array $roleNames): Collection
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $rows = Announcement::query()
            ->where('is_published', true)
            ->whereDate('publish_date', '<=', $today->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', $today->toDateString()))
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $audiences = $this->audiencesFor($roleNames);
        $classIds = $this->classIdsFor($userId, $roleNames);

        return $rows
            ->filter(fn (Announcement $row): bool => $this->matchesAudience($row, $audiences))
            ->filter(fn (Announcement $row): bool => $this->matchesClass($row, $classIds))
            ->map(fn (Announcement $row): array => [
                'id' => (int) $row->id,
                'title' => $this->localised($row, 'title'),
                'content' => $this->localised($row, 'content'),
                'type' => (string) $row->type,
                'priority' => (string) $row->priority,
                'published_on' => $row->publish_date?->toDateString(),
                'expires_on' => $row->expiry_date?->toDateString(),
                'is_urgent' => in_array($row->priority, ['urgent', 'high'], true),
                'has_attachment' => ! empty($row->attachment),
            ])
            ->values();
    }

    /**
     * Badge and status for the E1 tile.
     *
     * The badge counts only urgent and high posts, because it has to be able
     * to clear: there is no per-user read state, so a badge counting everything
     * would sit there forever. An urgent notice expires; the badge goes with it.
     *
     * @param  list<string>  $roleNames
     * @return array{total: int, urgent: int}
     */
    public function summary(int $userId, array $roleNames): array
    {
        $rows = $this->execute($userId, $roleNames);

        return [
            'total' => $rows->count(),
            'urgent' => $rows->filter(fn (array $row): bool => $row['is_urgent'])->count(),
        ];
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    private function audiencesFor(array $roleNames): array
    {
        $audiences = ['all'];
        foreach ($roleNames as $role) {
            if (isset(self::ROLE_AUDIENCE[$role])) {
                $audiences[] = self::ROLE_AUDIENCE[$role];
            }
        }

        return array_values(array_unique($audiences));
    }

    /**
     * @param  list<string>  $audiences
     */
    private function matchesAudience(Announcement $row, array $audiences): bool
    {
        $target = $row->target_audience;

        // Unset means the whole school, not nobody.
        if (! is_array($target) || $target === []) {
            return true;
        }

        return array_intersect($target, $audiences) !== [];
    }

    /**
     * @param  list<int>  $classIds
     */
    private function matchesClass(Announcement $row, array $classIds): bool
    {
        $target = $row->target_classes;

        if (! is_array($target) || $target === []) {
            return true;
        }

        return array_intersect(array_map('intval', $target), $classIds) !== [];
    }

    /**
     * Every class this person is connected to: the ones they teach, their own,
     * and their children's.
     *
     * @param  list<string>  $roleNames
     * @return list<int>
     */
    private function classIdsFor(int $userId, array $roleNames): array
    {
        $classIds = app(ListClassesTaughtByUserAction::class)
            ->execute($userId)
            ->pluck('id')
            ->all();

        $studentIds = [];
        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $studentIds[] = (int) $self['id'];
        }
        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $studentIds[] = (int) $child->id;
        }

        if ($studentIds !== []) {
            $classIds = array_merge($classIds, DB::table('class_student')
                ->whereIn('student_id', $studentIds)
                ->where('status', ClassStudentStatus::Active->value)
                ->pluck('class_id')
                ->map(fn ($id): int => (int) $id)
                ->all());
        }

        return array_values(array_unique(array_map('intval', $classIds)));
    }

    /**
     * The reader's language where the author supplied it, English otherwise.
     * A blank Dhivehi title must not blank the notice.
     */
    private function localised(Announcement $row, string $field): string
    {
        $suffix = match (app()->getLocale()) {
            'dv' => '_dhivehi',
            'ar' => '_arabic',
            default => '',
        };

        $value = $suffix === '' ? null : trim((string) ($row->{$field.$suffix} ?? ''));

        return $value !== null && $value !== ''
            ? $value
            : (string) $row->{$field};
    }
}
