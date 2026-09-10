<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Announcement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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

        $context = app(ResolveAudienceContextAction::class)->execute($userId, $roleNames);

        return $rows
            ->filter(fn (Announcement $row): bool => app(ResolveAudienceContextAction::class)
                ->matches($row->target_audience, $row->target_classes, $context))
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
