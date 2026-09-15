<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Academics\Models\LessonLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordClassAttendanceAction implements AttendanceWriterInterface
{
    public function __construct(private ResolveAttendanceSettingsAction $settings) {}

    public function record(StudentAttendanceDTO $dto): ClassAttendance
    {
        $this->guardExcused($dto);
        $dto = $this->applyApprovedNote($dto);

        $query = ClassAttendance::query()
            ->where('student_id', $dto->studentId)
            ->whereDate('date', $dto->date);

        if ($dto->periodId === null) {
            $query->whereNull('period_id');
        } else {
            $query->where('period_id', $dto->periodId);
        }

        $payload = [
            'student_id' => $dto->studentId,
            'class_id' => $dto->classId,
            'academic_year_id' => $dto->academicYearId,
            'term_id' => $dto->termId,
            'date' => $dto->date,
            'period_id' => $dto->periodId,
            'period_key' => $dto->periodId ?? 0,
            'lesson_log_id' => $dto->lessonLogId,
            'status' => $dto->status,
            'minutes_late' => $dto->minutesLate,
            'source' => $dto->source,
            'marked_by' => $dto->markedBy,
            'absence_note_id' => $dto->absenceNoteId,
            'remarks' => $dto->remarks,
        ];

        $row = $query->first();
        if ($row === null) {
            $row = ClassAttendance::query()->create($payload);
        } else {
            $row->fill($payload);
            $row->save();
        }

        if ($dto->lessonLogId) {
            $this->refreshCounts($dto->lessonLogId);
        }

        $this->maybeNotify($row, $dto);

        return $row->refresh();
    }

    /**
     * An excusal is the record of an approved absence note, so it must carry
     * the note it came from (S2_SPEC §S2.4, KNOWN_ISSUES #15).
     *
     * The rule lives here rather than in the grids because every route into
     * `class_attendance` — the register grid, the daily grid, a CSV import, a
     * future biometric device — passes through this one writer, which
     * `AttendanceWriterTest` pins as the only one. Taking `excused` off the
     * teacher's buttons is the cosmetic half.
     *
     * What it prevents is quiet: an excused row sends the family no message
     * (`maybeNotify` below), tells them in the portal that none was due, and
     * drops the child out of `unexcused()`. A missing child would be invisible
     * from three directions, on one mis-click.
     */
    private function guardExcused(StudentAttendanceDTO $dto): void
    {
        if ($dto->status !== AttendanceStatus::Excused) {
            return;
        }

        if ($dto->absenceNoteId !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'attendance' => 'An absence is excused by approving the guardian\'s note, not by marking it excused here.',
        ]);
    }

    /**
     * An absence already explained is excused whichever way round it happened.
     *
     * `ApproveAbsenceNoteAction` covers one order: the register is filled, the
     * child is marked absent, the note arrives afterwards and flips the row.
     * **The other order is the ordinary one** — a family tells the school in
     * the morning that the child is ill, the office approves it, and only then
     * does a teacher fill the 9am register. There the approval runs against no
     * rows at all, and the absent mark written minutes later carries no note.
     *
     * The consequences were not cosmetic and there was no way back from them:
     *
     *  - the family gets an absence SMS about an absence they reported,
     *  - the child joins `unexcused()`, the chronic-absence list,
     *  - the office cannot correct it. A teacher marking the row `excused` is
     *    refused by `guardExcused` below, and re-approving the note throws
     *    "This note is already approved."
     *
     * So the rule belongs here, beside its mirror image, and for the reason
     * `guardExcused` gives: every route into `class_attendance` — both grids, a
     * CSV import, a future card reader — passes through this one writer. An
     * excusal still comes only from an approved note (rule 11: the note is the
     * single source of truth for whether an absence is excused), and the note
     * still has to say it excuses.
     */
    private function applyApprovedNote(StudentAttendanceDTO $dto): StudentAttendanceDTO
    {
        if ($dto->status !== AttendanceStatus::Absent || $dto->absenceNoteId !== null) {
            return $dto;
        }

        $note = AbsenceNote::query()
            ->where('student_id', $dto->studentId)
            ->whereDate('date', $dto->date)
            ->where('status', AbsenceNoteStatus::Approved->value)
            // A note written for one period explains that period only; a
            // whole-day note explains any of them. This is the same pairing
            // `excuseMatchingAbsences` makes from the other direction.
            ->where(fn ($query) => $query->whereNull('period_id')->orWhere('period_id', $dto->periodId))
            ->orderByDesc('period_id')
            ->get()
            ->first(fn (AbsenceNote $note): bool => $note->excusesAttendance());

        if ($note === null) {
            return $dto;
        }

        return new StudentAttendanceDTO(
            studentId: $dto->studentId,
            classId: $dto->classId,
            academicYearId: $dto->academicYearId,
            date: $dto->date,
            status: AttendanceStatus::Excused,
            source: $dto->source,
            markedBy: $dto->markedBy,
            termId: $dto->termId,
            periodId: $dto->periodId,
            lessonLogId: $dto->lessonLogId,
            minutesLate: $dto->minutesLate,
            absenceNoteId: (int) $note->id,
            remarks: $dto->remarks,
        );
    }

    private function refreshCounts(int $lessonLogId): void
    {
        $counts = ClassAttendance::query()
            ->where('lesson_log_id', $lessonLogId)
            ->selectRaw('
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as absent_count
            ', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
                AttendanceStatus::Absent->value,
                AttendanceStatus::Excused->value,
            ])
            ->first();

        LessonLog::query()->where('id', $lessonLogId)->update([
            'present_count' => (int) ($counts->present_count ?? 0),
            'late_count' => (int) ($counts->late_count ?? 0),
            'absent_count' => (int) ($counts->absent_count ?? 0),
        ]);
    }

    private function maybeNotify(ClassAttendance $row, StudentAttendanceDTO $dto): void
    {
        $settings = $this->settings->execute();
        $should = match ($dto->status) {
            AttendanceStatus::Absent => true,
            AttendanceStatus::Late => $settings['notify'] === 'absent_and_late',
            default => false,
        };

        if (! $should) {
            return;
        }

        $student = DB::table('students')->where('id', $dto->studentId)->first(['first_name', 'last_name']);
        $name = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));

        event(new StudentMarkedAbsent(
            studentId: $dto->studentId,
            classAttendanceId: (int) $row->id,
            date: $dto->date,
            status: $dto->status,
            studentName: $name !== '' ? $name : 'Student',
        ));
    }
}
