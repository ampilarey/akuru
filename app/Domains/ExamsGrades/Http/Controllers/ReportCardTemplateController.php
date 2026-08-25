<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\SaveReportCardTemplateAction;
use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        return Inertia::render('ExamsGrades/ReportCards/Templates', [
            ...app(ListExamCatalogAction::class)->execute(),
            'templates' => ReportCardTemplate::query()->orderBy('name')->get()->map(fn (ReportCardTemplate $row) => [
                'id' => $row->id,
                'name' => $row->name,
                'applies_to' => $row->applies_to,
                'sections' => $row->sections,
                'header' => $row->header,
                'footer' => $row->footer,
                'active' => $row->active,
            ]),
            'sections' => SaveReportCardTemplateAction::SECTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveReportCardTemplateAction::class)->execute($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['nullable'],
            'sections' => ['nullable'],
            'header' => ['nullable', 'string', 'max:255'],
            'footer' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return redirect()->route('exams.report-templates.index')->with('success', 'Template saved.');
    }

    public function update(Request $request, ReportCardTemplate $reportCardTemplate): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveReportCardTemplateAction::class)->execute($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['nullable'],
            'sections' => ['nullable'],
            'header' => ['nullable', 'string', 'max:255'],
            'footer' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]), $reportCardTemplate);

        return redirect()->route('exams.report-templates.index')->with('success', 'Template updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = ReportCardTemplate::query()->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'sections', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->name, implode('|', $row->sections ?? []), $row->active ? '1' : '0']);
            }
            fclose($out);
        }, 'report-card-templates.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
