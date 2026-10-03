<?php

namespace App\Domains\Admissions\Actions;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use Illuminate\Http\UploadedFile;

/**
 * COMMERCE_PARITY_PLAN P3: someone registering for a course uploads the
 * learner's ID card — the front only since C17 slice R3 (STATUS §5of; the
 * owner: "all the important informations are on front page only"), a back
 * still kept if one is sent — the child's own card when a parent
 * enrols a child (decision D3). It never holds the enrolment up (D2): the
 * office verifies it afterwards on the enrolment page, and a certificate
 * waits for that.
 *
 * The funnel finishes on a later request (after the consent code), so the
 * two sides are stored now and their media ids carried in the session; the
 * card is filed against the learner once the enrolment has made them.
 */
class LearnerIdCardAction
{
    public const SESSION = 'enroll_pending_id_card';

    /**
     * Keep the two sides for the enrolment about to be confirmed.
     *
     * @return array<string, string>|null field errors, or null when all is well
     */
    public function stash(int $userId, ?int $studentId, ?UploadedFile $front, ?UploadedFile $back): ?array
    {
        session()->forget(self::SESSION);
        $identity = app(IdentityVerificationAction::class);
        $onFile = $studentId !== null && in_array($identity->learnerStatus($studentId)['status'], ['pending', 'verified'], true);
        if ($front === null) {
            return $onFile || ! IdentityVerificationAction::enforced() ? null : ['id_front' => __('account.id_learner_needed')];
        }
        session([self::SESSION => [$identity->storeSide($front, $userId), $back !== null ? $identity->storeSide($back, $userId) : null]]);

        return null;
    }

    /** File the stashed card against the learner the enrolment made (or found). */
    public function attach(int $userId, ?int $studentId): void
    {
        $ids = session()->pull(self::SESSION);
        if ($studentId === null || ! is_array($ids) || empty($ids[0])) {
            return;
        }
        app(IdentityVerificationAction::class)->submitStored($userId, 'learner', (int) $ids[0], isset($ids[1]) ? (int) $ids[1] : null, $studentId);
    }
}
