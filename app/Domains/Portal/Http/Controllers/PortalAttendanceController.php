<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Notifications\Actions\ResolveAttendanceNotificationStateAction;
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

        if ($studentId) {
            // KNOWN_ISSUES #17. This was a boolean, and its `—` stood for four
            // different facts: nothing is sent for this status, the guardian
            // excused it themselves, a late message the school does send, and
            // an absence message that should have gone and did not. Only the
            // last is a problem, and it looked exactly like the other three.
            //
            // The old condition was also plainly wrong for `late`: it tested
            // `=== 'absent'`, so a late SMS the school genuinely sent was shown
            // to the parent as not sent.
            $state = app(ResolveAttendanceNotificationStateAction::class);
            $rows = $rows->map(function (array $row) use ($state) {
                $resolved = $state->execute(
                    (int) $row['student_id'],
                    (string) $row['date'],
                    $row['status'] ?? null,
                );
                $row['notification_state'] = $resolved;
                $row['notification_label'] = $state->label($resolved);

                return $row;
            });
        }

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
