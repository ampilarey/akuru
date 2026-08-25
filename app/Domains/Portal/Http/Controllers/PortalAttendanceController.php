<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        $childIds = $children->pluck('id')->all();
        $requested = $request->integer('student_id') ?: null;

        if ($requested && ! in_array($requested, $childIds, true)) {
            abort(403);
        }

        $studentId = $requested ?: ($childIds[0] ?? null);
        $rows = $studentId
            ? app(ListClassAttendanceAction::class)->execute(['student_id' => $studentId])
            : collect();

        return Inertia::render('Portal/Attendance', [
            'children' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
            ])->values(),
            'studentId' => $studentId,
            'rows' => $rows,
            'summary' => $studentId
                ? app(ListClassAttendanceAction::class)->studentSummary($studentId)->first()
                : null,
        ]);
    }
}
