<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceMode;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\LessonLog;
use Illuminate\Validation\ValidationException;

class RecordRegisterAttendanceAction
{
    public function __construct(
        private AttendanceWriterInterface $writer,
        private ResolveAttendanceSettingsAction $settings,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $marks
     */
    public function execute(LessonLog $log, array $marks, int $markedBy): void
    {
        $mode = $this->settings->execute()['mode'];
        if ($mode !== AttendanceMode::PerLesson) {
            return;
        }

        if ($log->academic_year_id === null) {
            throw ValidationException::withMessages([
                'attendance' => 'This register is missing an academic year.',
            ]);
        }

        foreach ($marks as $mark) {
            $status = AttendanceStatus::tryFrom((string) ($mark['status'] ?? ''));
            if ($status === null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Each mark needs a valid status.',
                ]);
            }

            $this->writer->record(new StudentAttendanceDTO(
                studentId: (int) $mark['student_id'],
                classId: (int) $log->classroom_id,
                academicYearId: (int) $log->academic_year_id,
                date: (string) $log->date?->toDateString(),
                status: $status,
                source: AttendanceSource::Register,
                markedBy: $markedBy,
                termId: $log->term_id,
                periodId: $log->period_id,
                lessonLogId: $log->id,
                minutesLate: isset($mark['minutes_late']) && $mark['minutes_late'] !== ''
                    ? (int) $mark['minutes_late']
                    : null,
                remarks: isset($mark['remarks']) ? trim((string) $mark['remarks']) ?: null : null,
            ));
        }
    }
}
