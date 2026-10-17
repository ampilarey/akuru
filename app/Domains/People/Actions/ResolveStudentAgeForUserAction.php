<?php

namespace App\Domains\People\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL1: whether an account is an Akuru student — a school pupil or
 * a course learner, one `students` row either way (rule 11) — and how old
 * they are, for a domain that may not read `students` itself (rule 3). An
 * age the record cannot tell is null, which a caller treats as under 18.
 */
class ResolveStudentAgeForUserAction
{
    /**
     * @return array{student_id: int, first_name: string, last_name: string, age: ?int}|null
     */
    public function execute(int $userId): ?array
    {
        $row = DB::table('students')->where('user_id', $userId)->orderBy('id')->first(['id', 'first_name', 'last_name', 'date_of_birth']);
        if ($row === null) {
            return null;
        }

        return [
            'student_id' => (int) $row->id,
            'first_name' => (string) $row->first_name,
            'last_name' => (string) $row->last_name,
            'age' => $row->date_of_birth ? Carbon::parse($row->date_of_birth)->age : null,
        ];
    }
}
