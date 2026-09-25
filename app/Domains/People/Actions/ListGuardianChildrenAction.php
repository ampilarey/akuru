<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Support\VerifiedGuardianLink;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A guardian's children — the list some thirty-five family-facing callers
 * use to decide whose invoice, absence note, report card or homework a
 * parent may see or act on.
 *
 * Item 13 (2026-09-25): that list holds **verified links only**. A link the
 * parent made for themselves on the public registration form is not in it
 * until the office verifies it on the student's profile, so a stranger who
 * registered a "child" under a real pupil's ID card number authorises
 * nothing. `executePendingForGuardianUserId()` is the one deliberately
 * separate view — the name on the link and nothing else — so *My children*
 * can say *awaiting the office* instead of showing an empty page.
 */
class ListGuardianChildrenAction
{
    /**
     * @return Collection<int, object>
     */
    public function executeForGuardianUserId(int $userId): Collection
    {
        return VerifiedGuardianLink::scopeQuery($this->query($userId))->get();
    }

    /**
     * Links the office has not verified yet: the child's name and the
     * relationship the parent claimed, and nothing behind the link.
     *
     * @return Collection<int, object>
     */
    public function executePendingForGuardianUserId(int $userId): Collection
    {
        return $this->query($userId)
            ->where('guardian_student.verification_status', '!=', 'verified')
            ->get()
            ->map(fn (object $row): object => (object) [
                'id' => (int) $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'relationship' => $row->relationship,
                'verification_status' => $row->verification_status,
            ]);
    }

    private function query(int $userId): Builder
    {
        $guardianIds = DB::table('parent_guardians')->where('user_id', $userId)->pluck('id');

        return DB::table('guardian_student')
            ->join('students', 'students.id', '=', 'guardian_student.student_id')
            ->whereIn('guardian_student.guardian_id', $guardianIds->isEmpty() ? [0] : $guardianIds->all())
            ->select([
                'students.id',
                'students.student_id',
                'students.first_name',
                'students.last_name',
                'students.status',
                'guardian_student.relationship',
                'guardian_student.is_primary',
                'guardian_student.verification_status',
            ])
            ->orderBy('students.last_name');
    }
}
