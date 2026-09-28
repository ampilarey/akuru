<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ApproveAbsenceNoteAction;
use App\Domains\Academics\Actions\ListAbsenceNotesAction;
use App\Domains\Academics\Actions\RejectAbsenceNoteAction;
use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsenceNoteReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeReview($request);

        $status = $request->string('status')->toString() ?: null;

        return Inertia::render('Academics/AbsenceNotes/Index', [
            'status' => $status,
            'statuses' => array_map(fn (AbsenceNoteStatus $item) => $item->value, AbsenceNoteStatus::cases()),
            'notes' => app(ListAbsenceNotesAction::class)->execute(['status' => $status]),
        ]);
    }

    public function approve(Request $request, AbsenceNote $absenceNote): RedirectResponse
    {
        $this->authorizeReview($request);

        $data = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);
        app(ApproveAbsenceNoteAction::class)->execute(
            $absenceNote,
            (int) $request->user()->id,
            $data['review_notes'] ?? null,
        );

        return redirect()->route('academics.absence-notes.index')->with('success', 'Absence note approved.');
    }

    public function reject(Request $request, AbsenceNote $absenceNote): RedirectResponse
    {
        $this->authorizeReview($request);

        $data = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);
        app(RejectAbsenceNoteAction::class)->execute(
            $absenceNote,
            (int) $request->user()->id,
            $data['review_notes'] ?? null,
        );

        return redirect()->route('academics.absence-notes.index')->with('success', 'Absence note rejected.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReview($request);

        $rows = app(ListAbsenceNotesAction::class)->execute([
            'status' => $request->string('status')->toString() ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'date', 'student', 'type', 'status', 'reason', 'affects_attendance']);
            foreach ($rows as $row) {
                Csv::put($handle, [
                    $row['id'],
                    $row['date'],
                    $row['student_name'],
                    $row['type'],
                    $row['status'],
                    $row['reason'],
                    $row['affects_attendance'] ? '1' : '0',
                ]);
            }
            fclose($handle);
        }, 'absence-notes.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeReview(Request $request): void
    {
        // Attendance staff, and the office: an absence note is a family's
        // request to the school, and since ADR-040 the educational admin, the
        // dean and the supervisor hold `requests.review` and none of the
        // attendance pair — so the screen answered them 403 while the decision
        // said the office answers families (STATUS §5jt).
        abort_unless(
            $request->user()?->can('manage_attendance') || $request->user()?->can('requests.review'),
            403,
        );
    }
}
