<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListAbsenceNotesAction;
use App\Domains\Academics\Actions\SubmitAbsenceNoteAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalAbsenceNoteController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        $childIds = $children->pluck('id')->all();

        return Inertia::render('Portal/AbsenceNotes', [
            'children' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
            ])->values(),
            'notes' => $childIds === []
                ? collect()
                : app(ListAbsenceNotesAction::class)->execute(['student_ids' => $childIds]),
            // E10c: the school's own reasons, not five strings compiled in.
            'types' => app(\App\Domains\Academics\Actions\ListAbsenceTypesAction::class)->execute(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'reason' => ['required', 'string', 'max:2000'],
            // E10c: the form sends an id now. The old `type` code is still
            // accepted, because a hard break here would silently stop any
            // client that has not been redeployed — the mobile scaffold
            // included — and `SubmitAbsenceNoteAction` resolves either.
            'absence_type_id' => ['nullable', 'integer', 'exists:absence_types,id'],
            'type' => ['nullable', 'string', 'max:40', 'exists:absence_types,code'],
            'affects_attendance' => ['sometimes', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        if (($data['absence_type_id'] ?? null) === null && ($data['type'] ?? null) === null) {
            return back()->withErrors(['absence_type_id' => 'Choose a reason.']);
        }

        $childIds = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->pluck('id')
            ->all();

        abort_unless(in_array((int) $data['student_id'], $childIds, true), 403);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('absence-notes', 'local');
        }

        app(SubmitAbsenceNoteAction::class)->execute([
            ...$data,
            'created_by' => (int) $request->user()->id,
            'attachment_path' => $path,
        ]);

        return redirect()->route('portal.absence-notes')->with('success', 'Absence note submitted.');
    }
}
