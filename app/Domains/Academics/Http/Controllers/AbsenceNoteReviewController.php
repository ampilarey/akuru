<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ApproveAbsenceNoteAction;
use App\Domains\Academics\Actions\ListAbsenceNotesAction;
use App\Domains\Academics\Actions\RejectAbsenceNoteAction;
use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsenceNoteReviewController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('manage_attendance'), 403);

        $status = $request->string('status')->toString() ?: null;

        return Inertia::render('Academics/AbsenceNotes/Index', [
            'status' => $status,
            'statuses' => array_map(fn (AbsenceNoteStatus $item) => $item->value, AbsenceNoteStatus::cases()),
            'notes' => app(ListAbsenceNotesAction::class)->execute(['status' => $status]),
        ]);
    }

    public function approve(Request $request, AbsenceNote $absenceNote): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_attendance'), 403);

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
        abort_unless($request->user()?->can('manage_attendance'), 403);

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
        abort_unless($request->user()?->can('manage_attendance'), 403);

        $rows = app(ListAbsenceNotesAction::class)->execute([
            'status' => $request->string('status')->toString() ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'date', 'student', 'type', 'status', 'reason', 'affects_attendance']);
            foreach ($rows as $row) {
                fputcsv($handle, [
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
}
