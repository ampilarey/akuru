<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\Academics\Actions\CheckRoomSlotConflictAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveExamAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Exam $exam = null, ?int $actorId = null): Exam
    {
        if ($exam !== null && ! $exam->status->allowsEdits()) {
            throw ValidationException::withMessages([
                'status' => 'Locked exams cannot be edited. Unlock first.',
            ]);
        }

        $payload = $this->normalized($data, $actorId, $exam);
        $this->assertSchedule($payload, $exam, $data);

        if ($exam === null) {
            return Exam::query()->create($payload);
        }

        $exam->fill($payload);
        $exam->save();

        return $exam->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, ?int $actorId, ?Exam $exam): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($yearId < 1 || ! DB::table('academic_years')->where('id', $yearId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Academic year is required.']);
        }

        $termId = (int) ($data['term_id'] ?? 0);
        if ($termId < 1 || ! DB::table('terms')->where('id', $termId)->where('academic_year_id', $yearId)->exists()) {
            throw ValidationException::withMessages(['term_id' => 'Term must belong to the academic year.']);
        }

        $classId = (int) ($data['class_id'] ?? 0);
        if ($classId < 1 || ! DB::table('classes')->where('id', $classId)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'Class is required.']);
        }

        $subjectId = (int) ($data['subject_id'] ?? 0);
        if ($subjectId < 1 || ! DB::table('subjects')->where('id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Subject is required.']);
        }

        $typeId = (int) ($data['exam_type_id'] ?? 0);
        if ($typeId < 1 || ! ExamType::query()->where('id', $typeId)->exists()) {
            throw ValidationException::withMessages(['exam_type_id' => 'Exam type is required.']);
        }

        $roomId = $this->optionalId($data['room_id'] ?? null);
        if ($roomId !== null && ! DB::table('rooms')->where('id', $roomId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['room_id' => 'Room not found.']);
        }

        $date = $this->nullableString($data['exam_date'] ?? null);
        $start = $this->normalizeTime($data['start_time'] ?? null);
        $end = $this->normalizeTime($data['end_time'] ?? null);
        if (($start === null) !== ($end === null)) {
            throw ValidationException::withMessages([
                'start_time' => 'Provide both start and end time, or neither.',
            ]);
        }

        $maxMarks = (float) ($data['max_marks'] ?? 100);
        if ($maxMarks <= 0) {
            throw ValidationException::withMessages(['max_marks' => 'Max marks must be greater than 0.']);
        }

        $weight = $data['weight_override'] ?? null;
        $weight = $weight === '' || $weight === null ? null : (int) $weight;
        if ($weight !== null && ($weight < 0 || $weight > 100)) {
            throw ValidationException::withMessages(['weight_override' => 'Weight override must be 0–100.']);
        }

        $payload = [
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'exam_type_id' => $typeId,
            'name' => $name,
            'exam_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'room_id' => $roomId,
            'max_marks' => $maxMarks,
            'weight_override' => $weight,
            'instructions' => $this->nullableString($data['instructions'] ?? null),
        ];

        if ($exam === null) {
            $payload['status'] = ExamStatus::Scheduled;
            $payload['created_by'] = $actorId;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function assertSchedule(array $payload, ?Exam $exam, array $data): void
    {
        $date = $payload['exam_date'];
        if ($date === null) {
            return;
        }

        $confirmCalendar = (bool) ($data['confirm_calendar'] ?? false);
        $confirmSameDay = (bool) ($data['confirm_same_day'] ?? false);
        $confirmRoom = (bool) ($data['confirm_room'] ?? false);

        $blocked = DB::table('calendar_days')
            ->where('academic_year_id', $payload['academic_year_id'])
            ->whereDate('date', $date)
            ->whereIn('type', ['holiday', 'closure'])
            ->exists();

        if ($blocked && ! $confirmCalendar) {
            throw ValidationException::withMessages([
                'exam_date' => 'That date is a holiday or closure. Confirm to schedule anyway.',
            ]);
        }

        $maxPerDay = app(ResolveExamSettingsAction::class)->execute()['max_per_class_per_day'];
        $sameDay = Exam::query()
            ->where('class_id', $payload['class_id'])
            ->whereDate('exam_date', $date)
            ->when($exam !== null, fn ($query) => $query->where('id', '!=', $exam->id))
            ->count();

        if ($sameDay >= $maxPerDay && ! $confirmSameDay) {
            throw ValidationException::withMessages([
                'exam_date' => "This class already has {$sameDay} exam(s) on that date (max {$maxPerDay}). Confirm to schedule anyway.",
            ]);
        }

        $roomId = $payload['room_id'];
        if ($roomId === null || $confirmRoom) {
            return;
        }

        $start = $payload['start_time'];
        $end = $payload['end_time'];

        $otherExam = Exam::query()
            ->where('room_id', $roomId)
            ->whereDate('exam_date', $date)
            ->when($exam !== null, fn ($query) => $query->where('id', '!=', $exam->id))
            ->get()
            ->first(fn (Exam $other) => $this->timesOverlap(
                $start,
                $end,
                $this->normalizeTime($other->start_time?->format('H:i:s')),
                $this->normalizeTime($other->end_time?->format('H:i:s')),
            ));

        if ($otherExam !== null) {
            throw ValidationException::withMessages([
                'room_id' => 'Another exam uses this room at that time. Confirm to schedule anyway.',
            ]);
        }

        if ($start === null || $end === null) {
            return;
        }

        $conflicts = app(CheckRoomSlotConflictAction::class)->execute(
            (int) $payload['academic_year_id'],
            (int) $roomId,
            $date,
            $start,
            $end,
        );

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'room_id' => 'Room is booked or has a timetable slot at that time. Confirm to schedule anyway.',
            ]);
        }
    }

    private function timesOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if ($startA === null || $endA === null || $startB === null || $endB === null) {
            return true;
        }

        return $startA < $endB && $startB < $endA;
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $time = $this->nullableString($value);
        if ($time === null) {
            return null;
        }

        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
