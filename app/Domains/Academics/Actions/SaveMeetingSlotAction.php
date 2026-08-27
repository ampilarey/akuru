<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Models\MeetingSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMeetingSlotAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?MeetingSlot $slot = null, ?int $actorId = null): MeetingSlot
    {
        $payload = $this->normalized($data, $slot, $actorId);
        $this->assertNoTeacherOverlap($payload, $slot?->id);

        if ($slot === null) {
            return MeetingSlot::query()->create($payload);
        }

        if ($this->hasActiveBookings($slot) && $this->timeChanged($slot, $payload)) {
            throw ValidationException::withMessages([
                'start_time' => 'Cannot change time on a slot that already has bookings.',
            ]);
        }

        $slot->fill($payload);
        $slot->save();

        return $slot->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, ?MeetingSlot $slot, ?int $actorId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($yearId < 1 || ! DB::table('academic_years')->where('id', $yearId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Academic year is required.']);
        }

        $termId = $this->optionalId($data['term_id'] ?? null);
        if ($termId !== null && ! DB::table('terms')->where('id', $termId)->where('academic_year_id', $yearId)->exists()) {
            throw ValidationException::withMessages(['term_id' => 'Term must belong to the academic year.']);
        }

        $teacherId = (int) ($data['teacher_id'] ?? 0);
        if ($teacherId < 1 || ! DB::table('teachers')->where('id', $teacherId)->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Teacher is required.']);
        }

        $classId = $this->optionalId($data['class_id'] ?? null);
        if ($classId !== null && ! DB::table('classes')->where('id', $classId)->where('academic_year_id', $yearId)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'Class must belong to the academic year.']);
        }

        $roomId = $this->optionalId($data['room_id'] ?? null);
        if ($roomId !== null && ! DB::table('rooms')->where('id', $roomId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['room_id' => 'Room not found.']);
        }

        $date = trim((string) ($data['date'] ?? ''));
        if ($date === '') {
            throw ValidationException::withMessages(['date' => 'Date is required.']);
        }

        $start = $this->normalizeTime($data['start_time'] ?? null);
        $end = $this->normalizeTime($data['end_time'] ?? null);
        if ($start === null || $end === null) {
            throw ValidationException::withMessages(['start_time' => 'Start and end time are required.']);
        }
        if ($start >= $end) {
            throw ValidationException::withMessages(['end_time' => 'End time must be after start time.']);
        }

        $capacity = (int) ($data['capacity'] ?? 1);
        if ($capacity < 1) {
            throw ValidationException::withMessages(['capacity' => 'Capacity must be at least 1.']);
        }

        $status = MeetingSlotStatus::tryFrom((string) ($data['status'] ?? MeetingSlotStatus::Draft->value))
            ?? MeetingSlotStatus::Draft;

        $payload = [
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'teacher_id' => $teacherId,
            'class_id' => $classId,
            'room_id' => $roomId,
            'title' => $title,
            'title_arabic' => $this->nullableString($data['title_arabic'] ?? null),
            'title_dhivehi' => $this->nullableString($data['title_dhivehi'] ?? null),
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'capacity' => $capacity,
            'status' => $status,
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];

        if ($slot === null) {
            $payload['created_by'] = $actorId;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoTeacherOverlap(array $payload, ?int $excludeId): void
    {
        $others = MeetingSlot::query()
            ->where('teacher_id', $payload['teacher_id'])
            ->whereDate('date', $payload['date'])
            ->where('status', '!=', MeetingSlotStatus::Cancelled)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get();

        $overlap = $others->first(function (MeetingSlot $other) use ($payload): bool {
            return $this->timesOverlap(
                $payload['start_time'],
                $payload['end_time'],
                $this->normalizeTime($other->start_time?->format('H:i:s')),
                $this->normalizeTime($other->end_time?->format('H:i:s')),
            );
        });

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'start_time' => 'That teacher already has a meeting slot overlapping this time.',
            ]);
        }
    }

    private function hasActiveBookings(MeetingSlot $slot): bool
    {
        return $slot->bookings()->where('status', 'booked')->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timeChanged(MeetingSlot $slot, array $payload): bool
    {
        return $slot->date?->toDateString() !== $payload['date']
            || $this->normalizeTime($slot->start_time?->format('H:i:s')) !== $payload['start_time']
            || $this->normalizeTime($slot->end_time?->format('H:i:s')) !== $payload['end_time']
            || (int) $slot->teacher_id !== (int) $payload['teacher_id'];
    }

    private function timesOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if ($startA === null || $endA === null || $startB === null || $endB === null) {
            return false;
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
