<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Support\Facades\DB;

/**
 * Who a person is, for the purposes of "is this aimed at me?".
 *
 * Extracted from E4's announcement reader because E6's forms need exactly the
 * same question answered, and two copies of audience matching is how the
 * noticeboard and the sign-up sheet end up disagreeing about who is in Grade 5.
 *
 * Returns the audience keys an author would have ticked, and every class the
 * person is connected to — the ones they teach, their own, and their children's.
 */
class ResolveAudienceContextAction
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
     * @return array{audiences: list<string>, class_ids: list<int>}
     */
    public function execute(int $userId, array $roleNames): array
    {
        return [
            'audiences' => $this->audiencesFor($roleNames),
            'class_ids' => $this->classIdsFor($userId),
        ];
    }

    /**
     * Whether a target matches this person.
     *
     * **An unset target means everyone, not no one.** Treating a blank as a
     * filter would silently hide every row written before targeting was read by
     * anything, and it fails in the direction nobody notices.
     *
     * @param  ?array<int, string>  $targetAudience
     * @param  ?array<int, int|string>  $targetClasses
     * @param  array{audiences: list<string>, class_ids: list<int>}  $context
     */
    public function matches(?array $targetAudience, ?array $targetClasses, array $context): bool
    {
        $audienceOk = ! is_array($targetAudience) || $targetAudience === []
            || array_intersect($targetAudience, $context['audiences']) !== [];

        $classOk = ! is_array($targetClasses) || $targetClasses === []
            || array_intersect(array_map('intval', $targetClasses), $context['class_ids']) !== [];

        return $audienceOk && $classOk;
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
     * @return list<int>
     */
    private function classIdsFor(int $userId): array
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
}
