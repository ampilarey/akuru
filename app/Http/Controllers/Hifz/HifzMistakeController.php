<?php

namespace App\Http\Controllers\Hifz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hifz\StoreHifzMistakeRequest;
use App\Models\HifzMistake;
use App\Models\HifzSessionRecord;
use App\Services\Hifz\HifzMistakeCounterService;
use App\Services\Hifz\HifzScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class HifzMistakeController extends Controller
{
    public function __construct(
        protected HifzMistakeCounterService $counterService,
        protected HifzScopeService $scope,
    ) {}

    public function store(StoreHifzMistakeRequest $request): JsonResponse|RedirectResponse
    {
        $record = HifzSessionRecord::findOrFail($request->hifz_session_record_id);
        $this->authorize('update', $record);

        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            abort(403);
        }

        $mistake = HifzMistake::create([
            ...$request->validated(),
            'student_id' => $record->student_id,
            'teacher_id' => $teacher->id,
            'supervisor_id' => $record->supervisor_id,
        ]);

        $record = $this->counterService->afterMistakeChange($mistake);

        if ($request->wantsJson()) {
            return response()->json([
                'mistake' => $mistake->load('quranWord'),
                'counts' => [
                    'mistake_count' => $record->mistake_count,
                    'haraka_mistakes' => $record->haraka_mistakes,
                    'word_mistakes' => $record->word_mistakes,
                    'fluency_mistakes' => $record->fluency_mistakes,
                ],
            ]);
        }

        return back()->with('success', 'Mistake recorded.');
    }

    public function destroy(HifzMistake $mistake): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $mistake);

        $record = $mistake->sessionRecord;
        $mistake->delete();
        $record = $this->counterService->syncRecord($record);

        if (request()->wantsJson()) {
            return response()->json([
                'counts' => [
                    'mistake_count' => $record->mistake_count,
                    'haraka_mistakes' => $record->haraka_mistakes,
                ],
            ]);
        }

        return back()->with('success', 'Mistake removed.');
    }
}
