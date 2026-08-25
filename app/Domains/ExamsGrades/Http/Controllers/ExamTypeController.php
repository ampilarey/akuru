<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\SaveExamTypeAction;
use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamTypeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        return Inertia::render('ExamsGrades/Types/Index', [
            'types' => ExamType::query()->orderBy('name')->get()->map(fn (ExamType $type) => $this->serialize($type)),
            'codes' => array_map(fn (ExamTypeCode $code) => $code->value, ExamTypeCode::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveExamTypeAction::class)->execute($this->validated($request));

        return redirect()->route('exams.types.index')->with('success', 'Exam type saved.');
    }

    public function update(Request $request, ExamType $examType): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveExamTypeAction::class)->execute($this->validated($request), $examType);

        return redirect()->route('exams.types.index')->with('success', 'Exam type updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = ExamType::query()->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'name_arabic', 'name_dhivehi', 'code', 'default_weight', 'counts_toward_final', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->name,
                    $row->name_arabic,
                    $row->name_dhivehi,
                    $row->code->value,
                    $row->default_weight,
                    $row->counts_toward_final ? '1' : '0',
                    $row->active ? '1' : '0',
                ]);
            }
            fclose($out);
        }, 'exam-types.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'code' => ['required', Rule::enum(ExamTypeCode::class)],
            'default_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'counts_toward_final' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ExamType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'name_arabic' => $type->name_arabic,
            'name_dhivehi' => $type->name_dhivehi,
            'code' => $type->code->value,
            'default_weight' => $type->default_weight,
            'counts_toward_final' => $type->counts_toward_final,
            'active' => $type->active,
        ];
    }
}
