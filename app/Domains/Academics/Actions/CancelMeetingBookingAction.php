<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\MeetingBookingStatus;
use App\Domains\Academics\Models\MeetingBooking;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Validation\ValidationException;

class CancelMeetingBookingAction
{
    public function execute(int $bookingId, int $userId, bool $staff = false): MeetingBooking
    {
        $booking = MeetingBooking::query()->findOrFail($bookingId);

        if (! $staff && ! $this->userMayCancel($userId, $booking)) {
            throw ValidationException::withMessages([
                'booking' => 'You can only cancel your own meeting bookings.',
            ]);
        }

        if ($booking->status === MeetingBookingStatus::Cancelled) {
            return $booking;
        }

        $booking->status = MeetingBookingStatus::Cancelled;
        $booking->save();

        return $booking->refresh();
    }

    private function userMayCancel(int $userId, MeetingBooking $booking): bool
    {
        if ((int) $booking->booked_by === $userId) {
            return true;
        }

        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null && (int) $self['id'] === (int) $booking->student_id) {
            return true;
        }

        return app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId($userId)
            ->contains(fn ($child) => (int) $child->id === (int) $booking->student_id);
    }
}
