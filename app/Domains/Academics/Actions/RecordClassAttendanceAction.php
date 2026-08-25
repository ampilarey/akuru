<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Academics\Models\LessonLog;
use Illuminate\Support\Facades\DB;

class RecordClassAttendanceAction implements AttendanceWriterInterface
{
    public function __construct(private ResolveAttendanceSettingsAction $settings) {}

    public function record(StudentAttendanceDTO $dto): ClassAttendance
    {
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
