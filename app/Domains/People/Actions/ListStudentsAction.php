<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListStudentsAction
{
    /**
     * @param  array{search?: string, status?: string, class_id?: int, year?: string, awaiting_verification?: mixed}  $filters
     * @return Collection<int, object>
     */
    public function execute(array $filters = []): Collection
    {
        $query = DB::table('students')
            ->leftJoin('classes', 'classes.id', '=', 'students.class_id')
            ->select([
                'students.id',
                'students.student_id',
                'students.first_name',
                'students.last_name',
                'students.first_name_dhivehi',
                'students.last_name_dhivehi',
                'students.first_name_arabic',
                'students.last_name_arabic',
                'students.national_id',
                'students.status',
                'students.class_id',
                'students.date_of_birth',
                'classes.name as class_name',
                'classes.section as class_section',
            ])
            ->orderBy('students.last_name')
            ->orderBy('students.first_name');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('students.first_name', 'like', $like)
                    ->orWhere('students.last_name', 'like', $like)
                    // A whole name, typed the way people say it. Each part
                    // matched on its own, so "Fatima Yoosuf" found nobody
                    // while "Fatima" found her (sign-up walk, STATUS §5fq).
                    ->orWhereRaw("CONCAT(students.first_name, ' ', students.last_name) like ?", [$like])
                    ->orWhere('students.first_name_dhivehi', 'like', $like)
                    ->orWhere('students.last_name_dhivehi', 'like', $like)
                    ->orWhere('students.first_name_arabic', 'like', $like)
                    ->orWhere('students.last_name_arabic', 'like', $like)
                    ->orWhere('students.national_id', 'like', $like)
                    ->orWhere('students.student_id', 'like', $like);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('students.status', $filters['status']);
        }

        if (! empty($filters['class_id'])) {
            $query->where('students.class_id', (int) $filters['class_id']);
        }

        // Item 13: pupils with a parent link the office has not verified yet.
        if (! empty($filters['awaiting_verification'])) {
            $query->whereIn('students.id', $this->awaitingVerification());
        }

        return $query->get();
    }

    /** How many pupils have a parent link awaiting the office's check. */
    public function countAwaitingVerification(): int
    {
        return $this->awaitingVerification()->count();
    }

    private function awaitingVerification(): \Illuminate\Database\Query\Builder
    {
        return DB::table('guardian_student')
            ->where('verification_status', 'unverified')
            ->select('student_id')
            ->distinct();
    }
}
