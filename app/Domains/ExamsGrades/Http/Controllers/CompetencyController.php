<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\SaveCompetencyAction;
use App\Domains\ExamsGrades\Actions\SaveCompetencyAssessmentAction;
use App\Domains\ExamsGrades\Models\Competency;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetencyController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $subjectId = $request->integer('subject_id') ?: null;

        return Inertia::render('ExamsGrades/Competencies/Index', [
            ...app(ListExamCatalogAction::class)->execute(),
            'subjectId' => $subjectId,
            'competencies' => Competency::query()
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Competency $row) => [
                    'id' => $row->id,
                    'subject_id' => $row->subject_id,
                    'name' => $row->name,
                    'name_arabic' => $row->name_arabic,
                    'name_dhivehi' => $row->name_dhivehi,
                    'description' => $row->description,
                    'sort_order' => $row->sort_order,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveCompetencyAction::class)->execute($request->validate([
            'subject_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]));

        return redirect()->route('exams.competencies.index')->with('success', 'Competency saved.');
    }

    public function assess(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage') || $request->user()?->can('exams.enter-any'), 403);

        app(SaveCompetencyAssessmentAction::class)->execute($request->validate([
            'student_id' => ['required', 'integer'],
            'competency_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
            'level' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]), (int) $request->user()->id);

        return redirect()->back()->with('success', 'Assessment saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = Competency::query()->orderBy('subject_id')->orderBy('sort_order')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'subject_id', 'name', 'sort_order']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->id, $row->subject_id, $row->name, $row->sort_order]);
            }
            fclose($out);
        }, 'competencies.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
