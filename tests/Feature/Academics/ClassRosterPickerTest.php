<?php

use App\Domains\Academics\Models\ClassStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('searches roster candidates by identity fields and does not match primary keys', function () {
    $admin = actingPeopleAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'B');

    $intended = makeStudent([
        'first_name' => 'Fatima',
        'last_name' => 'Yoosuf',
        'date_of_birth' => '2010-03-12',
        'national_id' => 'A999001',
        'student_id' => 'PIL-01',
    ]);
    $leftover = makeStudent([
        'first_name' => 'Fatima',
        'last_name' => 'Yoosuf',
        'date_of_birth' => '2010-03-12',
        'national_id' => 'A999001',
        'student_id' => null,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', ['classRoom' => $class, 'q' => 'Fatima']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->has('candidates', 2)
            ->where('candidates.0.name', 'Fatima Yoosuf')
            ->where('candidates.0.date_of_birth', '2010-03-12')
            ->where('candidates.0.national_id', 'A999001')
            ->has('candidates.0.student_number')
            ->has('candidates.0.current_class')
            ->has('candidates.0.indistinguishable')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', ['classRoom' => $class, 'q' => 'PIL-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->has('candidates', 1)
            ->where('candidates.0.id', $intended->id)
            ->where('candidates.0.student_number', 'PIL-01')
        );

    $pkOnly = makeStudent([
        'first_name' => 'Zainab',
        'last_name' => 'Qasim',
        'date_of_birth' => '2011-08-08',
        'national_id' => 'NIDX',
        'student_id' => 'NUMX',
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', ['classRoom' => $class, 'q' => (string) $pkOnly->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->where('candidates', [])
        );
});

it('flags indistinguishable candidates and still requires an explicit student id to assign', function () {
    $admin = actingPeopleAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year, 'Grade 5', 'A');

    $first = makeStudent([
        'first_name' => 'Ahmed',
        'last_name' => 'Naseem',
        'date_of_birth' => '2009-04-04',
        'national_id' => 'A111111',
        'student_id' => null,
    ]);
    $second = makeStudent([
        'first_name' => 'Ahmed',
        'last_name' => 'Naseem',
        'date_of_birth' => '2009-04-04',
        'national_id' => 'A111111',
        'student_id' => null,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', ['classRoom' => $class, 'q' => 'Ahmed Naseem']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->has('candidates', 2)
            ->where('candidates.0.indistinguishable', true)
            ->where('candidates.1.indistinguishable', true)
            ->where('candidates.0.id', $first->id)
            ->where('candidates.1.id', $second->id)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.classes.assign', $class), [])
        ->assertSessionHasErrors('student_id');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.classes.assign', $class), ['student_id' => $second->id])
        ->assertRedirect();

    expect(ClassStudent::query()->where('class_id', $class->id)->where('student_id', $second->id)->exists())->toBeTrue()
        ->and(ClassStudent::query()->where('class_id', $class->id)->where('student_id', $first->id)->exists())->toBeFalse();
});
