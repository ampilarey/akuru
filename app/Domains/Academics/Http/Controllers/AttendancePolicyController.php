<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ResolveAttendanceSettingsAction;
use App\Domains\Academics\Actions\SaveAttendanceSettingsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** E10d. Thin (rule 5) — every bound lives in the Action. */
class AttendancePolicyController extends Controller
{
    public function index(): Response
    {
        $settings = app(ResolveAttendanceSettingsAction::class)->execute();

        return Inertia::render('Academics/AttendancePolicy/Index', [
            'settings' => [
                'mode' => $settings['mode']->value,
                'notify' => $settings['notify'],
                'chronic_threshold' => $settings['chronic_threshold'],
                'tardies_per_absence' => $settings['tardies_per_absence'],
                'part_lesson_minutes' => $settings['part_lesson_minutes'],
            ],
        ]);
    }

    public function update(Request $request, SaveAttendanceSettingsAction $save): RedirectResponse
    {
        $save->execute($request->all());

        return back()->with('success', 'Attendance policy saved. Reported figures use it from now on.');
    }
}
