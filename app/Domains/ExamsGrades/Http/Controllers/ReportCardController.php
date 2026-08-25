<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\GenerateReportCardsAction;
use App\Domains\ExamsGrades\Actions\GenerateTranscriptAction;
use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\ListReportCardsAction;
use App\Domains\ExamsGrades\Actions\PublishReportCardsAction;
use App\Domains\ExamsGrades\Actions\SaveReportCardCommentAction;
use App\Domains\ExamsGrades\Models\ReportCard;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $classId = $request->integer('class_id') ?: null;
        $termId = $request->integer('term_id') ?: null;

        return Inertia::render('ExamsGrades/ReportCards/Index', [
            ...app(ListExamCatalogAction::class)->execute(),
            'cards' => app(ListReportCardsAction::class)->execute([
                'class_id' => $classId,
                'term_id' => $termId,
            ])->values(),
            'unpublished' => app(ListReportCardsAction::class)->unpublished()->values(),
            'classId' => $classId,
            'termId' => $termId,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'locale' => ['nullable', 'string', 'in:en,dv,ar'],
        ]);

        app(GenerateReportCardsAction::class)->execute(
            (int) $data['class_id'],
            (int) $data['term_id'],
            isset($data['template_id']) ? (int) $data['template_id'] : null,
            $data['locale'] ?? 'en',
            (int) $request->user()->id,
        );

        return redirect()
            ->route('exams.report-cards.index', ['class_id' => $data['class_id'], 'term_id' => $data['term_id']])
            ->with('success', 'Report cards queued.');
    }

    public function publish(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        app(PublishReportCardsAction::class)->execute((int) $data['class_id'], (int) $data['term_id']);

        return redirect()
            ->route('exams.report-cards.index', $data)
            ->with('success', 'Report cards published.');
    }

    public function comment(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $request->validate([
            'report_card_id' => ['required', 'integer'],
            'comment_type' => ['required', 'string'],
            'comment' => ['required', 'string'],
            'comment_arabic' => ['nullable', 'string'],
            'comment_dhivehi' => ['nullable', 'string'],
        ]);

        app(SaveReportCardCommentAction::class)->execute($data, (int) $request->user()->id);

        return redirect()->route('exams.report-cards.index')->with('success', 'Comment saved.');
    }

    public function download(Request $request, ReportCard $reportCard): HttpResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);
        abort_unless($reportCard->document_id, 404);

        $file = app(ReadGeneratedDocumentAction::class)->execute((int) $reportCard->document_id);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="report-card-'.$reportCard->id.'.html"',
        ]);
    }

    public function transcript(Request $request): HttpResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'locale' => ['nullable', 'string', 'in:en,dv,ar'],
        ]);

        $result = app(GenerateTranscriptAction::class)->execute(
            (int) $data['student_id'],
            $data['locale'] ?? 'en',
            (int) $request->user()->id,
        );

        return response($result['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="transcript-'.$data['student_id'].'.html"',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = app(ListReportCardsAction::class)->execute([
            'class_id' => $request->integer('class_id') ?: null,
            'term_id' => $request->integer('term_id') ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student', 'class', 'term', 'status', 'generated_at', 'published_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['student_name'],
                    $row['class_name'],
                    $row['term_name'],
                    $row['status'],
                    $row['generated_at'],
                    $row['published_at'],
                ]);
            }
            fclose($out);
        }, 'report-cards.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
