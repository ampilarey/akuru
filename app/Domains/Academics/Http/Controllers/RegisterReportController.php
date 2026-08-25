<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\GenerateExpectedRegistersAction;
use App\Domains\Academics\Actions\ListUnfilledRegistersAction;
use App\Domains\Academics\Actions\UnlockRegisterAction;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\LessonLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegisterReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('registers.manage'), 403);

        $year = $this->year($request);
        $lister = app(ListUnfilledRegistersAction::class);

        return Inertia::render('Academics/Registers/Unfilled', [
            'yearId' => $year?->id,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'unfilled' => $lister->execute($year?->id),
            'fillRates' => $lister->fillRates($year?->id),
            'planAdherence' => $lister->planAdherence($year?->id),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.manage'), 403);

        $data = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $result = app(GenerateExpectedRegistersAction::class)->execute(
            isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
            $data['from'] ?? null,
            $data['to'] ?? null,
        );

        return redirect()
            ->route('academics.registers.index', $request->only(['academic_year_id']))
            ->with('success', "Created {$result['created']} expected registers.");
    }

    public function unlock(Request $request, LessonLog $lessonLog): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.manage'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        app(UnlockRegisterAction::class)->execute($lessonLog, (int) $request->user()->id, $data['reason']);

        return redirect()
            ->route('academics.registers.show', $lessonLog)
            ->with('success', 'Register unlocked.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('registers.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: null;
        $rows = app(ListUnfilledRegistersAction::class)->execute($yearId);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'date', 'status', 'class', 'subject', 'teacher_id', 'period']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['date'],
                    $row['status'],
                    $row['class_name'],
                    $row['subject_name'],
                    $row['teacher_id'],
                    $row['period_name'],
                ]);
            }

            fclose($handle);
        }, 'unfilled-registers.csv', ['Content-Type' => 'text/csv']);
    }

    private function year(Request $request): ?AcademicYear
    {
        $yearId = $request->integer('academic_year_id');

        if ($yearId) {
            return AcademicYear::query()->find($yearId);
        }

        return AcademicYear::query()->where('status', 'active')->first();
    }
}
