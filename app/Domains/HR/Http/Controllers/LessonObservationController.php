<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Academics\Actions\ListSubjectsAction;
use App\Domains\HR\Actions\ListLessonObservationsAction;
use App\Domains\HR\Actions\SaveLessonObservationAction;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonObservationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        return Inertia::render('HR/Performance/Observations', [
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'classes' => app(ListClassesForYearAction::class)->execute()->values(),
            'subjects' => app(ListSubjectsAction::class)->execute()->values(),
            'rows' => app(ListLessonObservationsAction::class)->execute()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveLessonObservationAction::class)->execute($request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'date' => ['required', 'date'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'summary' => ['nullable', 'string'],
            'shared_with_staff' => ['sometimes', 'boolean'],
        ]) + ['observer_id' => $request->user()?->id]);

        return redirect()->route('hr.observations.index')->with('success', 'Observation saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListLessonObservationsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'date', 'class_name', 'subject_name', 'summary']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['staff_name'], $row['date'], $row['class_name'], $row['subject_name'], $row['summary']]);
            }
            fclose($out);
        }, 'lesson-observations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
