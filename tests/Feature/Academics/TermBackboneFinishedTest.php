<?php

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Academics\Actions\ListMeetingSlotOptionsAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Courses\Models\Course;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * S1.5, finished (ADR-037). The 2026-08-23 backbone migration added
 * `unified_term_id` beside `term_id` and backfilled it — rule 9's deploy 1 —
 * and deploy 2 never came: a month on, nothing read or wrote it, registration
 * still wrote the bare `term_id` from an unvalidated field, and the "active
 * year" was answered by two columns with the invariant enforced on one.
 */
function enrollmentRow(array $overrides = []): array
{
    $student = makeStudent();
    $course = Course::query()->first() ?? Course::factory()->create();

    return array_merge([
        'unified_student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrollment_type' => 'self',
        'progress_percentage' => 0,
        'payment_status' => 'not_required',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('constrains the enrolment term to the terms table and drops the dead columns', function () {
    $fks = collect(Schema::getForeignKeys('course_enrollments'))
        ->filter(fn (array $fk) => $fk['columns'] === ['term_id'])
        ->first();

    expect($fks)->not->toBeNull()
        ->and($fks['foreign_table'])->toBe('terms')
        ->and(Schema::hasColumn('course_enrollments', 'unified_term_id'))->toBeFalse()
        ->and(Schema::hasColumn('academic_years', 'terms'))->toBeFalse();
});

it('refuses an enrolment whose term does not exist', function () {
    expect(fn () => DB::table('course_enrollments')->insert(enrollmentRow(['term_id' => 987654])))
        ->toThrow(QueryException::class);
});

it('still catches a second course-only enrolment for the same pupil', function () {
    // The reason `term_key` survives the spec's "drop it": NULLs are distinct
    // in a MySQL unique index, so a key on `term_id` alone would let this in.
    $row = enrollmentRow(['term_id' => null]);
    DB::table('course_enrollments')->insert($row);

    expect(fn () => DB::table('course_enrollments')->insert($row))->toThrow(QueryException::class);
});

it('refuses to delete a term that still has enrolments, and never deletes the enrolment', function () {
    $year = makeYear();
    $term = makeTerm($year);
    $id = DB::table('course_enrollments')->insertGetId(enrollmentRow(['term_id' => $term->id]));

    expect(fn () => $term->delete())->toThrow(QueryException::class)
        ->and(DB::table('course_enrollments')->where('id', $id)->value('term_id'))->toBe($term->id);
});

it('refuses a registration that names a term which does not exist', function () {
    $course = Course::query()->first() ?? Course::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->from('/courses/'.$course->id.'/register')
        ->post('/courses/register/start', [
            'contact_type' => 'email',
            'contact_value' => 'nobody-'.uniqid().'@example.com',
            'course_id' => $course->id,
            'term_id' => 987654,
        ])
        ->assertSessionHasErrors('term_id');
});

it('answers "which year is active" from status alone', function () {
    // The FeaturePackDemoSeeder shape: `is_current` set, `status` not.
    $legacy = AcademicYear::query()->create([
        'name' => 'Legacy-flag year',
        'start_date' => '2024-09-01',
        'end_date' => '2025-06-30',
        'is_current' => true,
    ]);

    // The bridge turns the legacy flag into the real answer…
    expect($legacy->refresh()->status)->toBe(AcademicYearStatus::Active);

    // …and every reader asks status, not the flag.
    $legacy->forceFill(['status' => AcademicYearStatus::Closed])->save();
    $active = makeYear(['name' => 'Real active year', 'status' => AcademicYearStatus::Active]);

    // Drift the legacy flag by hand, as a raw write would.
    DB::table('academic_years')->where('id', $legacy->id)->update(['is_current' => true]);

    expect(app(ListAcademicYearsAction::class)->execute()->firstWhere('is_current', true)['id'])->toBe($active->id)
        ->and(app(ListMeetingSlotOptionsAction::class)->execute()['yearId'])->toBe($active->id)
        ->and($legacy->refresh()->is_current)->toBeTrue(); // the raw drift is real, and ignored
});

it('keeps the legacy flag in step whichever way a writer sets the year', function () {
    $year = makeYear(['status' => AcademicYearStatus::Upcoming]);
    expect($year->is_current)->toBeFalse();

    $year->forceFill(['status' => AcademicYearStatus::Active])->save();
    expect($year->refresh()->is_current)->toBeTrue();

    $year->forceFill(['is_current' => false])->save();
    expect($year->refresh()->status)->toBe(AcademicYearStatus::Upcoming);

    $year->forceFill(['status' => AcademicYearStatus::Closed])->save();
    $year->forceFill(['is_current' => false])->save();
    expect($year->refresh()->status)->toBe(AcademicYearStatus::Closed)
        ->and($year->is_current)->toBeFalse();
});
