<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\ListGradingCatalogAction;
use App\Domains\ExamsGrades\Actions\ResolveWeightSchemeAction;
use App\Domains\ExamsGrades\Actions\SaveWeightSchemeAction;
use App\Domains\ExamsGrades\Models\AssessmentWeightScheme;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeightSchemeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $catalog = app(ListGradingCatalogAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: null;
        $classId = $request->integer('class_id') ?: null;
        $subjectId = $request->integer('subject_id') ?: null;

        $resolved = ($yearId !== null)
            ? app(ResolveWeightSchemeAction::class)->execute($yearId, $classId ?: null, $subjectId ?: null)
            : null;

        return Inertia::render('ExamsGrades/Weights/Index', [
            ...$catalog,
            'examTypes' => ExamType::query()->where('active', true)->orderBy('name')->get()
                ->map(fn (ExamType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code->value,
                    'default_weight' => (int) $type->default_weight,
                ]),
            'schemes' => AssessmentWeightScheme::query()->orderBy('academic_year_id')->get()->map(fn (AssessmentWeightScheme $scheme) => $this->serialize($scheme)),
            'resolve' => [
                'academic_year_id' => $yearId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'scheme' => $resolved !== null ? $this->serialize($resolved) : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $this->validated($request);
        app(SaveWeightSchemeAction::class)->execute($data);

        return redirect()
            ->route('exams.weights.index', [
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['class_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
            ])
            ->with('success', 'Weight scheme saved.');
    }

    public function update(Request $request, AssessmentWeightScheme $weightScheme): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $this->validated($request);
        app(SaveWeightSchemeAction::class)->execute($data, $weightScheme);

        return redirect()
            ->route('exams.weights.index', [
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['class_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
            ])
            ->with('success', 'Weight scheme updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = AssessmentWeightScheme::query()->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'academic_year_id', 'class_id', 'subject_id', 'weights']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->academic_year_id,
                    $row->class_id,
                    $row->subject_id,
                    json_encode($row->weights),
                ]);
            }
            fclose($out);
        }, 'weight-schemes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'class_id' => $request->input('class_id') ?: null,
            'subject_id' => $request->input('subject_id') ?: null,
        ]);

        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'weights' => ['required', 'array'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AssessmentWeightScheme $scheme): array
    {
        return [
            'id' => $scheme->id,
            'academic_year_id' => $scheme->academic_year_id,
            'class_id' => $scheme->class_id,
            'subject_id' => $scheme->subject_id,
            'weights' => $scheme->weights,
        ];
    }
}
