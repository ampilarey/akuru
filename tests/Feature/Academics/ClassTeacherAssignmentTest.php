<?php

use App\Domains\Academics\Models\ClassRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('assigns a class teacher from the create form and shows the name', function () {
    $admin = actingPeopleAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Index')
            ->has('teachers', 1)
            ->where('teachers.0.id', $teacher->user_id)
            ->where('teachers.0.name', 'Fatimat Ali')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.classes.store'), [
            'academic_year_id' => $year->id,
            'name' => 'Grade 5',
            'section' => 'B',
            'level' => 'Primary',
            'capacity' => 20,
            'class_teacher_id' => $teacher->user_id,
        ])
        ->assertRedirect();

    $class = ClassRoom::query()->where('name', 'Grade 5')->where('section', 'B')->sole();
    expect($class->class_teacher_id)->toBe($teacher->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Index')
            ->where('classes.0.class_teacher_name', 'Fatimat Ali')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.classes.show', $class))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Classes/Show')
            ->where('classRoom.class_teacher_name', 'Fatimat Ali')
        );
});
