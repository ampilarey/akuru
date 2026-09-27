<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\EnsureTeacherRowAction;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * KNOWN_ISSUES, "A registrant who leaves gender empty is recorded as male"
 * (BOOKSHOP_PLAN §15 finding 7): the public registration forms let gender be
 * left empty, `students.gender` was a required enum, and the writer filled
 * the gap with `male`. Since 2026-09-27 the column is nullable and an empty
 * answer is stored as one — for a registrant's own record, for a child, and
 * for the teacher row made from a user with no gender on file.
 */
function genderDetails(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Aishath',
        'last_name' => 'Ali',
        'dob' => now()->subYears(20)->toDateString(),
        'id_type' => 'national_id',
        'national_id' => 'a'.random_int(100000, 999999),
    ], $overrides);
}

it('stores an empty gender as empty for a registrant and a child, and keeps the one that was given', function () {
    $adult = User::factory()->create();
    $own = app(RegisterCourseStudentAction::class)->forSelf($adult->id, genderDetails(['gender' => '']));
    expect($own['gender'])->toBeNull()
        ->and(Student::query()->find($own['id'])->gender)->toBeNull();

    $parent = User::factory()->create();
    $child = app(RegisterCourseStudentAction::class)->forChild($parent->id, genderDetails(['first_name' => 'Ibrahim']));
    expect(Student::query()->find($child['id'])->gender)->toBeNull();

    $given = app(RegisterCourseStudentAction::class)->forChild($parent->id, genderDetails(['first_name' => 'Mariyam', 'gender' => 'female']));
    expect(Student::query()->find($given['id'])->gender)->toBe('female');

    // A later registration that names it fills it in; one that leaves it empty does not blank it.
    app(RegisterCourseStudentAction::class)->forSelf($adult->id, genderDetails(['gender' => 'female']));
    expect(Student::query()->find($own['id'])->gender)->toBe('female');
    app(RegisterCourseStudentAction::class)->forSelf($adult->id, genderDetails(['gender' => null]));
    expect(Student::query()->find($own['id'])->gender)->toBe('female');
});

it('gives a user with no gender on file a teacher row with none', function () {
    $school = makeSchool();
    $user = User::factory()->create(['name' => 'Hawwa Zahir', 'gender' => null]);

    $teacher = app(EnsureTeacherRowAction::class)->execute((int) $user->id, (int) $school->id);

    expect($teacher->gender)->toBeNull()
        ->and(Teacher::query()->find($teacher->id)->gender)->toBeNull();
});
