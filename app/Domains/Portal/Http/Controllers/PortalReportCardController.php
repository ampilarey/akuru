<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\ExamsGrades\Actions\DownloadPublishedReportCardAction;
use App\Domains\ExamsGrades\Actions\GenerateTranscriptAction;
use App\Domains\ExamsGrades\Actions\ListPublishedReportCardsForGuardianAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use App\Support\Contracts\PdfConverterInterface;
use App\Support\Http\DocumentResponse;
use App\Support\Inertia\Phrases;
use App\Support\PersonName;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PortalReportCardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requested = $request->integer('student_id') ?: null;

        if ($requested && ! in_array($requested, $childIds, true)) {
            abort(403);
        }

        $studentId = $requested ?: ($childIds[0] ?? null);

        return Inertia::render('Portal/ReportCards', [
            'children' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => PersonName::ofStudent($child),
            ])->values(),
            'studentId' => $studentId,
            'pdf_available' => app(PdfConverterInterface::class)->enabled(),
            'cards' => app(ListPublishedReportCardsForGuardianAction::class)->execute(
                (int) $request->user()->id,
                $studentId,
            )->values(),
            't' => Phrases::once('portal'),
        ]);
    }

    public function download(Request $request, int $reportCard): HttpResponse
    {
        abort_unless($request->user() !== null, 403);

        $file = app(DownloadPublishedReportCardAction::class)->execute(
            $reportCard,
            (int) $request->user()->id,
        );

        // STATUS §5lr: ?format=pdf where this host can print one (ADR-012, amended).
        return DocumentResponse::make($file['contents'], $file['mime'], 'report-card-'.$reportCard, $request->query('format') === 'pdf');
    }

    public function transcript(Request $request): HttpResponse
    {
        abort_unless($request->user() !== null, 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'locale' => ['nullable', 'string', 'in:en,dv,ar'],
        ]);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        abort_unless($children->pluck('id')->contains((int) $data['student_id']), 403);

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
}
