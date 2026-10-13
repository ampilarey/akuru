<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\ExamsGrades\Actions\ListPublishedExamsForGuardianAction;
use App\Domains\Portal\Actions\ResolvePortalHouseholdAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalExamController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $household = app(ResolvePortalHouseholdAction::class);
        $people = $household->people((int) $request->user()->id, $household->audience($request->user()));
        $childIds = array_column($people, 'id');
        $requested = $request->integer('student_id') ?: null;

        if ($requested && ! in_array($requested, $childIds, true)) {
            abort(403);
        }

        $studentId = $requested ?: ($childIds[0] ?? null);

        return Inertia::render('Portal/Exams', [
            'children' => array_map(fn (array $person): array => [
                'id' => $person['id'],
                'name' => $person['name'],
            ], $people),
            'studentId' => $studentId,
            'exams' => app(ListPublishedExamsForGuardianAction::class)->execute(
                (int) $request->user()->id,
                $studentId,
                $childIds,
            )->values(),
            't' => Phrases::once('portal'),
        ]);
    }
}
