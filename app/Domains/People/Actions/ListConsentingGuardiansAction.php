<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Support\VerifiedGuardianLink;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL1: who may consent for a child — the guardians the office has
 * verified for that student (item 13), and only those with an account to
 * sign in and say yes. An unverified link names nobody.
 */
class ListConsentingGuardiansAction
{
    /**
     * @return list<int> the guardians' user ids
     */
    public function execute(int $studentId): array
    {
        $query = DB::table('guardian_student')
            ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
            ->where('guardian_student.student_id', $studentId)
            ->whereNotNull('parent_guardians.user_id');

        return VerifiedGuardianLink::scopeQuery($query)
            ->distinct()
            ->orderBy('parent_guardians.user_id')
            ->pluck('parent_guardians.user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
