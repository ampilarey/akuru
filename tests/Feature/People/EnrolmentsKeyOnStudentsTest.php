<?php

use App\Domains\Courses\Actions\CancelEnrollmentAction;
use App\Domains\Courses\Actions\CreateOrReviveEnrollmentAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * S1 Deploy 3, slice 1 (OWNER_ACTIONS item 10).
 *
 * The rule "one enrolment per student, course and term" lived on the legacy
 * `course_enrollments.student_id`, which references `registration_students`.
 * The next slice stops writing that column, and a key on a column nobody
 * writes stops nothing. These say the rule now lives on the unified student.
 */
function enrolmentKeyCourse(): int
{
    $admin = actingPeopleAdmin(['courses.manage']);

    return (int) app(SaveEngineCourseAction::class)->execute([
        'title' => 'Keyed on the student',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ])->id;
}

it('keeps one enrolment per unified student, course and term', function () {
    $courseId = enrolmentKeyCourse();
    $student = makeStudent();

    // Before Deploy 3, two legacy registration rows that unified into the
    // same student could each carry an enrolment past the old key.
    CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => $courseId,
        'status' => 'active',
    ]);

    expect(fn () => CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => $courseId,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('still keeps a second course-only enrolment out when the term is empty', function () {
    $courseId = enrolmentKeyCourse();
    $student = makeStudent();

    CourseEnrollment::query()->create(['unified_student_id' => $student->id, 'course_id' => $courseId, 'status' => 'active']);

    expect(fn () => CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => $courseId,
        'status' => 'active',
    ]))->toThrow(QueryException::class);
});

it('accepts an enrolment with no legacy registration row', function () {
    $courseId = enrolmentKeyCourse();
    $student = makeStudent();

    $enrolment = CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => $courseId,
        'status' => 'active',
    ]);

    expect($enrolment->fresh()->unified_student_id)->toBe($student->id);
});

it('revives an ended enrolment found by its unified student, legacy id or not', function () {
    $courseId = enrolmentKeyCourse();
    $student = makeStudent();

    $ended = CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => $courseId,
        'status' => 'active',
    ]);
    app(CancelEnrollmentAction::class)->execute((int) $ended->id);

    $revived = app(CreateOrReviveEnrollmentAction::class)->execute([
        'unified_student_id' => $student->id,
        'course_id' => $courseId,
        'status' => 'active',
    ]);

    expect($revived->id)->toBe($ended->id)
        ->and($revived->status)->toBe('active')
        ->and(CourseEnrollment::query()->where('unified_student_id', $student->id)->count())->toBe(1);
});

it('has the unified key in the schema, and the legacy column only as an archive', function () {
    $indexes = collect(DB::select("SHOW INDEX FROM course_enrollments WHERE Key_name = 'course_enrollments_unified_student_course_term_unique'"));

    expect($indexes->sortBy('Seq_in_index')->pluck('Column_name')->all())
        ->toBe(['unified_student_id', 'course_id', 'term_key'])
        ->and((int) $indexes->first()->Non_unique)->toBe(0);

    // Slice 3 archived the legacy pointer; slice 1 had made it optional.
    expect(Schema::hasColumn('course_enrollments', 'student_id'))->toBeFalse()
        ->and(Schema::hasColumn('course_enrollments', 'archived_registration_student_id'))->toBeTrue();
});
