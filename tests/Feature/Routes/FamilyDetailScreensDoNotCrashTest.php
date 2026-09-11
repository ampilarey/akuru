<?php

use App\Domains\Courses\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Family detail screens — the pages a student or parent opens to see **one**
 * lesson, activity, assessment or message thread.
 *
 * `DetailScreensDoNotCrashTest` sweeps the staff half as a super_admin. It
 * cannot sweep these: they are scoped to the person who owns the record, so an
 * administrator holding every permission would sail straight through screens
 * whose scoping is broken for the student they are built for. That is why these
 * seven sat in its pinned list rather than being swept badly.
 *
 * **The cast is enrolled on purpose.** `AuthorizeActivityAccessAction`,
 * `AuthorizeAssessmentAccessAction` and `AuthorizeLessonAccessAction` all refuse
 * a student with no `course_enrollments` row, and 403 is an allowed status here
 * — so an unenrolled cast would make this test pass while proving nothing about
 * the screens. The enrollment is what turns the sweep from "did not throw" into
 * "rendered for the student it belongs to".
 *
 * The assertion stays the narrow one: **no 5xx**.
 */

/**
 * The rows the family detail routes point at, owned by the cast's student.
 *
 * @return array<string, int>
 */
function familyDetailFixtures(int $studentId): array
{
    $course = Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Family walk', 'slug' => 'family-walk-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Family course',
        'slug' => 'family-course-'.Str::random(6),
        'short_desc' => 'For the detail sweep.',
        'body' => 'Body.',
        'cover_image' => '',
        'workflow_status' => 'published',
        'course_type' => 'general',
        'status' => 'open',
    ]);

    // Without this row every learn/* detail screen answers 403 and the sweep
    // proves nothing.
    //
    // Two student ids, not one, and the distinction is load-bearing.
    // `course_enrollments.student_id` still carries a foreign key to the legacy
    // `registration_students` table — the S1.1 unification left it there on
    // purpose (rule 9: never drop a populated column in the deploy that stops
    // using it), and STATUS records it as "posted enrollment id still legacy
    // RS". The authorize actions match on `unified_student_id`, which is the
    // People `students` row. A fixture that sets only one of them either fails
    // the foreign key or is refused 403.
    DB::table('course_enrollments')->insert([
        'student_id' => makeRegistrationStudent()->id,
        'unified_student_id' => $studentId,
        'course_id' => $course->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moduleId = DB::table('course_modules')->insertGetId([
        'course_id' => $course->id, 'title' => 'Module one', 'position' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $lessonId = DB::table('lessons')->insertGetId([
        'course_id' => $course->id, 'course_module_id' => $moduleId,
        'title' => 'Lesson one', 'slug' => 'family-lesson-'.Str::random(6),
        'position' => 1, 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $revisionId = DB::table('lesson_revisions')->insertGetId([
        'lesson_id' => $lessonId, 'revision_number' => 1,
        'snapshot_json' => json_encode(['title' => 'Lesson one', 'blocks' => []]),
        'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('lessons')->where('id', $lessonId)->update(['current_revision_id' => $revisionId]);

    // `pattern` is a backed enum (`ActivityPattern`: selection, text_input,
    // arrange, teacher_marked) and the model casts it, so an invented value
    // throws a ValueError while *reading* the row — which surfaces as a 500 on
    // the screen rather than an error on the insert. The shape here follows
    // `ActivityPatternTest`'s own selection payload rather than being guessed.
    $activityId = DB::table('activities')->insertGetId([
        'course_id' => $course->id, 'title' => 'Choose the meaning',
        'pattern' => 'selection', 'activity_type' => 'multiple_choice',
        'max_score' => 10,
        'data' => json_encode([
            'prompt' => 'Pick the right word',
            'options' => [['id' => 'a', 'label' => 'Book'], ['id' => 'b', 'label' => 'Pen']],
            'correct' => ['a'],
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // `published`, not the default draft: `AuthorizeAssessmentAccessAction`
    // refuses an unpublished assessment with a 403, which this sweep allows —
    // so a draft fixture would pass the test while exercising only the guard
    // clause.
    $assessmentId = DB::table('assessments')->insertGetId([
        'course_id' => $course->id, 'title' => 'Unit check',
        'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'course' => (int) $course->id,
        'lesson' => (int) $lessonId,
        'activity' => (int) $activityId,
        'assessment' => (int) $assessmentId,
    ];
}

it('loads every family detail screen without a server error', function () {
    $cast = portalCast();
    $ids = familyDetailFixtures($cast['child_id']);

    // uri => [actor, resolved path]
    $screens = [
        'learn/courses/{course}' => ['student', 'learn/courses/'.$ids['course']],
        'learn/lessons/{lesson}' => ['student', 'learn/lessons/'.$ids['lesson']],
        'learn/activities/{activity}' => ['student', 'learn/activities/'.$ids['activity']],
        'learn/assessments/{assessment}' => ['student', 'learn/assessments/'.$ids['assessment']],
    ];

    $crashed = [];

    foreach ($screens as $uri => [$who, $path]) {
        try {
            $response = $this->withoutLocalizationMiddleware()
                ->actingAs($cast[$who]->fresh())
                ->get('/'.$path);

            if ($response->getStatusCode() >= 500) {
                $crashed[] = sprintf('%s → %s (%s) → %d', $who, $path, $uri, $response->getStatusCode());
            }
        } catch (Throwable $e) {
            $crashed[] = sprintf('%s → %s threw %s: %s', $who, $path, $e::class, $e->getMessage());
        }
    }

    expect(count($screens))->toBeGreaterThan(3);

    expect($crashed)->toBeEmpty(
        count($crashed)." family detail screen(s) return a server error:\n".implode("\n", $crashed)
    );
});
