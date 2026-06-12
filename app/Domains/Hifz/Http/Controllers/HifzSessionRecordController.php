<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Services\HifzScoringService;
use App\Domains\People\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hifz\UpdateHifzSessionRecordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HifzSessionRecordController extends Controller
{
    public function __construct(
        protected HifzScopeService $scope,
        protected HifzScoringService $scoringService,
    ) {}

    public function update(UpdateHifzSessionRecordRequest $request, HifzSessionRecord $record): RedirectResponse
    {
        $record->fill($request->validated());
        $record->updated_by = auth()->id();
        $this->scoringService->applyToRecord($record, $request->filled('overall_status'));
        $record->save();

        return back()->with('success', 'Record saved.');
    }

    public function review(HifzSessionRecord $record): RedirectResponse
    {
        $this->authorize('review', $record);

        $record->update([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'requires_supervisor_review' => false,
        ]);

        return back()->with('success', 'Record reviewed.');
    }

    public function history(Student $student): View
    {
        abort_unless($this->scope->canAccessStudent(auth()->user(), $student), 403);

        $records = HifzSessionRecord::where('student_id', $student->id)
            ->with(['session', 'mistakes', 'newFromSurah', 'newToSurah'])
            ->latest()
            ->paginate(20);

        $legacyProgress = $student->quranProgress()->latest()->take(10)->get();

        return view('hifz.students.history', compact('student', 'records', 'legacyProgress'));
    }

    public function quranPage(HifzSessionRecord $record): View
    {
        $this->authorize('view', $record);

        $record->load(['student.user', 'teacher.user', 'program.supervisor', 'mistakes', 'session']);

        $mushaf = QuranMushaf::where('is_active', true)->first();
        $pageNumber = $record->newFromSurah?->index ? 1 : ($record->student->hifzEnrollments()->first()?->current_page ?? 1);

        $page = $mushaf?->pages()->where('page_number', $pageNumber)->with(['wordPositions.word'])->first();
        $ayahs = $page
            ? $mushaf->ayahs()->where('page_number', $pageNumber)->orderBy('surah_number')->orderBy('ayah_number')->get()
            : collect();

        $surahs = \App\Domains\Hifz\Models\Surah::orderBy('index')->get();

        return view('hifz.session-records.quran-page', compact('record', 'mushaf', 'page', 'ayahs', 'surahs', 'pageNumber'));
    }
}
