<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Settings\Actions\GetSettingAction;
use Illuminate\Support\Collection;

class ComposeCourseConversionSignalsAction
{
    /**
     * Occupying statuses match Course::available_seats / isFull() so the public
     * page never advertises a seat checkout would refuse.
     *
     * @var list<string>
     */
    private const OCCUPYING = ['pending', 'active'];

    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $courseId): ?array
    {
        $course = Course::query()->find($courseId);

        return $course === null ? null : $this->present($course, $this->occupyingCount([$courseId])[$courseId] ?? 0);
    }

    /**
     * @param  Collection<int, Course>|list<int>  $courses
     * @return array<int, array<string, mixed>>
     */
    public function forCourses(Collection|array $courses): array
    {
        $models = $courses instanceof Collection
            ? $courses
            : Course::query()->whereIn('id', $courses)->get();
        $ids = $models->pluck('id')->all();
        $counts = $this->occupyingCount($ids);
        $out = [];
        foreach ($models as $course) {
            $out[$course->id] = $this->present($course, $counts[$course->id] ?? 0);
        }

        return $out;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, int>
     */
    private function occupyingCount(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return CourseEnrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', self::OCCUPYING)
            ->selectRaw('course_id, COUNT(*) as aggregate')
            ->groupBy('course_id')
            ->pluck('aggregate', 'course_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return array{
     *     seats_total: ?int,
     *     seats_remaining: ?int,
     *     seats_label: ?string,
     *     seats_tone: ?string,
     *     deadline: ?string,
     *     deadline_days: ?int,
     *     deadline_badge: bool,
     *     deadline_expired: bool,
     *     hide_from_open_listing: bool,
     *     early_bird: ?array{amount: float, currency: string, ends_at: string, normal_amount: float}
     * }
     */
    private function present(Course $course, int $occupying): array
    {
        $hideAbove = (int) app(GetSettingAction::class)->execute('conversion.seats_hide_above', 20);
        $exactAtOrBelow = (int) app(GetSettingAction::class)->execute('conversion.seats_exact_at_or_below', 10);
        $badgeDays = (int) app(GetSettingAction::class)->execute('conversion.deadline_badge_days', 14);

        $total = $course->seats === null ? null : (int) $course->seats;
        $remaining = $total === null ? null : max(0, $total - $occupying);

        $label = null;
        $tone = null;
        if ($remaining !== null) {
            if ($remaining === 0) {
                $label = 'Full — join waiting list';
                $tone = 'full';
            } elseif ($remaining <= $exactAtOrBelow) {
                $label = $remaining.' '.($remaining === 1 ? 'seat left' : 'seats left');
                $tone = 'exact';
            } elseif ($remaining <= $hideAbove) {
                $label = 'Limited seats';
                $tone = 'limited';
            }
        }

        $today = now()->timezone(config('app.timezone'))->startOfDay();
        $deadline = $course->enrollment_deadline;
        $deadlineDate = $deadline?->toDateString();
        $days = null;
        $expired = false;
        if ($deadlineDate !== null) {
            $end = $deadline->copy()->timezone(config('app.timezone'))->startOfDay();
            $days = (int) $today->diffInDays($end, false);
            $expired = $days < 0;
        }

        return [
            'seats_total' => $total,
            'seats_remaining' => $remaining,
            'seats_label' => $label,
            'seats_tone' => $tone,
            'deadline' => $deadlineDate,
            'deadline_days' => $expired ? null : $days,
            'deadline_badge' => $deadlineDate !== null && ! $expired && $days <= $badgeDays,
            'deadline_expired' => $expired,
            'hide_from_open_listing' => $expired && $course->status === 'open',
            'early_bird' => $this->earlyBird($course, $today),
        ];
    }

    /**
     * Early-bird lives on existing courses.meta (no new columns). Shown only when
     * active, dated, and cheaper than the listed fee.
     *
     * @return array{amount: float, currency: string, ends_at: string, normal_amount: float}|null
     */
    private function earlyBird(Course $course, $today): ?array
    {
        $meta = is_array($course->meta) ? $course->meta : [];
        $active = filter_var($meta['early_bird_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $amount = isset($meta['early_bird_amount']) ? (float) $meta['early_bird_amount'] : 0.0;
        $endsAt = isset($meta['early_bird_ends_at']) ? (string) $meta['early_bird_ends_at'] : '';
        $normal = (float) ($course->fee ?? 0);

        if (! $active || $amount <= 0 || $endsAt === '' || $normal <= 0 || $amount >= $normal) {
            return null;
        }

        try {
            $end = \Illuminate\Support\Carbon::parse($endsAt)->timezone(config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($end->lt($today)) {
            return null;
        }

        return [
            'amount' => $amount,
            'currency' => (string) ($meta['early_bird_currency'] ?? $course->registration_fee_currency ?? 'MVR'),
            'ends_at' => $end->toDateString(),
            'normal_amount' => $normal,
        ];
    }
}
