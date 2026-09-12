<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\ListRecitationReviewQueueAction;
use App\Domains\Courses\Components\Quran\Actions\ReviewRecitationAction;
use App\Domains\Courses\Components\Quran\Actions\ServeRecitationAudioAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F4 teacher review queue (§52.10 non-AI subset): list submissions, mark
 * outcomes with mistakes, export CSV. Gated on a teacher row or
 * courses.manage.
 */
class TeachRecitationController extends Controller
{
    public function index(Request $request): InertiaResponse|StreamedResponse
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

        // SPEC §36 "Upload correction audio". Tajweed is a sound: "your madd is
        // short on ayah 4" describes the correction, three seconds of the
        // teacher reciting it *is* the correction.
        $correctionMediaId = null;
        if ($request->hasFile('correction_audio')) {
            $request->validate([
                'correction_audio' => ['file', 'max:20480', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm,video/webm'],
            ]);

            $stored = app(StorePrivateMediaAction::class)->execute(
                $request->file('correction_audio'),
                (int) $request->user()->id,
            );
            $correctionMediaId = (int) $stored['id'];
        }

        app(ReviewRecitationAction::class)->execute($submission, [
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'teacher_id' => $teacher['id'] ?? null,
            'reviewed_by' => (int) $request->user()->id,
            'mistakes' => $data['mistakes'] ?? [],
            'correction_audio_media_file_id' => $correctionMediaId,
        ]);

        return back()->with('success', $correctionMediaId !== null
            ? 'Recitation reviewed, with your correction recording attached.'
            : 'Recitation reviewed.');
    }

    /**
     * SPEC §36 "Play audio/voice submissions" — and the teacher's own
     * correction back the other way. These are recordings of a named student's
     * voice, so they are served through the Qur'an component's own
     * authorization rather than the catalogue media path, which asks whether a
     * file appears in a lesson.
     */
    public function audio(Request $request, int $submission, string $kind): Response
    {
        $file = app(ServeRecitationAudioAction::class)->execute($submission, $kind, $request->user());

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['original_name'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
