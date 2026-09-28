<?php

namespace App\Domains\Portal\Actions;

use App\Domains\Courses\Actions\ListEnrolmentsMadeByAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;

/**
 * *My account* (docs/SIGN_IN_PLAN.md ID2b): the home of a signed-in person
 * who holds no other workspace — no role, no course of their own. Until ID2b
 * that person was shown the public course dashboard, a Blade page in the
 * website's layout with the courses open for enrolment down its side.
 *
 * What it holds is what such a person has (the shell asks for a password on
 * every workspace home, ID3): the children they registered on
 * the website that the office has not checked yet (the check is what opens
 * Family, ID2c); the enrolments they made, with where each stands. The doors
 * to the rest — courses, the Library, the Bookstore, the wallet — are the
 * workspace's own menu, which the page lays out as tiles.
 */
class ComposeAccountHomeAction
{
    /** How many of the latest enrolments the home shows; My enrolments has them all. */
    public const RECENT = 5;

    /**
     * @return array{children_waiting: list<array{id: int, name: string, refused: bool}>, enrolments: list<array<string, mixed>>, enrolments_total: int}
     */
    public function execute(int $userId): array
    {
        $enrolments = app(ListEnrolmentsMadeByAction::class);

        return [
            'children_waiting' => app(ListGuardianChildrenAction::class)->executePendingForGuardianUserId($userId)
                ->map(fn (object $child): array => [
                    'id' => (int) $child->id,
                    'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
                    'refused' => $child->verification_status === 'rejected',
                ])
                ->values()
                ->all(),
            'enrolments' => $enrolments->execute($userId, self::RECENT),
            'enrolments_total' => $enrolments->count($userId),
        ];
    }
}
