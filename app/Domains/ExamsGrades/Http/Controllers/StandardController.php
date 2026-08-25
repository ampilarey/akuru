<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\ListExamsAction;
use App\Domains\ExamsGrades\Actions\ListStandardsCoverageAction;
use App\Domains\ExamsGrades\Actions\SaveStandardAction;
use App\Domains\ExamsGrades\Actions\TagStandardAction;
use App\Domains\ExamsGrades\Models\Standard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StandardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $subjectId = $request->integer('subject_id') ?: null;
        $termId = $request->integer('term_id') ?: null;

        return Inertia::render('ExamsGrades/Standards/Index', [
            ...app(ListExamCatalogAction::class)->execute(),
            'exams' => app(ListExamsAction::class)->execute(),
            'coverage' => app(ListStandardsCoverageAction::class)->execute($subjectId, $termId)->values(),
            'standards' => Standard::query()->orderBy('code')->get()->map(fn (Standard $row) => [
                'id' => $row->id,
                'code' => $row->code,
                'title' => $row->title,
                'subject_id' => $row->subject_id,
                'parent_id' => $row->parent_id,
                'active' => $row->active,
            ]),
            'subjectId' => $subjectId,
            'termId' => $termId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveStandardAction::class)->execute($request->validate([
            'subject_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return redirect()->route('exams.standards.index')->with('success', 'Standard saved.');
    }

    public function tag(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(TagStandardAction::class)->execute($request->validate([
            'standard_id' => ['required', 'integer'],
            'taggable_type' => ['required', 'string'],
            'taggable_id' => ['required', 'integer'],
        ]));

        return redirect()->route('exams.standards.index')->with('success', 'Standard tagged.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = app(ListStandardsCoverageAction::class)->execute(
            $request->integer('subject_id') ?: null,
            $request->integer('term_id') ?: null,
        );

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'title', 'exams_tagged', 'topics_tagged', 'covered']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['code'], $row['title'], $row['exams_tagged'], $row['topics_tagged'], $row['covered'] ? '1' : '0']);
            }
            fclose($out);
        }, 'standards-coverage.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
