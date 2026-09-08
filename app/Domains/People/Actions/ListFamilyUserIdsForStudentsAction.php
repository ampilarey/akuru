<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

/**
 * The login accounts behind a set of students: their guardians, themselves, or
 * both.
 *
 * Lives in People because students and guardians are People's data — Academics
 * knows which pupils are on a roster, not who is allowed to speak for them.
 *
 * Only accounts that exist are returned. A pupil with no login and a guardian
 * who has never been given one are silently absent rather than counted as
 * reachable: a broadcast that claims 30 recipients and delivers to 11 is worse
 * than one that says 11.
 */
class ListFamilyUserIdsForStudentsAction
{
    public const AUDIENCE_GUARDIANS = 'guardians';

    public const AUDIENCE_STUDENTS = 'students';

    public const AUDIENCE_BOTH = 'both';

    /**
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    public function execute(array $studentIds, string $audience = self::AUDIENCE_GUARDIANS): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return [];
        }

        $userIds = [];

        if ($audience !== self::AUDIENCE_STUDENTS) {
            $userIds = DB::table('guardian_student')
                ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
                ->whereIn('guardian_student.student_id', $ids)
                ->whereNotNull('parent_guardians.user_id')
                ->pluck('parent_guardians.user_id')
                ->all();
        }

        if ($audience !== self::AUDIENCE_GUARDIANS) {
            $userIds = array_merge($userIds, DB::table('students')
                ->whereIn('id', $ids)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all());
        }

        return array_values(array_unique(array_map('intval', $userIds)));
    }

    /**
     * How many accounts a broadcast would actually reach, per audience.
     *
     * The compose screen shows this before sending, because "message the class"
     * means nothing without knowing how many people that is — and because the
     * reply policy flips at six recipients, so the sender should be able to see
     * which side of that line they are on.
     *
     * @param  list<int>  $studentIds
     * @return array{guardians: int, students: int, both: int}
     */
    public function counts(array $studentIds): array
    {
        return [
            'guardians' => count($this->execute($studentIds, self::AUDIENCE_GUARDIANS)),
            'students' => count($this->execute($studentIds, self::AUDIENCE_STUDENTS)),
            'both' => count($this->execute($studentIds, self::AUDIENCE_BOTH)),
        ];
    }
}
