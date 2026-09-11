<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListAbsenceTypesAction;
use App\Domains\Academics\Actions\SaveAbsenceTypeAction;
use App\Domains\Academics\Models\AbsenceType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** E10c admin CRUD. Thin (rule 5). */
class AbsenceTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Academics/AbsenceTypes/Index', [
            // Inactive ones included: a retired reason is still the reason on
            // last term's notes, and the office needs to see it.
            'types' => app(ListAbsenceTypesAction::class)->execute(selectableOnly: false),
        ]);
    }

    public function store(Request $request, SaveAbsenceTypeAction $save): RedirectResponse
    {
        $save->execute($this->validated($request));

        return back()->with('success', 'Reason added. Families can choose it now.');
    }

    public function update(Request $request, AbsenceType $absenceType, SaveAbsenceTypeAction $save): RedirectResponse
    {
        $save->execute($this->validated($request), $absenceType);

        return back()->with('success', 'Reason updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:191'],
            'name_dhivehi' => ['nullable', 'string', 'max:191'],
            'name_arabic' => ['nullable', 'string', 'max:191'],
            'excuses_absence' => ['nullable', 'boolean'],
            'requires_evidence' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
