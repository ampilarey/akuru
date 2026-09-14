<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseSubjectsAction;
use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Courses\Actions\ListQuestionsAction;
use App\Domains\Courses\Actions\NormalizeTextAnswerAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Enums\ActivityPattern;
use App\Domains\Courses\Enums\QuestionType;
use App\Domains\Courses\Models\Question;
use App\Domains\ExamsGrades\Actions\ListStandardsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            // §20 lists "Course ID nullable" on a question, and `index` has
            // always accepted a `course_id` filter — with no control on the
            // page able to set one, and no list to pick from. A bank meant to
            // be reusable across courses could not say which course a question
            // came from, nor be narrowed to one.
            'courses' => app(ListEngineCoursesAction::class)->execute()
                ->map(fn (array $course): array => ['id' => $course['id'], 'title' => $course['title']])
                ->values(),
            'filters' => [
                'subject_id' => $request->input('subject_id', ''),
                'course_id' => $request->input('course_id', ''),
                'question_type' => $request->input('question_type', ''),
            ],
            'standards' => app(ListStandardsAction::class)->execute()->values(),
            'types' => array_map(fn (QuestionType $type) => $type->value, QuestionType::cases()),
            // SPEC §18 applies to "auto-marked text input" only. Which types
            // those are is the enum's answer, not the client's — sending the
            // list keeps the builder from re-deriving a mapping that already
            // exists and would drift the moment a type is added.
            'textInputTypes' => array_values(array_map(
                fn (QuestionType $type) => $type->value,
                array_filter(
                    QuestionType::cases(),
                    fn (QuestionType $type) => $type->pattern() === ActivityPattern::TextInput,
                ),
            )),
            'normalizationFlags' => NormalizeTextAnswerAction::flags(),
            'normalizationModes' => NormalizeTextAnswerAction::modes(),
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
            Csv::put($handle, ['id', 'question_type', 'pattern', 'title', 'question_text', 'difficulty']);
            foreach ($rows as $row) {
                Csv::put($handle, [
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
     * CLAUDE.md rule 5: "authorize → validate into DTO → call Action".
     *
     * This built its array entirely out of `$request->input()` with **no
     * `validate()` call anywhere on the save path** — the same defect §19's
     * assessment form had. `SaveQuestionAction` rejects an unknown type and an
     * empty text, and everything else was trusted: `difficulty` silently
     * coerced to `medium` on any unrecognised value, and `subject_id` and
     * `course_id` were cast to int and stored whether or not the row existed.
     * `course_id` has a foreign key, so a bad one surfaced as a 500; `subject_id`
     * did not, so a bad one was simply kept.
     *
     * Casting is not validating. An input silently turned into something valid
     * is the failure mode that leaves an author certain they set a thing they
     * did not.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $validated = $request->validate([
            'subject_id' => ['nullable', 'integer', 'exists:course_subjects,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'question_type' => ['required', Rule::enum(QuestionType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'question_text' => ['required', 'string'],
            'secondary_text' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'skill_tag' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'video_title' => ['nullable', 'string', 'max:255'],
            'remove_attachment' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'subject_id' => $validated['subject_id'] ?? null,
            // §20 names a "Category ID nullable" and nothing in the system
            // defines what a question category *is* — no table, no foreign key,
            // and the same unanchored column on `glossary_items`. Passing it
            // through unvalidated is deliberate: giving it a control would mean
            // choosing a meaning for it, which is a decision, not a cleanup.
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'course_id' => $validated['course_id'] ?? null,
            'question_type' => (string) $validated['question_type'],
            'title' => $validated['title'] ?? null,
            'question_text' => (string) $validated['question_text'],
            'secondary_text' => $validated['secondary_text'] ?? null,
            'explanation' => $validated['explanation'] ?? null,
            // Shapes, not scalars: `SaveQuestionAction` owns what a valid
            // options array or normalization setting is, and already refuses
            // the malformed ones (§18's `ValidateNormalizationSettingsAction`).
            'options' => $request->input('options'),
            'correct_answer' => $request->input('correct_answer'),
            'acceptable_answers' => $request->input('acceptable_answers'),
            'normalization_settings' => $request->input('normalization_settings'),
            'difficulty' => (string) ($validated['difficulty'] ?? 'medium'),
            'skill_tag' => $validated['skill_tag'] ?? null,
            'standard_ids' => $request->input('standard_ids', []),
            // §20's fourth attachment kind. A reference, not an upload — it
            // shares §15's YouTube/Vimeo allowlist.
            'video_url' => $validated['video_url'] ?? null,
            'video_title' => $validated['video_title'] ?? null,
            'remove_attachment' => $validated['remove_attachment'] ?? null,
        ];
    }
}
