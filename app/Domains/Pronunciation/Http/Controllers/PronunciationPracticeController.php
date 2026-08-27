<?php

namespace App\Domains\Pronunciation\Http\Controllers;

use App\Domains\Pronunciation\Actions\ListArabicSoundReferencesAction;
use App\Domains\Pronunciation\Actions\StoreArabicPronunciationAttemptAction;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Arabic B student surface: record an isolated letter+haraka and hand it
 * in. Works identically with AI off — the attempt just waits for a
 * teacher (rule 8).
 */
class PronunciationPracticeController extends Controller
{
    public function index(Request $request): Response
    {
        $references = app(ListArabicSoundReferencesAction::class)->execute();
        $recent = ArabicPronunciationAttempt::query()
            ->where('student_user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return Inertia::render('Pronunciation/Practice', [
            'letters' => $references['letters'],
            'harakas' => $references['harakas'],
            'attempts' => $recent->map(fn (ArabicPronunciationAttempt $attempt) => [
                'id' => $attempt->id,
                'letter_id' => $attempt->expected_letter_id,
                'haraka_id' => $attempt->expected_haraka_id,
                'status' => $attempt->status,
                'teacher_review_required' => (bool) $attempt->teacher_review_required,
                'at' => $attempt->created_at?->toDateTimeString(),
            ])->values()->all(),
            'ai_enabled' => (bool) config('ai.pronunciation_enabled'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expected_letter_id' => 'required|integer|exists:arabic_letters,id',
            'expected_haraka_id' => 'required|integer|exists:arabic_harakas,id',
            'audio' => 'required|file|max:10240',
            'duration_seconds' => 'nullable|integer|min:1|max:30',
        ]);

        app(StoreArabicPronunciationAttemptAction::class)->execute(
            (int) $request->user()->id,
            $data,
            $request->file('audio'),
        );

        return back()->with('success', 'Recording submitted — your teacher will hear it.');
    }
}
