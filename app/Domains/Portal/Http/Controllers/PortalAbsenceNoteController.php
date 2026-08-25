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
            'types' => ['illness', 'medical_appointment', 'family_emergency', 'religious', 'other'],
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
            'type' => ['required', 'string', 'in:illness,medical_appointment,family_emergency,religious,other'],
            'affects_attendance' => ['sometimes', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

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
