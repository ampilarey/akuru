<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

class SearchRosterCandidatesAction
{
    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     student_number: ?string,
     *     date_of_birth: ?string,
     *     national_id: ?string,
     *     current_class: ?string,
     *     indistinguishable: bool
     * }>
     */
    public function execute(string $query, int $limit = 25): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%'.$query.'%';

        $rows = DB::table('students')
            ->leftJoin('classes', 'classes.id', '=', 'students.class_id')
            ->select([
                'students.id',
                'students.student_id',
                'students.first_name',
                'students.last_name',
                'students.national_id',
                'students.date_of_birth',
                'classes.name as class_name',
                'classes.section as class_section',
            ])
            ->where(function ($inner) use ($like): void {
                $inner->where('students.first_name', 'like', $like)
                    ->orWhere('students.last_name', 'like', $like)
                    ->orWhereRaw("concat(students.first_name, ' ', students.last_name) like ?", [$like])
                    ->orWhere('students.first_name_dhivehi', 'like', $like)
                    ->orWhere('students.last_name_dhivehi', 'like', $like)
                    ->orWhere('students.first_name_arabic', 'like', $like)
                    ->orWhere('students.last_name_arabic', 'like', $like)
                    ->orWhere('students.national_id', 'like', $like)
                    ->orWhere('students.student_id', 'like', $like);
            })
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->orderBy('students.id')
            ->limit($limit)
            ->get();

        $mapped = $rows->map(function (object $student): array {
            $name = trim($student->first_name.' '.$student->last_name);
            $class = trim(($student->class_name ?? '').' '.($student->class_section ?? ''));
            $dob = $student->date_of_birth
                ? substr((string) $student->date_of_birth, 0, 10)
                : null;

            return [
                'id' => (int) $student->id,
                'name' => $name,
                'student_number' => $student->student_id,
                'date_of_birth' => $dob,
                'national_id' => $student->national_id,
                'current_class' => $class === '' ? null : $class,
                'identity_key' => mb_strtolower(implode('|', [
                    $name,
                    (string) ($student->student_id ?? ''),
                    (string) $dob,
                    (string) ($student->national_id ?? ''),
                    $class,
                ])),
            ];
        });

        $counts = $mapped->countBy('identity_key');

        return $mapped
            ->map(function (array $row) use ($counts): array {
                $row['indistinguishable'] = ($counts[$row['identity_key']] ?? 0) > 1;
                unset($row['identity_key']);

                return $row;
            })
            ->values()
            ->all();
    }
}
