<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\StudentStatusHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeStudentStatusAction
{
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

        return DB::transaction(function () use ($student, $to, $changedBy, $reason, $effectiveDate) {
            $student->refresh();

            $from = $student->status;

            $student->forceFill(['status' => $to])->save();

            StudentStatusHistory::query()->create([
                'student_id' => $student->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'effective_date' => $effectiveDate ?? now()->toDateString(),
                'changed_by' => $changedBy,
            ]);

            return $student->refresh();
        });
    }
}
