<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\GenerateIdCardAction;
use App\Domains\ExamsGrades\Actions\GenerateTransferCertificateAction;
use App\Domains\ExamsGrades\Actions\IssueStudentAwardsAction;
use App\Domains\ExamsGrades\Actions\ListAwardsAction;
use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\SaveAwardAction;
use App\Domains\ExamsGrades\Models\Award;
use App\Domains\ExamsGrades\Models\StudentAward;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
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

    /**
     * One issued award's certificate.
     *
     * This used to bind `{award}` — the award *template* — and then read
     * `?document_id=` **straight from the query string**, ignoring the binding
     * entirely. `exams.manage` is held by teachers and exam staff, so any of
     * them could pass any document id and receive its contents: another
     * class's report cards, anybody's certificates, every generated document
     * in the application. The route parameter made it look scoped and scoped
     * nothing.
     *
     * That is the shape `PrivateMediaReadersAreScopedTest` was written about
     * after #336 — *"the one that legitimately takes an id straight from the
     * route ... is exactly why it needs an allow-list and why it is the one
     * that went wrong"* — on the documents path, which that gate cannot see.
     *
     * The document now comes from the record that owns it. A certificate
     * belongs to a `StudentAward` (the issue), not to an `Award` (the
     * template), which is also why the old binding could never have helped.
     */
    public function download(Request $request, StudentAward $studentAward): HttpResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);
        abort_unless($studentAward->certificate_document_id, 404);

        $file = app(ReadGeneratedDocumentAction::class)->execute((int) $studentAward->certificate_document_id);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="award-'.$studentAward->id.'.html"',
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
            Csv::put($out, ['student', 'award', 'level', 'date']);
            foreach ($rows as $row) {
                Csv::put($out, [$row['student_name'], $row['award'], $row['level'], $row['awarded_date']]);
            }
            fclose($out);
        }, 'student-awards.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
