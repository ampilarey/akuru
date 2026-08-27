<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Enums\MeetingBookingStatus;
use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Models\MeetingBooking;
use App\Domains\Academics\Models\MeetingSlot;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookMeetingSlotAction
{
    public function execute(int $slotId, int $studentId, int $userId, ?string $notes = null): MeetingBooking
    {
        if (! $this->userMayBookStudent($userId, $studentId)) {
            throw ValidationException::withMessages([
                'student_id' => 'You can only book meetings for your linked children.',
            ]);
        }

        return DB::transaction(function () use ($slotId, $studentId, $userId, $notes): MeetingBooking {
            $slot = MeetingSlot::query()->lockForUpdate()->findOrFail($slotId);

            if ($slot->status !== MeetingSlotStatus::Published) {
                throw ValidationException::withMessages([
                    'slot' => 'That meeting slot is not open for booking.',
                ]);
            }

            if (! $this->studentEligible($slot, $studentId)) {
                throw ValidationException::withMessages([
                    'student_id' => 'That student is not in the class for this slot.',
                ]);
            }

            $this->assertNoStudentOverlap($slot, $studentId);

            $existing = MeetingBooking::query()
                ->where('meeting_slot_id', $slot->id)
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status === MeetingBookingStatus::Booked) {
                throw ValidationException::withMessages([
                    'slot' => 'That student already has this slot.',
                ]);
            }

            $booked = MeetingBooking::query()
                ->where('meeting_slot_id', $slot->id)
                ->where('status', MeetingBookingStatus::Booked)
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                ->count();

            if ($booked >= (int) $slot->capacity) {
                throw ValidationException::withMessages([
                    'slot' => 'That meeting slot is full.',
                ]);
            }

            $payload = [
                'meeting_slot_id' => $slot->id,
                'academic_year_id' => $slot->academic_year_id,
                'term_id' => $slot->term_id,
                'student_id' => $studentId,
                'booked_by' => $userId,
                'status' => MeetingBookingStatus::Booked,
                'notes' => $this->nullable($notes),
            ];

            if ($existing !== null) {
                $existing->fill($payload);
                $existing->save();

                return $existing->refresh();
            }

            return MeetingBooking::query()->create($payload);
        });
    }

    private function userMayBookStudent(int $userId, int $studentId): bool
    {
        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null && (int) $self['id'] === $studentId) {
            return true;
        }

        return app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId($userId)
            ->contains(fn ($child) => (int) $child->id === $studentId);
    }

    private function studentEligible(MeetingSlot $slot, int $studentId): bool
    {
        if ($slot->class_id === null) {
            return true;
        }

        return DB::table('class_student')
            ->where('class_id', $slot->class_id)
            ->where('student_id', $studentId)
            ->where('status', ClassStudentStatus::Active->value)
            ->exists();
    }

    private function assertNoStudentOverlap(MeetingSlot $slot, int $studentId): void
    {
        $others = MeetingBooking::query()
            ->where('student_id', $studentId)
            ->where('status', MeetingBookingStatus::Booked)
            ->whereHas('slot', function ($query) use ($slot): void {
                $query->whereDate('date', $slot->date?->toDateString())
                    ->where('id', '!=', $slot->id)
                    ->where('status', '!=', MeetingSlotStatus::Cancelled);
            })
            ->with('slot')
            ->get();

        $start = $slot->start_time?->format('H:i:s');
        $end = $slot->end_time?->format('H:i:s');

        $overlap = $others->first(function (MeetingBooking $booking) use ($start, $end): bool {
            $other = $booking->slot;
            if ($other === null || $start === null || $end === null) {
                return false;
            }

            $otherStart = $other->start_time?->format('H:i:s');
            $otherEnd = $other->end_time?->format('H:i:s');

            return $otherStart !== null && $otherEnd !== null && $start < $otherEnd && $otherStart < $end;
        });

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'slot' => 'That student already has a meeting at this time.',
            ]);
        }
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
