<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\GenerateIdCardAction;
use App\Domains\ExamsGrades\Actions\GenerateTransferCertificateAction;
use App\Domains\ExamsGrades\Actions\IssueStudentAwardsAction;
use App\Domains\ExamsGrades\Actions\ListAwardsAction;
use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\SaveAwardAction;
use App\Domains\ExamsGrades\Models\Award;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AwardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        return Inertia::render('ExamsGrades/Awards/Index', [
            ...app(ListExamCatalogAction::class)->execute(),
            'awards' => app(ListAwardsAction::class)->awards()->values(),
            'students' => app(ListAwardsAction::class)->students()->values(),
            'issued' => app(ListAwardsAction::class)->issued([
                'academic_year_id' => $request->integer('academic_year_id') ?: null,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveAwardAction::class)->execute($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return redirect()->route('exams.awards.index')->with('success', 'Award saved.');
    }

    public function issue(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(IssueStudentAwardsAction::class)->execute($request->validate([
            'award_id' => ['required', 'integer'],
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['integer'],
            'academic_year_id' => ['required', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'awarded_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]), (int) $request->user()->id);

        return redirect()->route('exams.awards.index')->with('success', 'Awards issued.');
    }

    public function idCard(Request $request): HttpResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $result = app(GenerateIdCardAction::class)->execute((int) $data['student_id'], (int) $request->user()->id);

        return response($result['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="id-card-'.$data['student_id'].'.html"',
        ]);
    }

    public function transfer(Request $request): HttpResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $result = app(GenerateTransferCertificateAction::class)->execute((int) $data['student_id'], (int) $request->user()->id);

        return response($result['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="transfer-'.$data['student_id'].'.html"',
        ]);
    }

    public function download(Request $request, Award $award): HttpResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);
        $documentId = $request->integer('document_id');
        abort_unless($documentId, 404);
        $file = app(ReadGeneratedDocumentAction::class)->execute($documentId);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="award-'.$award->id.'.html"',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);
        $rows = app(ListAwardsAction::class)->issued([
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student', 'award', 'level', 'date']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['student_name'], $row['award'], $row['level'], $row['awarded_date']]);
            }
            fclose($out);
        }, 'student-awards.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
