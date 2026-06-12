<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Enums\Hifz\HifzSessionStatus;
use App\Http\Controllers\Controller;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Services\HifzScoringService;
use App\Domains\Hifz\Services\HifzSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HifzSessionController extends Controller
{
    public function __construct(
        protected HifzScopeService $scope,
        protected HifzSessionService $sessionService,
        protected HifzScoringService $scoringService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', HifzSession::class);

        $user = auth()->user();
        $query = HifzSession::with(['program', 'teacher.user'])->latest('session_date');

        if (! $user->isHifzDean() && ! $user->isAdminLevel()) {
            $query->whereIn('hifz_program_id', $this->scope->assignedProgramIds($user));
        }

        if ($request->filled('program_id')) {
            $query->where('hifz_program_id', $request->program_id);
        }

        $sessions = $query->paginate(20);
        $programs = HifzProgram::whereIn('id', $this->scope->assignedProgramIds($user))->get();

        return view('hifz.sessions.index', compact('sessions', 'programs'));
    }

    public function create(Request $request): RedirectResponse
    {
        $this->authorize('create', HifzSession::class);

        $request->validate([
            'hifz_program_id' => 'required|exists:hifz_programs,id',
        ]);

        $program = HifzProgram::findOrFail($request->hifz_program_id);
        $this->authorize('view', $program);

        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            abort(403, 'Teacher profile required.');
        }

        $session = $this->sessionService->createSessionForToday($program, $teacher, auth()->user());

        return redirect()->route('hifz.sessions.edit', $session)
            ->with('success', 'Today\'s Hifz session is ready.');
    }

    public function edit(HifzSession $session): View
    {
        $this->authorize('update', $session);

        $session->load([
            'records.student.user',
            'records.newFromSurah',
            'records.newToSurah',
            'program.supervisor',
            'teacher.user',
        ]);

        $surahs = \App\Domains\Hifz\Models\Surah::orderBy('index')->get();

        return view('hifz.sessions.edit', compact('session', 'surahs'));
    }

    public function update(Request $request, HifzSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $session->update($request->validate([
            'title' => 'nullable|string|max:255',
            'general_note' => 'nullable|string',
            'status' => 'nullable|in:draft,completed,reviewed,locked',
        ]));

        if ($request->input('status') === 'completed') {
            $session->update(['status' => HifzSessionStatus::Completed]);
        }

        return back()->with('success', 'Session updated.');
    }

    public function bulkUpdateRecords(Request $request, HifzSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $records = $request->input('records', []);

        foreach ($records as $recordId => $data) {
            $record = $session->records()->find($recordId);
            if (! $record) {
                continue;
            }

            $this->authorize('update', $record);

            $record->fill($data);
            $record->updated_by = auth()->id();
            $this->scoringService->applyToRecord($record, ! empty($data['overall_status']));
            $record->save();
        }

        return back()->with('success', 'All student records saved.');
    }

    public function review(HifzSession $session): RedirectResponse
    {
        $this->authorize('review', $session);

        $session->update([
            'status' => HifzSessionStatus::Reviewed,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Session marked as reviewed.');
    }
}
