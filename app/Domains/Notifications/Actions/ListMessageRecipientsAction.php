<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Academics\Actions\ListTeacherContactsForStudentAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Support\Collection;

/**
 * Who a portal user may start a thread with.
 *
 * E2a deliberately answers only the family → teacher direction. Staff get the
 * inbox and the reply, which is the whole loop from their side; letting a
 * teacher open a thread with a class needs the fan-out and audience rules that
 * belong to the next slice, not a wider directory bolted on here.
 *
 * Cross-domain reads go through other domains' Actions (rule 3).
 */
class ListMessageRecipientsAction
{
    /**
     * @return Collection<int, array{user_id: int, name: string, context: string}>
     */
    public function execute(int $userId): Collection
    {
        $studentIds = [];
        $labels = [];

        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $studentIds[] = (int) $self['id'];
            $labels[(int) $self['id']] = trim($self['first_name'].' '.$self['last_name']);
        }

        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $studentIds[] = (int) $child->id;
            $labels[(int) $child->id] = trim(($child->first_name ?? '').' '.($child->last_name ?? ''));
        }

        $studentIds = array_values(array_unique($studentIds));
        if ($studentIds === []) {
            return collect();
        }

        $teachers = app(ListTeacherContactsForStudentAction::class);

        // Grouped per child so a parent of two can tell which class a teacher
        // belongs to; the same teacher for both children collapses to one row
        // rather than appearing twice with no way to tell them apart.
        $rows = collect();
        foreach ($studentIds as $studentId) {
            foreach ($teachers->execute([$studentId]) as $teacher) {
                $rows->push([
                    'user_id' => $teacher['user_id'],
                    'name' => $teacher['name'],
                    'context' => $labels[$studentId] ?? '',
                ]);
            }
        }

        return $rows
            ->groupBy('user_id')
            ->map(fn (Collection $group): array => [
                'user_id' => (int) $group->first()['user_id'],
                'name' => (string) $group->first()['name'],
                'context' => $group->pluck('context')->filter()->unique()->implode(', '),
            ])
            ->sortBy('name')
            ->values();
    }

    public function allows(int $userId, int $recipientUserId): bool
    {
        return $this->execute($userId)->contains(
            fn (array $row): bool => $row['user_id'] === $recipientUserId
        );
    }
}
