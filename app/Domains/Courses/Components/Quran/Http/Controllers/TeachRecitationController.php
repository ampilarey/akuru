<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\ListRecitationReviewQueueAction;
use App\Domains\Courses\Components\Quran\Actions\ReviewRecitationAction;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F4 teacher review queue (§52.10 non-AI subset): list submissions, mark
 * outcomes with mistakes, export CSV. Gated on a teacher row or
 * courses.manage.
 */
class TeachRecitationController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        $teacher = $this->authorizeTeacher($request);
        $status = (string) $request->query('status', 'submitted');
        $payload = app(ListRecitationReviewQueueAction::class)->execute($status);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($payload): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['id', 'student', 'surah', 'from', 'to', 'mode', 'status', 'submitted_at', 'mistakes']);
                foreach ($payload['rows'] as $row) {
                    fputcsv($handle, [
                        $row['id'],
                        $row['student']['name'] ?? '',
                        $row['surah'] ?? '',
                        $row['start_ayah_number'],
                        $row['end_ayah_number'],
                        $row['mode'],
                        $row['status'],
                        $row['submitted_at'],
                        $row['mistake_count'],
                    ]);
                }
                fclose($handle);
            }, 'recitation-queue.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Courses/Teach/RecitationQueue', $payload + [
            'status' => $status,
            'teacher' => $teacher,
        ]);
    }

    public function review(Request $request, int $submission): RedirectResponse
    {
        $teacher = $this->authorizeTeacher($request);
        $data = $request->validate([
            'status' => 'required|string|in:teacher_reviewed,needs_repeat,passed,failed',
            'note' => 'nullable|string|max:2000',
            'mistakes' => 'array',
            'mistakes.*.mistake_type' => 'nullable|string|max:40',
            'mistakes.*.severity' => 'nullable|string|max:20',
            'mistakes.*.ayah_number' => 'nullable|integer|min:1',
            'mistakes.*.word_position' => 'nullable|integer|min:1',
            'mistakes.*.comment' => 'nullable|string|max:1000',
        ]);

        app(ReviewRecitationAction::class)->execute($submission, [
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'teacher_id' => $teacher['id'] ?? null,
            'reviewed_by' => (int) $request->user()->id,
            'mistakes' => $data['mistakes'] ?? [],
        ]);

        return back()->with('success', 'Recitation reviewed.');
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function authorizeTeacher(Request $request): ?array
    {
        abort_unless($request->user() !== null, 403);
        $teacher = app(ResolveTeacherForUserAction::class)->execute((int) $request->user()->id);
        abort_unless($teacher !== null || $request->user()->can('courses.manage'), 403);

        return $teacher;
    }
}
