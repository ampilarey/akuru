<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveType;
use Illuminate\Validation\ValidationException;

/**
 * S5.2: a leave type can require a supporting document (`requires_document`
 * — sick leave is seeded that way). The flag was saved, listed and shown as
 * a column, and enforced nowhere: a request with nothing attached went
 * through and was approved (S5 audit D4, STATUS §5ff). Asked twice now —
 * at submission, so the person is told before they send, and again at
 * approval, so a payload built some other way cannot slip past.
 */
class AssertLeaveDocumentAction
{
    public function execute(int $leaveTypeId, ?int $documentId): void
    {
        $type = LeaveType::query()->find($leaveTypeId);
        if ($type === null || ! $type->requires_document) {
            return;
        }

        if ($documentId === null || $documentId < 1) {
            throw ValidationException::withMessages([
                'document' => $type->name.' leave needs a supporting document (a PDF or a photo of the certificate).',
            ]);
        }
    }
}
