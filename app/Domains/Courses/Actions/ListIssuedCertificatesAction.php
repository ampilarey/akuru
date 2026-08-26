<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\IssuedCertificate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListIssuedCertificatesAction
{
    /**
     * @param  array{academic_year_id?: int|null, student_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $query = IssuedCertificate::query()->orderByDesc('id');
        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }
        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }

        $rows = $query->get();
        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->all() ?: [0])
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');
        $courses = DB::table('courses')
            ->whereIn('id', $rows->pluck('course_id')->filter()->all() ?: [0])
            ->pluck('title', 'id');
        $offerings = DB::table('course_offerings')
            ->whereIn('id', $rows->pluck('course_offering_id')->filter()->all() ?: [0])
            ->pluck('title', 'id');
        $templates = DB::table('certificate_templates')
            ->whereIn('id', $rows->pluck('certificate_template_id')->all() ?: [0])
            ->pluck('name', 'id');

        return $rows->map(function (IssuedCertificate $row) use ($students, $courses, $offerings, $templates): array {
            $student = $students->get($row->student_id);

            return [
                'id' => $row->id,
                'public_id' => $row->public_id,
                'certificate_number' => $row->certificate_number,
                'student_name' => $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '',
                'template' => $templates[$row->certificate_template_id] ?? '',
                'course_name' => $courses[$row->course_id] ?? '',
                'offering_name' => $offerings[$row->course_offering_id] ?? '',
                'completion_date' => $row->completion_date?->toDateString(),
                'issued_at' => $row->issued_at?->toDateTimeString(),
                'grade' => $row->grade,
                'status' => $row->revoked_at !== null ? 'revoked' : 'issued',
                'verify_url' => url('/verify/certificates/'.$row->public_id),
                'document_id' => $row->document_id,
                'revoked' => $row->revoked_at !== null,
            ];
        })->values();
    }
}
