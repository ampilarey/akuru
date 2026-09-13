<?php

namespace App\Domains\Courses\Enums;

/**
 * SPEC §19 "Assessment Types" names eleven, and the system supports all
 * eleven — as a **hardcoded array inside a controller**:
 *
 *     'types' => ['lesson_quiz', 'module_test', 'placement_test', …],
 *
 * with `SaveAssessmentAction` storing whatever arrived:
 *
 *     'assessment_type' => (string) ($data['assessment_type'] ?? 'lesson_quiz'),
 *
 * and `CatalogAssessmentController::payload()` reading it through
 * `$request->input()` with **no `validate()` call anywhere on that path**. So
 * the list on screen was advisory: any string at all could be stored, and
 * `assessment_type` is what §19's reporting and §34–§37's dashboards group by.
 *
 * The repo's own convention (CLAUDE.md "Enums: string-backed PHP enums") is
 * what this should have been from the start. The controller now serves the
 * list from here, so the screen and the validator cannot drift apart.
 */
enum AssessmentType: string
{
    case LessonQuiz = 'lesson_quiz';
    case ModuleTest = 'module_test';
    case PlacementTest = 'placement_test';
    case FinalExam = 'final_exam';
    case Listening = 'listening';
    case Speaking = 'speaking';
    case Reading = 'reading';
    case Writing = 'writing';
    case Practical = 'practical';
    case Mixed = 'mixed';
    case Assignment = 'assignment';

    public function label(): string
    {
        return match ($this) {
            self::LessonQuiz => 'Lesson quiz',
            self::ModuleTest => 'Module test',
            self::PlacementTest => 'Placement test',
            self::FinalExam => 'Final exam',
            self::Listening => 'Listening assessment',
            self::Speaking => 'Speaking assessment',
            self::Reading => 'Reading assessment',
            self::Writing => 'Writing assessment',
            self::Practical => 'Practical assessment',
            self::Mixed => 'Mixed assessment',
            self::Assignment => 'Assignment-based assessment',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
