<?php

use App\Domains\Academics\Models\ClassStudent;
use App\Domains\Identity\Models\User;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\StudentStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a school student with guardian, status history, class roster, and roster-picker match', function () {
    $admin = actingPeopleAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'A');
    $guardian = makeGuardian();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Index')
            ->has('schools')
            ->has('classes')
            ->has('guardians')
            ->has('relationships')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('people.students.store'), [
            'first_name' => 'Zunaira',
            'last_name' => 'Qasim',
            'date_of_birth' => '2013-04-04',
            'gender' => 'female',
            'student_id' => 'NEW-01',
            'class_id' => $class->id,
            'admission_date' => '2026-01-15',
            'status' => StudentStatus::Active->value,
            'guardian_id' => $guardian->id,
            'guardian_relationship' => GuardianRelationship::Mother->value,
            'is_primary' => true,
        ])
        ->assertRedirect();

    $student = Student::query()->where('first_name', 'Zunaira')->where('last_name', 'Qasim')->sole();

    expect($student->student_id)->toBe('NEW-01')
        ->and($student->class_id)->toBe($class->id)
        ->and($student->school_id)->toBe($class->school_id)
        ->and($student->user_id)->toBeNull()
        ->and($student->status)->toBe(StudentStatus::Active)
        ->and($student->admission_date?->toDateString())->toBe('2026-01-15');

    expect(StudentStatusHistory::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentStatusHistory::query()->where('student_id', $student->id)->first()->to_status)->toBe(StudentStatus::Active);

    expect($student->guardians()->where('parent_guardians.id', $guardian->id)->exists())->toBeTrue();
    expect(ClassStudent::query()->where('class_id', $class->id)->where('student_id', $student->id)->exists())->toBeTrue();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.students.index', ['search' => 'Zunaira']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Index')
            ->has('students', 1)
            ->where('students.0.first_name', 'Zunaira')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', ['classRoom' => $class, 'q' => 'Zunaira']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->has('candidates', 1)
            ->where('candidates.0.name', 'Zunaira Qasim')
            ->where('candidates.0.student_number', 'NEW-01')
            ->where('candidates.0.date_of_birth', '2013-04-04')
        );
});

it('creates a course-only student with nullable school fields', function () {
    $admin = actingPeopleAdmin();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('people.students.store'), [
            'first_name' => 'Iyas',
            'last_name' => 'Didi',
            'date_of_birth' => '2014-09-09',
            'gender' => 'male',
            'student_id' => '',
            'school_id' => '',
            'class_id' => '',
            'admission_date' => '',
            'status' => StudentStatus::Prospective->value,
        ])
        ->assertRedirect();

    $student = Student::query()->where('first_name', 'Iyas')->where('last_name', 'Didi')->sole();

    expect($student->school_id)->toBeNull()
        ->and($student->class_id)->toBeNull()
        ->and($student->student_id)->toBeNull()
        ->and($student->admission_date)->toBeNull()
        ->and($student->user_id)->toBeNull()
        ->and($student->status)->toBe(StudentStatus::Prospective);

    expect(StudentStatusHistory::query()->where('student_id', $student->id)->sole()->to_status)
        ->toBe(StudentStatus::Prospective);
});

it('updates a student through the directory and changes status via the action', function () {
    $admin = actingPeopleAdmin();
    $student = makeStudent([
        'first_name' => 'Hana',
        'last_name' => 'Moosa',
        'date_of_birth' => '2012-01-01',
        'gender' => 'female',
        'student_id' => 'HANA-1',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('people.students.update', $student), [
            'first_name' => 'Hana',
            'last_name' => 'Moosa-Updated',
            'date_of_birth' => '2012-02-02',
            'gender' => 'female',
            'student_id' => 'HANA-1',
            'status' => StudentStatus::Withdrawn->value,
        ])
        ->assertRedirect();

    $student->refresh();

    expect($student->last_name)->toBe('Moosa-Updated')
        ->and($student->date_of_birth?->toDateString())->toBe('2012-02-02')
        ->and($student->status)->toBe(StudentStatus::Withdrawn);

    expect(StudentStatusHistory::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentStatusHistory::query()->where('student_id', $student->id)->first()->to_status)->toBe(StudentStatus::Withdrawn);
});

it('does not mass-assign student status', function () {
    $student = makeStudent();

    $student->fill(['status' => StudentStatus::Withdrawn->value])->save();

    expect($student->fresh()->status)->toBe(StudentStatus::Active)
        ->and(StudentStatusHistory::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('forbids student create for a teacher', function () {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacher)
        ->post(route('people.students.store'), [
            'first_name' => 'No',
            'last_name' => 'Access',
            'date_of_birth' => '2011-01-01',
            'gender' => 'male',
            'status' => StudentStatus::Active->value,
        ])
        ->assertForbidden();

    expect(Student::query()->where('first_name', 'No')->where('last_name', 'Access')->exists())->toBeFalse();
});
