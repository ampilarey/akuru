<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\GuardianVerificationStatus;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use InvalidArgumentException;

class AttachGuardianAction
{
    /**
     * @param  array<string, mixed>  $policy  SPEC §9's consent status,
     *                                        verification status and notes.
     *                                        Consent left out is "not asked".
     *                                        Verification left out is
     *                                        **verified**: since item 13
     *                                        (2026-09-25) the status gates
     *                                        the family's access, and every
     *                                        caller of this Action is the
     *                                        office or a seeder — the one
     *                                        that is not, self-registration,
     *                                        passes `unverified` explicitly.
     */
    public function execute(
        Student $student,
        ParentGuardian $guardian,
        GuardianRelationship|string $relationship,
        bool $isPrimary = false,
        bool $canPickup = true,
        bool $financialResponsible = false,
        array $policy = [],
        ?int $actorId = null,
    ): void {
        $relationship = $relationship instanceof GuardianRelationship
            ? $relationship
            : GuardianRelationship::from($relationship);

        if ($student->guardians()->where('parent_guardians.id', $guardian->id)->exists()) {
            throw new InvalidArgumentException('Guardian is already attached to this student.');
        }

        $student->guardians()->attach($guardian->id, [
            'relationship' => $relationship->value,
            'is_primary' => $isPrimary,
            'can_pickup' => $canPickup,
            'financial_responsible' => $financialResponsible,
            // SPEC §9 names `created_by` among the nine things this pivot must
            // support, and nothing had ever written it: every link in the
            // database has a NULL creator. It is set here because this is the
            // only moment the answer is known.
            'created_by' => $actorId,
        ]);

        $policy += ['verification_status' => GuardianVerificationStatus::Verified->value];
        app(RecordGuardianLinkPolicyAction::class)->execute($student, $guardian, $policy, $actorId);

        // The mirror into the legacy `student_guardians` table stopped in
        // Deploy 3 slice 2; `guardian_student` is the only guardian link.
    }
}
