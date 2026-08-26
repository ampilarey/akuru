<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\IssuedCertificate;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Domains\Offerings\Actions\GetOfferingAttendancePercentAction;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Services\StudentNumberQr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueCertificateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $actorId = null): IssuedCertificate
    {
        $template = CertificateTemplate::query()
            ->where('active', true)
            ->findOrFail((int) ($data['certificate_template_id'] ?? 0));

        $studentId = (int) ($data['student_id'] ?? 0);
        if ($studentId < 1) {
            throw ValidationException::withMessages(['student_id' => 'Student is required.']);
        }

        $courseId = $this->nullableId($data['course_id'] ?? $template->course_id);
        $offeringId = $this->nullableId($data['course_offering_id'] ?? $data['offering_id'] ?? null);
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($yearId < 1) {
            throw ValidationException::withMessages(['academic_year_id' => 'Academic year is required.']);
        }

        $eligibility = app(CheckCertificateEligibilityAction::class)->execute(
            $template,
            $studentId,
            $courseId,
            $offeringId,
            [
                'teacher_approved' => (bool) ($data['teacher_approved'] ?? false),
                'assessment_id' => $this->nullableId($data['assessment_id'] ?? null),
            ],
        );
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'student_id' => implode(' ', $eligibility['reasons']) ?: 'Not eligible.',
            ]);
        }

        $duplicate = IssuedCertificate::query()
            ->where('student_id', $studentId)
            ->where('certificate_template_id', $template->id)
            ->where('course_id', $courseId)
            ->where('course_offering_id', $offeringId)
            ->whereNull('revoked_at')
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['student_id' => 'This certificate is already issued.']);
        }

        $enrollment = CourseEnrollment::query()
            ->where('unified_student_id', $studentId)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($offeringId, fn ($query) => $query->where('course_offering_id', $offeringId))
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderByDesc('id')
            ->first();

        $student = DB::table('students')->where('id', $studentId)->first();
        if ($student === null) {
            throw ValidationException::withMessages(['student_id' => 'Student not found.']);
        }

        $courseTitle = $courseId
            ? (string) (Course::query()->where('id', $courseId)->value('title') ?? '')
            : '';
        $offeringTitle = $offeringId
            ? (string) (DB::table('course_offerings')->where('id', $offeringId)->value('title') ?? '')
            : '';

        $attendance = $offeringId
            ? app(GetOfferingAttendancePercentAction::class)->execute($offeringId, $studentId)
            : null;

        $publicId = (string) Str::ulid();
        $number = 'AKU-'.now()->year.'-'.strtoupper(Str::random(6));
        $completionDate = (string) ($data['completion_date'] ?? now()->toDateString());
        $grade = trim((string) ($data['grade'] ?? $data['grade_label'] ?? '')) ?: null;
        $studentName = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));
        $verifyUrl = url('/verify/certificates/'.$publicId);
        $face = [
            'student_name' => $studentName,
            'course_name' => $courseTitle,
            'offering_name' => $offeringTitle,
            'completion_date' => $completionDate,
            'grade' => $grade,
            'certificate_number' => $number,
            'institute' => 'Akuru Institute',
            'kind' => $template->kind->value,
            'template_name' => $template->name,
            'attendance_percent' => $attendance !== null ? (string) $attendance : '',
        ];

        $issued = IssuedCertificate::query()->create([
            'certificate_template_id' => $template->id,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'course_offering_id' => $offeringId,
            'enrollment_id' => $enrollment?->id,
            'assessment_id' => $this->nullableId($data['assessment_id'] ?? null),
            'academic_year_id' => $yearId,
            'term_id' => $this->nullableId($data['term_id'] ?? null),
            'public_id' => $publicId,
            'certificate_number' => $number,
            'completion_date' => $completionDate,
            'grade' => $grade,
            'attendance_percent' => $attendance,
            'issued_by' => $actorId,
            'issued_at' => now(),
        ]);

        $html = app(DocumentRendererInterface::class)->render('course-certificate', [
            'locale' => 'en',
            'dir' => 'ltr',
            'face' => $face,
            'body_html' => $this->filledBody($template->body_html, $face),
            'qr' => app(StudentNumberQr::class)->svg($verifyUrl),
            'verify_url' => $verifyUrl,
        ]);

        $document = app(StoreGeneratedDocumentAction::class)->execute(
            'issued_certificate',
            $issued->id,
            'course_certificate',
            sprintf('Certificate — %s', $template->name),
            $html,
            'html',
            $actorId,
        );
        $issued->update(['document_id' => $document->id]);

        return $issued->fresh();
    }

    /**
     * @param  array<string, mixed>  $face
     */
    private function filledBody(?string $body, array $face): string
    {
        $text = $body ?: '<p>This certifies that <strong>{{student_name}}</strong> completed {{course_name}}.</p>';
        foreach ($face as $key => $value) {
            $text = str_replace('{{'.$key.'}}', e((string) ($value ?? '')), $text);
        }

        return $text;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
