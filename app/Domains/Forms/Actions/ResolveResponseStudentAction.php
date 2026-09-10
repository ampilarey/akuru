<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Academics\Actions\ResolveAudienceContextAction;
use App\Domains\Forms\Models\Form;
use App\Domains\People\Actions\GuardianCanAccessStudentAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Validation\ValidationException;

/**
 * Which pupil an answer is about.
 *
 * Invoices are student-scoped, so a fee cannot be raised without this — and it
 * is the genuinely ambiguous part of E6c. A parent with two children in the
 * same class answering one trip form has not said which child is going, and
 * guessing is how the wrong family gets billed.
 *
 * The rules, in order:
 *  - a pupil answering for themselves is unambiguous;
 *  - a guardian with exactly one child the form is aimed at is unambiguous;
 *  - a guardian with several **must say which**, and the named child is checked
 *    against their own children rather than trusted.
 */
class ResolveResponseStudentAction
{
    /**
     * @param  list<string>  $roleNames
     */
    public function execute(Form $form, int $userId, array $roleNames, ?int $chosenStudentId = null): ?int
    {
        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            return (int) $self['id'];
        }

        $candidates = $this->candidatesFor($form, $userId, $roleNames);

        if ($chosenStudentId !== null) {
            // Never trust the posted id: a guardian may only answer for their
            // own children.
            if (! app(GuardianCanAccessStudentAction::class)->execute($userId, $chosenStudentId)) {
                throw ValidationException::withMessages([
                    'student_id' => 'That is not your child.',
                ]);
            }

            return $chosenStudentId;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if (count($candidates) > 1) {
            throw ValidationException::withMessages([
                'student_id' => 'Please say which child this is for.',
            ]);
        }

        return null;
    }

    /**
     * The children of this guardian that the form is actually aimed at.
     *
     * Narrowing by the form's own class targeting is what makes the common case
     * unambiguous: a parent of three with one child in Grade 5 does not have to
     * choose when the form is a Grade 5 trip.
     *
     * @param  list<string>  $roleNames
     * @return list<int>
     */
    private function candidatesFor(Form $form, int $userId, array $roleNames): array
    {
        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId);
        if ($children->isEmpty()) {
            return [];
        }

        $targetClasses = is_array($form->target_classes) ? array_map('intval', $form->target_classes) : [];
        if ($targetClasses === []) {
            return $children->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        }

        $matcher = app(ResolveAudienceContextAction::class);

        return $children
            ->filter(function ($child) use ($matcher, $targetClasses): bool {
                // Reuses the same class resolution the audience matcher uses,
                // so "aimed at" means the same thing everywhere.
                $context = $matcher->execute((int) ($child->user_id ?? 0), []);

                return array_intersect($targetClasses, $context['class_ids']) !== [];
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
