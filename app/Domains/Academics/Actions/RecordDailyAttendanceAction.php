<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceMode;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassRoom;
use Illuminate\Validation\ValidationException;

class RecordDailyAttendanceAction
{
    public function __construct(
        private AttendanceWriterInterface $writer,
        private ResolveAttendanceSettingsAction $settings,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $marks
     */
    public function execute(ClassRoom $class, string $date, array $marks, int $markedBy): void
    {
        $mode = $this->settings->execute()['mode'];
        if ($mode !== AttendanceMode::Daily) {
            throw ValidationException::withMessages([
                'mode' => 'Daily attendance is disabled while the school is in per-lesson mode.',
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
                classId: (int) $class->id,
                academicYearId: (int) $class->academic_year_id,
                date: $date,
                status: $status,
                source: AttendanceSource::Daily,
                markedBy: $markedBy,
                periodId: null,
                minutesLate: isset($mark['minutes_late']) && $mark['minutes_late'] !== ''
                    ? (int) $mark['minutes_late']
                    : null,
                remarks: isset($mark['remarks']) ? trim((string) $mark['remarks']) ?: null : null,
            ));
        }
    }
}
