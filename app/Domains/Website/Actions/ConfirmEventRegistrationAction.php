<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\EventRegistration;
use Illuminate\Validation\ValidationException;

class ConfirmEventRegistrationAction
{
    public function execute(int $registrationId): EventRegistration
    {
        $registration = EventRegistration::query()->findOrFail($registrationId);

        if ($registration->status !== 'pending_parent') {
            throw ValidationException::withMessages([
                'status' => __('portal.error_event_not_awaiting'),
            ]);
        }

        $registration->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $registration->event?->updateAttendeeCount();

        return $registration->fresh();
    }
}
