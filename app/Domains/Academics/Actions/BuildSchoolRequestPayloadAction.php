<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\HR\Actions\AssertLeaveDocumentAction;
use App\Domains\Media\Actions\StoreUploadedDocumentAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * What a request is *about*, worked out from the form: the teacher a
 * teacher-leave request concerns, the staff profile and leave type a
 * staff-leave request concerns, and — new — the supporting document a
 * leave type may require (S5.2 `requires_document`, STATUS §5ff). Lived in
 * the controller's `store` until that was the longest method in the domain.
 *
 * The document is stored before the request exists and attached to the
 * staff profile, so a request that is then refused for another reason still
 * leaves the file where the office can find it — and the request row, once
 * it exists, points at it by id.
 */
class BuildSchoolRequestPayloadAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{payload: array<string, mixed>, regarding_type: string|null, regarding_id: int|null}
     */
    public function execute(SchoolRequestType $type, int $userId, array $data, ?UploadedFile $document = null): array
    {
        $from = (string) ($data['from_date'] ?? now()->toDateString());
        $to = (string) ($data['to_date'] ?? $from);

        if ($type === SchoolRequestType::TeacherLeave) {
            $teacherId = isset($data['teacher_id']) && $data['teacher_id'] !== ''
                ? (int) $data['teacher_id']
                : app(ResolveTeacherIdForUserAction::class)->execute($userId);
            if ($teacherId === null) {
                throw ValidationException::withMessages(['teacher_id' => 'A teacher profile is required for leave.']);
            }

            return [
                'payload' => ['teacher_id' => $teacherId, 'from_date' => $from, 'to_date' => $to],
                'regarding_type' => 'teacher',
                'regarding_id' => $teacherId,
            ];
        }

        if ($type === SchoolRequestType::StaffLeave) {
            $profile = app(ResolveStaffProfileForUserAction::class)->execute($userId);
            if ($profile === null) {
                throw ValidationException::withMessages(['type' => 'A staff profile is required for leave.']);
            }
            $leaveTypeId = (int) ($data['leave_type_id'] ?? 0);
            if ($leaveTypeId < 1) {
                throw ValidationException::withMessages(['leave_type_id' => 'A leave type is required.']);
            }

            $documentId = isset($data['document_id']) && $data['document_id'] !== '' ? (int) $data['document_id'] : null;
            if ($document !== null) {
                $documentId = app(StoreUploadedDocumentAction::class)->execute(
                    $document,
                    'staff_profile',
                    (int) $profile['id'],
                    'Leave '.$from.($to !== $from ? ' – '.$to : '').': '.$document->getClientOriginalName(),
                    'other',
                    $userId,
                )['id'];
            }
            app(AssertLeaveDocumentAction::class)->execute($leaveTypeId, $documentId);

            return [
                'payload' => [
                    'staff_profile_id' => (int) $profile['id'],
                    'leave_type_id' => $leaveTypeId,
                    'from_date' => $from,
                    'to_date' => $to,
                    'half_day' => (bool) ($data['half_day'] ?? false),
                    'document_id' => $documentId,
                ],
                'regarding_type' => 'staff_profile',
                'regarding_id' => (int) $profile['id'],
            ];
        }

        return ['payload' => [], 'regarding_type' => null, 'regarding_id' => null];
    }
}
