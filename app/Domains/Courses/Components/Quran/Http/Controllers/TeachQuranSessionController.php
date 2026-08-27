<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\ListQuranSessionSheetAction;
use App\Domains\Courses\Components\Quran\Actions\ReviewQuranSessionRecordAction;
use App\Domains\Courses\Components\Quran\Actions\SaveQuranSessionRecordAction;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F5-P1 (ADR-025 gate item 1): the three-lane halaqa session sheet on the
 * engine — the browser path the legacy Blade HifzSessionRecordController
 * provided, now against engine sessions/enrollments.
 */
class TeachQuranSessionController extends Controller
{
    public function show(Request $request, int $session): Response|StreamedResponse
    {
        $this->authorizeTeacher($request);
        $payload = app(ListQuranSessionSheetAction::class)->execute($session);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($payload): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['student', 'attendance', 'new_result', 'new_score', 'recent_result', 'recent_score', 'old_result', 'old_score', 'mistakes', 'overall']);
                foreach ($payload['roster'] as $row) {
                    $record = $row['record'] ?? [];
                    fputcsv($handle, [
                        $row['student_name'],
                        $row['status'],
                        $record['new_result'] ?? '',
                        $record['new_score'] ?? '',
                        $record['recent_revision_result'] ?? '',
                        $record['recent_revision_score'] ?? '',
                        $record['old_revision_result'] ?? '',
                        $record['old_revision_score'] ?? '',
                        $record['mistake_count'] ?? '',
                        $record['overall_status'] ?? '',
                    ]);
                }
                fclose($handle);
            }, 'quran-session-sheet.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Courses/Teach/QuranSessionSheet', $payload);
    }

    public function storeRecord(Request $request, int $session): RedirectResponse
    {
        $this->authorizeTeacher($request);
        $data = $request->validate([
            'course_enrollment_id' => 'required|integer',
            'attendance_status' => 'nullable|string|in:present,late,absent,excused',
            'new_from_surah_id' => 'nullable|integer',
            'new_from_ayah' => 'nullable|integer|min:1',
            'new_to_surah_id' => 'nullable|integer',
            'new_to_ayah' => 'nullable|integer|min:1',
            'new_result' => 'nullable|string|max:20',
            'new_score' => 'nullable|integer|min:0|max:100',
            'recent_revision_text' => 'nullable|string|max:2000',
            'recent_revision_result' => 'nullable|string|max:20',
            'recent_revision_score' => 'nullable|integer|min:0|max:100',
            'old_revision_text' => 'nullable|string|max:2000',
            'old_revision_result' => 'nullable|string|max:20',
            'old_revision_score' => 'nullable|integer|min:0|max:100',
            'mistake_count' => 'nullable|integer|min:0',
            'haraka_mistakes' => 'nullable|integer|min:0',
            'word_mistakes' => 'nullable|integer|min:0',
            'fluency_mistakes' => 'nullable|integer|min:0',
            'teacher_note' => 'nullable|string|max:5000',
            'parent_visible_note' => 'nullable|string|max:5000',
            'next_target' => 'nullable|string|max:2000',
            'requires_parent_attention' => 'nullable|boolean',
            'requires_supervisor_review' => 'nullable|boolean',
            'overall_status' => 'nullable|string|max:20',
        ]);

        app(SaveQuranSessionRecordAction::class)->execute($session, $data + [
            'created_by' => (int) $request->user()->id,
        ]);

        return back()->with('success', 'Session record saved.');
    }

    public function review(Request $request, int $record): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate(['supervisor_note' => 'nullable|string|max:5000']);

        app(ReviewQuranSessionRecordAction::class)->execute(
            $record,
            (int) $request->user()->id,
            $data['supervisor_note'] ?? null,
        );

        return back()->with('success', 'Record reviewed.');
    }

    private function authorizeTeacher(Request $request): void
    {
        abort_unless($request->user() !== null, 403);
        $teacher = app(ResolveTeacherForUserAction::class)->execute((int) $request->user()->id);
        abort_unless($teacher !== null || $request->user()->can('courses.manage'), 403);
    }
}
