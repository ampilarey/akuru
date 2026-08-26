<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\IssuedCertificate;
use Illuminate\Support\Facades\DB;

class VerifyIssuedCertificateAction
{
    /**
     * Public face only. Never include student ids, contacts, or other records.
     *
     * @return array{
     *     valid: bool,
     *     revoked: bool,
     *     certificate_number: string,
     *     student_name: string,
     *     course_name: string,
     *     offering_name: string,
     *     completion_date: string|null,
     *     grade: string|null,
     *     institute: string,
     *     template_name: string
     * }|null
     */
    public function execute(string $publicId): ?array
    {
        $token = trim($publicId);
        if ($token === '' || strlen($token) > 32 || ! preg_match('/^[0-9A-HJKMNP-TV-Z]+$/i', $token)) {
            return null;
        }

        $row = IssuedCertificate::query()->where('public_id', $token)->first();
        if ($row === null) {
            return null;
        }

        $student = DB::table('students')->where('id', $row->student_id)->first(['first_name', 'last_name']);
        $courseName = $row->course_id
            ? (string) (DB::table('courses')->where('id', $row->course_id)->value('title') ?? '')
            : '';
        $offeringName = $row->course_offering_id
            ? (string) (DB::table('course_offerings')->where('id', $row->course_offering_id)->value('title') ?? '')
            : '';
        $templateName = (string) (DB::table('certificate_templates')->where('id', $row->certificate_template_id)->value('name') ?? '');

        return [
            'valid' => $row->revoked_at === null,
            'revoked' => $row->revoked_at !== null,
            'certificate_number' => $row->certificate_number,
            'student_name' => $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '',
            'course_name' => $courseName,
            'offering_name' => $offeringName,
            'completion_date' => $row->completion_date?->toDateString(),
            'grade' => $row->grade,
            'institute' => 'Akuru Institute',
            'template_name' => $templateName,
        ];
    }
}
