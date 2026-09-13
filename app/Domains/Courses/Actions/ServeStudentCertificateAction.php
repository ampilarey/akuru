<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\IssuedCertificate;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;

/**
 * A student opening the certificate they earned.
 *
 * SPEC §24 lists "Certificates" among what the student dashboard must show, and
 * §39 builds them end to end: templates, eligibility rules, issuing, a rendered
 * document, a revocation flag and a public verification page.
 *
 * Every route to one was staff-only — `role:super_admin|admin|headmaster` on
 * the group and `courses.manage` on the method. So the system issued a
 * certificate *to* a student, told them on the course page that they had
 * earned it, printed its number, and gave them **no way to open it**. The
 * document existed and its only readers were administrators.
 *
 * The gate here is the narrowest one that works: the certificate must be this
 * user's own, and it must not be revoked. Revocation is the point of the
 * column — a withdrawn certificate that still downloads was never withdrawn —
 * and it is why this cannot simply reuse the staff download with a widened
 * role, which has no such check because a revoked row is something an
 * administrator may legitimately need to look at.
 */
class ServeStudentCertificateAction
{
    /**
     * @return array{contents: string, mime: string, filename: string}
     */
    public function execute(int $certificateId, ?int $userId): array
    {
        abort_unless($userId !== null, 403);

        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        abort_unless($student !== null, 403, 'A student profile is required.');

        $certificate = IssuedCertificate::query()
            ->where('id', $certificateId)
            ->where('student_id', $student['id'])
            ->first();

        // Not-found rather than forbidden for someone else's certificate: a 403
        // would confirm that the id exists and belongs to somebody.
        abort_if($certificate === null, 404);
        abort_if($certificate->revoked_at !== null, 404);
        abort_unless($certificate->document_id, 404);

        $file = app(ReadGeneratedDocumentAction::class)->execute((int) $certificate->document_id);

        return [
            'contents' => $file['contents'],
            'mime' => $file['mime'],
            'filename' => $certificate->certificate_number.'.html',
        ];
    }
}
