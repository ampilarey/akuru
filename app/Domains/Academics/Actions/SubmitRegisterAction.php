<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Academics\Models\PlanTopic;
use Illuminate\Validation\ValidationException;

class SubmitRegisterAction
{
    public function __construct(
        private ResolveRegisterLockDaysAction $lockDays,
        private ResolveTeacherIdForUserAction $teacherId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(LessonLog $log, array $data, int $actorUserId, bool $canManage = false): LessonLog
    {
        $this->assertCanEdit($log, $actorUserId, $canManage);

        $topicId = isset($data['plan_topic_id']) && $data['plan_topic_id'] !== '' && $data['plan_topic_id'] !== null
            ? (int) $data['plan_topic_id']
            : null;
        $summary = trim((string) ($data['taught_summary'] ?? ''));

        if ($topicId === null && $summary === '') {
            throw ValidationException::withMessages([
                'taught_summary' => 'Pick a plan topic or enter what was taught.',
            ]);
        }

        if ($topicId !== null) {
            $topic = PlanTopic::query()->find($topicId);
            if ($topic === null) {
                throw ValidationException::withMessages([
                    'plan_topic_id' => 'That plan topic does not exist.',
                ]);
            }

            $topic->is_completed = true;
            $topic->save();

            if ($summary === '') {
                $summary = $topic->title;
            }
        }

        $log->fill([
            'plan_topic_id' => $topicId,
            'taught_summary' => $summary,
            'homework' => $this->nullableString($data['homework'] ?? null),
            'materials' => $this->materials($data['materials'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'status' => LessonLogStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $log->save();

        return $log->refresh();
    }

    private function assertCanEdit(LessonLog $log, int $actorUserId, bool $canManage): void
    {
        if (! $canManage) {
            $teacherId = $this->teacherId->execute($actorUserId);
            if ($teacherId === null || (int) $log->teacher_id !== $teacherId) {
                throw ValidationException::withMessages([
                    'teacher_id' => 'You can only submit your own registers.',
                ]);
            }
        }

        if ($log->status === LessonLogStatus::Locked) {
            throw ValidationException::withMessages([
                'status' => 'This register is locked.',
            ]);
        }

        $cutoff = now()->timezone(config('app.timezone'))->startOfDay()
            ->subDays($this->lockDays->execute());
        $grace = $log->unlocked_until !== null && $log->unlocked_until->isFuture();

        if ($log->date?->lte($cutoff) && ! $grace) {
            throw ValidationException::withMessages([
                'status' => 'This register is locked.',
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>|null
     */
    private function materials(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $items = array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                $value,
            ), fn (string $item) => $item !== ''));

            return $items === [] ? null : $items;
        }

        $items = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $value),
        ), fn (string $item) => $item !== ''));

        return $items === [] ? null : $items;
    }
}
