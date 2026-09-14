<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Events\StudentStatusChanged;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\StudentStatusHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeStudentStatusAction
{
    public function executeById(
        int $studentId,
        string $to,
        int $changedBy,
        ?string $reason = null,
        mixed $effectiveDate = null,
    ): void {
        $this->execute(
            Student::query()->findOrFail($studentId),
            StudentStatus::from($to),
            $changedBy,
            $reason,
            $effectiveDate,
        );
    }

    public function execute(
        Student $student,
        StudentStatus $to,
        ?int $changedBy = null,
        ?string $reason = null,
        mixed $effectiveDate = null,
    ): Student {
        $changedBy ??= auth()->id();
        if ($changedBy === null) {
            throw new InvalidArgumentException('changed_by is required for a student status change.');
        }

        $effective = (string) ($effectiveDate ?? now()->toDateString());

        [$student, $from] = DB::transaction(function () use ($student, $to, $changedBy, $reason, $effective) {
            $student->refresh();

            $from = $student->status;

            $student->forceFill(['status' => $to])->save();

            StudentStatusHistory::query()->create([
                'student_id' => $student->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'effective_date' => $effective,
                'changed_by' => $changedBy,
            ]);

            return [$student->refresh(), $from];
        });

        // Announced after the transaction commits, so a listener cannot act on
        // a change that is then rolled back — and so a listener that throws
        // cannot undo a status change the office has already been told
        // succeeded.
        event(new StudentStatusChanged(
            (int) $student->id,
            $from,
            $to,
            $effective,
            $changedBy,
        ));

        return $student;
    }
}
