<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListMovementsForGuardianAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The family side of E18 — when my child arrived and when they left.
 *
 * The children are resolved here and their ids passed down, so the Academics
 * action never has to know how a guardian maps to a child (rule 3). Nothing a
 * guardian sends chooses the scope: the list is derived from who they are.
 *
 * This uses the general children list rather than pick-up's `can_pickup` one —
 * seeing when your child arrived is the ordinary parent permission, not the
 * stricter "may take them out of the building" one.
 */
class PortalMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $date = (string) $request->query('date', now()->toDateString());

        $children = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id);

        $ids = $children->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return Inertia::render('Portal/Movements', [
            'date' => $date,
            'has_children' => $children->isNotEmpty(),
            'movements' => app(ListMovementsForGuardianAction::class)->execute($ids, $date),
        ]);
    }
}
