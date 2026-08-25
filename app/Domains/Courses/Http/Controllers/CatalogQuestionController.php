<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseSubjectsAction;
use App\Domains\Courses\Actions\ListQuestionsAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Enums\QuestionType;
use App\Domains\Courses\Models\Question;
use App\Domains\ExamsGrades\Actions\ListStandardsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogQuestionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/Questions', [
            'rows' => app(ListQuestionsAction::class)->execute($request->only([
                'subject_id',
                'course_id',
                'question_type',
            ]))->values(),
            'subjects' => app(ListCourseSubjectsAction::class)->execute()->values(),
            'standards' => app(ListStandardsAction::class)->execute()->values(),
            'types' => array_map(fn (QuestionType $type) => $type->value, QuestionType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveQuestionAction::class)->execute($this->payload($request) + [
            'created_by' => $request->user()?->id,
            'file' => $request->file('file'),
        ]);

        return redirect()->route('catalog.questions.index')->with('success', 'Question saved.');
    }

    public function update(Request $request, int $question): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $model = Question::query()->findOrFail($question);
        app(SaveQuestionAction::class)->execute($this->payload($request) + [
            'file' => $request->file('file'),
        ], $model);

        return redirect()->route('catalog.questions.index')->with('success', 'Question updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $rows = app(ListQuestionsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'question_type', 'pattern', 'title', 'question_text', 'difficulty']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['question_type'],
                    $row['pattern'],
                    $row['title'],
                    $row['question_text'],
                    $row['difficulty'],
                ]);
            }
            fclose($handle);
        }, 'questions.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return [
            'subject_id' => $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'course_id' => $request->filled('course_id') ? (int) $request->input('course_id') : null,
            'question_type' => (string) $request->input('question_type', ''),
            'title' => $request->input('title'),
            'question_text' => (string) $request->input('question_text', ''),
            'secondary_text' => $request->input('secondary_text'),
            'explanation' => $request->input('explanation'),
            'options' => $request->input('options'),
            'correct_answer' => $request->input('correct_answer'),
            'acceptable_answers' => $request->input('acceptable_answers'),
            'normalization_settings' => $request->input('normalization_settings'),
            'difficulty' => (string) $request->input('difficulty', 'medium'),
            'skill_tag' => $request->input('skill_tag'),
            'standard_ids' => $request->input('standard_ids', []),
        ];
    }
}
