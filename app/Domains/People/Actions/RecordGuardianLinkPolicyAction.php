<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianConsentStatus;
use App\Domains\People\Enums\GuardianVerificationStatus;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §9 "Parent-Child Relationship" lists nine things the `guardian_student`
 * pivot must support:
 *
 *   > Multiple children per guardian · Multiple guardians per child ·
 *   > Relationship type · **Consent status** · **Verification status** ·
 *   > **`verified_at`** · **`created_by`** · **Notes**
 *
 * The table has all of them. `AttachGuardianAction` wrote four — relationship
 * and the primary/pickup/financial flags — and left the last five at their
 * defaults, with no other code path able to change them. Every link in the
 * database has therefore read `unknown` / `unverified` since the table shipped,
 * and `verified_at`, `created_by` and `notes` have been NULL on every row ever
 * created.
 *
 * A field that can only ever hold its default is supported in name only. This
 * Action is what makes those five real.
 *
 * **Verification is a record, not a gate.** `/portal/children` stays scoped the
 * way it always was — to the signed-in guardian's own links — and nothing here
 * filters on `verification_status`. Making it an access rule would hide every
 * child from every parent overnight, because every existing link is
 * `unverified`; that is a separate decision with its own backfill, and it
 * belongs to the owner. See `GuardianVerificationStatus`.
 */
class RecordGuardianLinkPolicyAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(Student $student, ParentGuardian $guardian, array $data, ?int $actorId = null): array
    {
        $link = $student->guardians()->where('parent_guardians.id', $guardian->id)->first();

        if ($link === null) {
            throw ValidationException::withMessages([
                'guardian' => ['That guardian is not linked to this student.'],
            ]);
        }

        $pivot = $link->pivot;
        $attributes = [];

        if (array_key_exists('consent_status', $data)) {
            $attributes['consent_status'] = $this->consent($data['consent_status'])->value;
        }

        if (array_key_exists('verification_status', $data)) {
            $verification = $this->verification($data['verification_status']);
            $attributes['verification_status'] = $verification->value;

            // §9 pairs the status with `verified_at`. They move together, so a
            // link that goes back to unchecked does not keep a timestamp
            // claiming somebody verified it.
            $attributes['verified_at'] = $verification->stampsVerifiedAt() ? now() : null;
        }

        if (array_key_exists('notes', $data)) {
            $notes = trim((string) $data['notes']);
            $attributes['notes'] = $notes !== '' ? $notes : null;
        }

        if ($attributes === []) {
            return $this->serialize($student, $guardian);
        }

        // `created_by` records who built the link, so it is filled once and
        // never overwritten — a later editor is not the person who made it.
        if ($actorId !== null && $pivot->created_by === null) {
            $attributes['created_by'] = $actorId;
        }

        $student->guardians()->updateExistingPivot($guardian->id, $attributes);

        return $this->serialize($student, $guardian);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Student $student, ParentGuardian $guardian): array
    {
        $pivot = $student->guardians()->where('parent_guardians.id', $guardian->id)->firstOrFail()->pivot;

        $consent = GuardianConsentStatus::tryFrom((string) $pivot->consent_status) ?? GuardianConsentStatus::Unknown;
        $verification = GuardianVerificationStatus::tryFrom((string) $pivot->verification_status)
            ?? GuardianVerificationStatus::Unverified;

        return [
            'guardian_id' => (int) $guardian->id,
            'consent_status' => $consent->value,
            'consent_label' => $consent->label(),
            'verification_status' => $verification->value,
            'verification_label' => $verification->label(),
            'verified_at' => $pivot->verified_at ? (string) $pivot->verified_at : null,
            'created_by' => $pivot->created_by !== null ? (int) $pivot->created_by : null,
            'notes' => $pivot->notes,
        ];
    }

    private function consent(mixed $value): GuardianConsentStatus
    {
        $status = GuardianConsentStatus::tryFrom(is_string($value) ? $value : '');

        if ($status === null) {
            throw ValidationException::withMessages([
                'consent_status' => ['Consent must be one of: '.$this->values(GuardianConsentStatus::cases()).'.'],
            ]);
        }

        return $status;
    }

    private function verification(mixed $value): GuardianVerificationStatus
    {
        $status = GuardianVerificationStatus::tryFrom(is_string($value) ? $value : '');

        if ($status === null) {
            throw ValidationException::withMessages([
                'verification_status' => ['Verification must be one of: '.$this->values(GuardianVerificationStatus::cases()).'.'],
            ]);
        }

        return $status;
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     */
    private function values(array $cases): string
    {
        return implode(', ', array_map(static fn ($case): string => (string) $case->value, $cases));
    }
}
