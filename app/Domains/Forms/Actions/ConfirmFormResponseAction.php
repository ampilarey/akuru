<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Forms\Models\FormResponse;
use App\Domains\People\Actions\GuardianCanAccessStudentAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Validation\ValidationException;

/**
 * A guardian confirms their child's answer.
 *
 * The rule that gives this feature its point, and the one easiest to lose:
 * **acting as the pupil never grants the confirmation.** EduPage blocks
 * confirming while signed in as the child, and so does this — a child ticking
 * "yes, I am going" is not their parent agreeing to it, and if the same session
 * can do both then the confirmation records nothing.
 *
 * That constraint survives E7's account switcher by being enforced on identity
 * rather than on session: the confirming user must be a *different* user who is
 * a registered guardian of the pupil.
 */
class ConfirmFormResponseAction
{
    public function execute(int $responseId, int $guardianUserId): FormResponse
    {
        $response = FormResponse::query()->with('form')->findOrFail($responseId);
        $form = $response->form;

        if ($form === null || ! $form->requires_parent_confirmation) {
            throw ValidationException::withMessages([
                'response' => 'This form does not need a guardian to confirm it.',
            ]);
        }

        if ($response->user_id === null) {
            throw ValidationException::withMessages([
                'response' => 'An anonymous answer has nobody to confirm for.',
            ]);
        }

        // The whole point: the pupil cannot confirm their own answer, however
        // they are signed in.
        if ((int) $response->user_id === $guardianUserId) {
            throw ValidationException::withMessages([
                'response' => 'A pupil cannot confirm their own answer.',
            ]);
        }

        $student = app(ResolveStudentForUserAction::class)->execute((int) $response->user_id);
        if ($student === null) {
            throw ValidationException::withMessages([
                'response' => 'That answer was not given by a pupil.',
            ]);
        }

        if (! app(GuardianCanAccessStudentAction::class)->execute($guardianUserId, (int) $student['id'])) {
            throw ValidationException::withMessages([
                'response' => 'You are not a guardian of that pupil.',
            ]);
        }

        $response->update([
            'confirmed_at' => now(),
            'confirmed_by_user_id' => $guardianUserId,
        ]);

        return $response->refresh();
    }
}
