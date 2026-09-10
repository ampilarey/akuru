<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\EmergencyContact;
use Illuminate\Validation\ValidationException;

/**
 * Take an emergency contact off a student.
 *
 * Checked against the student it is being removed from, so a stale page cannot
 * delete another child's contact by id.
 */
class RemoveEmergencyContactAction
{
    public function execute(int $studentId, EmergencyContact $contact): void
    {
        if ((int) $contact->student_id !== $studentId) {
            throw ValidationException::withMessages([
                'contact' => 'That contact belongs to a different student.',
            ]);
        }

        $contact->delete();
    }
}
