<?php

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Student;
use Database\Seeders\ClassSeeder;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SurahSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The Hifz port, slice 1 (BACKLOG C1, STATUS §5jv): the programme list,
 * form and page and a programme's enrolment list and form are Inertia
 * pages at their old addresses, keyed EN/DV/AR, with the scoping and the
 * policies as they were.
 */
function hifzSeeded(): void
{
    foreach ([RoleSeeder::class, SchoolSeeder::class, ClassSeeder::class, UserSeeder::class, SurahSeeder::class, HifzDemoSeeder::class] as $seeder) {
        test()->seed($seeder);
    }
}

function hifzUser(string $email): User
{
    $user = User::query()->where('email', $email)->firstOrFail();
    $user->markEmailAsVerified();

    return $user;
}

it('lists the programmes for the dean as an Inertia page, with the door to a new one', function () {
    hifzSeeded();
    $dean = hifzUser('headmaster@akuru.edu.mv');
    $program = HifzProgram::query()->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Programs')
            ->where('can_create', true)
            ->where('t.hifz_programs_title', 'Hifz Programs')
            ->has('programs.data', fn (Assert $rows) => $rows->each(fn (Assert $row) => $row->hasAll(['id', 'name', 'class', 'supervisor', 'teacher', 'status'])))
            ->where('programs.data', fn ($rows) => collect($rows)->contains('id', $program->id)));

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['hifz_programs_title', 'hifz_program_new', 'hifz_enroll_student', 'hifz_flash_enrolled'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});

it('creates a programme through the form and lands on its page with the flash', function () {
    hifzSeeded();
    $dean = hifzUser('headmaster@akuru.edu.mv');

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.programs.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/ProgramForm')
            ->where('program', null)
            ->has('classes')->has('supervisors')->has('teachers')->has('deans')->has('academic_years'));

    $response = $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->post(route('hifz.programs.store'), ['name' => 'Port Halaqa', 'description' => 'Ported.', 'class_id' => null, 'supervisor_id' => null, 'default_teacher_id' => null]);
    $program = HifzProgram::query()->where('name', 'Port Halaqa')->firstOrFail();
    $response->assertRedirect(route('hifz.programs.show', $program))->assertSessionHas('success', 'Hifz program created successfully.');

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Program')
            ->where('program.name', 'Port Halaqa')
            ->where('program.description', 'Ported.')
            ->where('can_update', true)
            ->where('can_assign_supervisor', true)
            ->has('supervisors')
            ->has('enrollments', 0));

    // Edit: the form arrives holding the programme, and a save changes it.
    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.programs.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/ProgramForm')->where('program.id', $program->id)->where('program.status', 'active'));
    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->put(route('hifz.programs.update', $program), ['name' => 'Port Halaqa', 'status' => 'inactive'])
        ->assertRedirect(route('hifz.programs.show', $program))->assertSessionHas('success', 'Hifz program updated successfully.');
    expect($program->fresh()->status->value)->toBe('inactive');
});

it('enrols a pupil through the form, defaulting the teacher to the programme\'s, and lists the enrolments', function () {
    hifzSeeded();
    $dean = hifzUser('headmaster@akuru.edu.mv');
    $program = HifzProgram::query()->firstOrFail();
    $student = Student::query()->orderBy('id')->firstOrFail();
    HifzEnrollment::query()->where('hifz_program_id', $program->id)->where('student_id', $student->id)->delete();

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.enrollments.create', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/EnrollmentForm')
            ->where('program.id', $program->id)
            ->where('students', fn ($rows) => collect($rows)->contains('id', $student->id))
            ->has('teachers')
            ->where('today', now()->toDateString()));

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->post(route('hifz.enrollments.store', $program), ['student_id' => $student->id, 'teacher_id' => null, 'start_date' => now()->toDateString(), 'current_page' => 12])
        ->assertRedirect(route('hifz.programs.show', $program))->assertSessionHas('success', 'Student enrolled successfully.');

    $enrollment = HifzEnrollment::query()->where('hifz_program_id', $program->id)->where('student_id', $student->id)->firstOrFail();
    expect($enrollment->teacher_id)->toBe($program->default_teacher_id)->and((int) $enrollment->current_page)->toBe(12);

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.enrollments.index', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Enrollments')
            ->where('program.name', $program->name)
            ->where('can_update', true)
            ->where('enrollments.data', fn ($rows) => collect($rows)->contains('student', $student->full_name)));
});

it('keeps the scoping: a teacher is refused a programme they are not assigned to, and sees no New Program', function () {
    hifzSeeded();
    $teacher = hifzUser('teacher@akuru.edu.mv');
    $other = HifzProgram::query()->create(['name' => 'Elsewhere', 'status' => 'active']);

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('hifz.programs.show', $other))
        ->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('hifz.programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Programs')->where('can_create', false)
            ->where('programs.data', fn ($rows) => ! collect($rows)->contains('id', $other->id)));
});
