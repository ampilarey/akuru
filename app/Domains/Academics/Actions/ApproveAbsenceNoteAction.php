<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use Illuminate\Validation\ValidationException;

class ApproveAbsenceNoteAction
{
    public function __construct(private AttendanceWriterInterface $writer) {}

    public function execute(AbsenceNote $note, int $reviewerId, ?string $reviewNotes = null): AbsenceNote
    {
        if ($note->status === AbsenceNoteStatus::Approved->value || $note->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'This note is already approved.',
            ]);
        }

        $note->status = AbsenceNoteStatus::Approved->value;
        $note->reviewed_by = $reviewerId;
        $note->reviewed_at = now();
        $note->review_notes = $reviewNotes;
        $note->save();

        // E10c: the type decides whether approval excuses the register. The
        // per-note boolean stays as the fallback for notes written before
        // types existed — it is still written, so the two cannot drift.
        $excuses = $note->absence_type_id !== null
            ? (bool) \App\Domains\Academics\Models\AbsenceType::query()
                ->whereKey($note->absence_type_id)->value('excuses_absence')
            : (bool) $note->affects_attendance;

        if ($excuses) {
            $this->excuseMatchingAbsences($note, $reviewerId);
        }

        return $note->refresh();
    }

    private function excuseMatchingAbsences(AbsenceNote $note, int $reviewerId): void
    {
        $rows = ClassAttendance::query()
            ->where('student_id', $note->student_id)
            ->whereDate('date', $note->date)
            ->where('status', AttendanceStatus::Absent->value)
            ->when($note->period_id, fn ($query) => $query->where('period_id', $note->period_id))
            ->get();

        foreach ($rows as $row) {
            $this->writer->record(new StudentAttendanceDTO(
                studentId: (int) $row->student_id,
                classId: (int) $row->class_id,
                academicYearId: (int) $row->academic_year_id,
                date: (string) $row->date?->toDateString(),
                status: AttendanceStatus::Excused,
                source: $row->source,
                markedBy: $reviewerId,
                termId: $row->term_id,
                periodId: $row->period_id,
                lessonLogId: $row->lesson_log_id,
                minutesLate: $row->minutes_late,
                absenceNoteId: $note->id,
                remarks: $row->remarks,
            ));
        }
    }
}
