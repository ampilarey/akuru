<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\ExamsGrades\Actions\ListPublishedAwardsForGuardianAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalAwardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requested = $request->integer('student_id') ?: null;

        if ($requested && ! in_array($requested, $childIds, true)) {
            abort(403);
        }

        $studentId = $requested ?: ($childIds[0] ?? null);

        return Inertia::render('Portal/Awards', [
            'children' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
            ])->values(),
            'studentId' => $studentId,
            'awards' => app(ListPublishedAwardsForGuardianAction::class)->execute(
                (int) $request->user()->id,
                $studentId,
            )->values(),
        ]);
    }
}
