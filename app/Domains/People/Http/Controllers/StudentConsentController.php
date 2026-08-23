<?php

namespace App\Domains\People\Http\Controllers;

use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentSource;
use App\Domains\People\Enums\ConsentType;
use App\Domains\People\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentConsentController extends Controller
{
    public function store(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'consent_type' => ['required', 'in:'.implode(',', array_column(ConsentType::cases(), 'value'))],
            'granted' => ['required', 'boolean'],
        ]);

        app(RecordConsentAction::class)->execute(
            ConsentPersonType::Student,
            $student->id,
            $data['consent_type'],
            (bool) $data['granted'],
            (int) $request->user()->id,
            ConsentSource::Admin,
        );

        return redirect()
            ->route('people.students.show', ['student' => $student, 'tab' => 'consents'])
            ->with('success', 'Consent recorded.');
    }
}
