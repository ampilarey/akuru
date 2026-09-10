<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\EmergencyContact;
use Illuminate\Validation\ValidationException;

/**
 * Record or correct an emergency contact.
 *
 * A name and a number are both required and neither is allowed to be blank:
 * a contact you cannot ring is not a contact, and an unnamed number tells
 * whoever dials it nothing about who is answering.
 */
class SaveEmergencyContactAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $studentId, array $data, ?EmergencyContact $contact = null): EmergencyContact
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'A contact needs a name.']);
        }

        if ($phone === '') {
            throw ValidationException::withMessages(['phone' => 'A contact needs a phone number.']);
        }

        if ($contact !== null && (int) $contact->student_id !== $studentId) {
            // Editing one child's contact through another child's page would
            // rewrite the wrong family's details.
            throw ValidationException::withMessages([
                'name' => 'That contact belongs to a different student.',
            ]);
        }

        $attributes = [
            'name' => $name,
            'phone' => $phone,
            'relationship' => $this->nullableString($data['relationship'] ?? null),
            // Defaulting to 1 rather than 0 keeps the column meaning what it
            // says: "ring first", not "unset".
            'priority' => max(1, (int) ($data['priority'] ?? 1)),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];

        if ($contact !== null) {
            $contact->update($attributes);

            return $contact->refresh();
        }

        return EmergencyContact::query()->create([...$attributes, 'student_id' => $studentId]);
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
