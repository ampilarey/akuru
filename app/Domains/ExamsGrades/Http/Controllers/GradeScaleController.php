<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\SaveGradeScaleAction;
use App\Domains\ExamsGrades\Enums\GradeScaleType;
use App\Domains\ExamsGrades\Models\GradeScale;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradeScaleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        return Inertia::render('ExamsGrades/Scales/Index', [
            'scales' => GradeScale::query()->orderByDesc('is_default')->orderBy('name')->get()->map(fn (GradeScale $scale) => $this->serialize($scale)),
            'types' => array_map(fn (GradeScaleType $type) => $type->value, GradeScaleType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveGradeScaleAction::class)->execute($this->validated($request));

        return redirect()->route('exams.scales.index')->with('success', 'Grade scale saved.');
    }

    public function update(Request $request, GradeScale $gradeScale): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveGradeScaleAction::class)->execute($this->validated($request), $gradeScale);

        return redirect()->route('exams.scales.index')->with('success', 'Grade scale updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = GradeScale::query()->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'type', 'is_default', 'active', 'bands']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->name,
                    $row->type->value,
                    $row->is_default ? '1' : '0',
                    $row->active ? '1' : '0',
                    json_encode($row->bands),
                ]);
            }
            fclose($out);
        }, 'grade-scales.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(GradeScaleType::class)],
            'bands' => ['required'],
            'active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(GradeScale $scale): array
    {
        return [
            'id' => $scale->id,
            'name' => $scale->name,
            'type' => $scale->type->value,
            'bands' => $scale->bands,
            'active' => $scale->active,
            'is_default' => $scale->is_default,
        ];
    }
}
