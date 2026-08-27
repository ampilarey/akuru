<?php

namespace App\Domains\Courses\Components\Quran\Http\Controllers;

use App\Domains\Courses\Components\Quran\Actions\DecideQuranMilestoneAction;
use App\Domains\Courses\Components\Quran\Actions\ListQuranMilestoneBoardAction;
use App\Domains\Courses\Components\Quran\Actions\RecommendQuranMilestoneAction;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use App\Http\Controllers\Controller;
use App\Support\Contracts\HalaqaMilestoneWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F5-P3 (ADR-025 gate item 3): milestone recommend → supervisor-review →
 * approve/reject on the engine. Teachers recommend; review and decision are
 * courses.manage (the engine permission model does not split supervisor
 * from dean — recorded deviation).
 */
class TeachQuranMilestoneController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        $this->authorizeTeacher($request);
        $status = (string) $request->query('status', 'all');
        $payload = app(ListQuranMilestoneBoardAction::class)->execute($status);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($payload): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['id', 'program', 'student', 'type', 'title', 'status', 'recommended_at']);
                foreach ($payload['rows'] as $row) {
                    fputcsv($handle, [
                        $row['id'],
                        $row['program_name'],
                        $row['student_name'],
                        $row['type'],
                        $row['title'] ?? '',
                        $row['status'],
                        $row['recommended_at'] ?? '',
                    ]);
                }
                fclose($handle);
            }, 'quran-milestones.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Courses/Teach/QuranMilestones', $payload + [
            'status' => $status,
            'can_decide' => (bool) $request->user()->can('courses.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->authorizeTeacher($request);
        $data = $request->validate([
            'hifz_program_id' => 'required|integer',
            'student_id' => 'required|integer',
            'type' => 'required|string|max:40',
            'surah_number' => 'nullable|integer|min:1|max:114',
            'juz_number' => 'nullable|integer|min:1|max:30',
            'page_number' => 'nullable|integer|min:1',
            'title' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:5000',
        ]);

        app(RecommendQuranMilestoneAction::class)->execute($data + [
            'teacher_id' => $teacher['id'] ?? null,
            'recommended_by' => (int) $request->user()->id,
        ]);

        return back()->with('success', 'Milestone recommended.');
    }

    public function review(Request $request, int $milestone): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate(['note' => 'nullable|string|max:5000']);

        app(HalaqaMilestoneWriter::class)->review(
            $milestone,
            (int) $request->user()->id,
            $data['note'] ?? null,
        );

        return back()->with('success', 'Milestone reviewed.');
    }

    public function decide(Request $request, int $milestone): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'approved' => 'required|boolean',
            'note' => 'nullable|string|max:5000',
        ]);

        app(DecideQuranMilestoneAction::class)->execute(
            $milestone,
            (int) $request->user()->id,
            (bool) $data['approved'],
            $data['note'] ?? null,
        );

        return back()->with('success', 'Milestone decided.');
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
