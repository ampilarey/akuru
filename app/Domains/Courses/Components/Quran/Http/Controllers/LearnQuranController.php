<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\ListStudentQuranDashboardAction;
use App\Domains\Courses\Components\Quran\Actions\SubmitRecitationAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F4 student dashboard (§52.7 non-AI subset) — replaces the frozen Hifz
 * Blade student views for engine-keyed data.
 */
class LearnQuranController extends Controller
{
    /**
     * SPEC §30 lists mp3/m4a/ogg/webm for audio. `video/webm` is here because
     * that is what `MediaRecorder` produces on Chromium even for an audio-only
     * stream, which is the recorder this page uses.
     *
     * @var list<string>
     */
    private const ALLOWED_AUDIO_MIMES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/aac',
        'audio/ogg',
        'audio/wav',
        'audio/x-wav',
        'audio/webm',
        'video/webm',
    ];

    /** SPEC §30: "Student voice recordings: max 10MB". */
    private const MAX_AUDIO_BYTES = 10 * 1024 * 1024;

    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);
        $student = app(ResolveStudentForUserAction::class)->execute((int) $request->user()->id);
        $payload = $student
            ? app(ListStudentQuranDashboardAction::class)->execute((int) $student['id'])
            : ['submissions' => [], 'progress' => [], 'schedules' => []];

        return Inertia::render('Courses/Learn/Quran', $payload + ['student' => $student]);
    }

    /**
     * SPEC §52.9, the manual recording mode: the student records, replays, and
     * submits; Laravel stores the audio in **private** media and the teacher
     * reviews it later.
     *
     * Steps 6–9 of that flow have existed since F3 — `SubmitRecitationAction`
     * validates the surah through the reference contract, resolves the engine
     * enrolment, moves an answered assignment on, and hands letter+haraka
     * drills to the Pronunciation check when the flag is on. **Steps 1–5 had
     * no route.** The Action was reachable from six test files and from nothing
     * a student could press, so `quran_recitation_submissions` could only ever
     * be empty and the teacher's review queue had nothing to review (F4
     * recorded this as deferred: "needs private media upload + authenticated
     * streaming", both of which shipped afterwards).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $student = app(ResolveStudentForUserAction::class)->execute((int) $request->user()->id);
        abort_unless($student !== null, 403);

        $data = $request->validate([
            'surah_id' => ['required', 'integer'],
            'start_ayah_number' => ['required', 'integer', 'min:1'],
            'end_ayah_number' => ['nullable', 'integer', 'min:1', 'gte:start_ayah_number'],
            'quran_hifz_assignment_id' => ['nullable', 'integer'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:1800'],
            // §52.9 step 6 stores the audio, so there has to be audio. A
            // submission with nothing to listen to is a teacher being asked to
            // pass or fail a student they cannot hear.
            'audio' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', self::ALLOWED_AUDIO_MIMES)],
        ]);

        $stored = app(StorePrivateMediaAction::class)->execute(
            $request->file('audio'),
            (int) $request->user()->id,
            self::ALLOWED_AUDIO_MIMES,
            self::MAX_AUDIO_BYTES,
        );

        app(SubmitRecitationAction::class)->execute([
            'student_id' => (int) $student['id'],
            'surah_id' => $data['surah_id'],
            'start_ayah_number' => $data['start_ayah_number'],
            'end_ayah_number' => $data['end_ayah_number'] ?? $data['start_ayah_number'],
            'quran_hifz_assignment_id' => $data['quran_hifz_assignment_id'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'audio_media_file_id' => (int) $stored['id'],
        ]);

        return back()->with('success', 'Recitation submitted — your teacher will listen to it.');
    }
}
