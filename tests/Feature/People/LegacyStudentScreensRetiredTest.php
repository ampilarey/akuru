<?php

use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * S1 DoD: "All S1 screens live in React; corresponding legacy Blade screens
 * removed." The Blade student CRUD outlived the React directory by a month,
 * and it was not a harmless duplicate: `StudentController::store` and
 * `update` wrote `students.class_id` and never a `class_student` row, while
 * every roster reader — registers, attendance, the class page — reads
 * `class_student`. A pupil added or moved on that screen was on no register.
 *
 * `StudentDirectoryCrudTest` pins that the surviving writer puts the pupil on
 * the roster. This file pins that the other writer is gone, and that what
 * pointed at it still lands somewhere.
 */
it('no longer registers the Blade student CRUD routes', function () {
    foreach (['students.create', 'students.store', 'students.edit', 'students.update', 'students.destroy'] as $name) {
        expect(Route::has($name))->toBeFalse("Route [{$name}] should be gone.");
    }
});

it('sends an old students bookmark to the React directory, query intact', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->get('/students?search=Zunaira')
        ->assertRedirect('/people/students?search=Zunaira');
});

it('sends an old student record link to the React profile', function () {
    $student = makeStudent();

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->get('/students/'.$student->id)
        ->assertRedirect('/people/students/'.$student->id);
});

it('has nothing left to post a stale student form into', function () {
    // 405, not 403: the URI still answers GET (the redirect above), but no
    // route accepts a write on it. A parent could once create a pupil *and a
    // user account with a password of their choosing* here.
    $before = Student::query()->count();

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->post('/students', ['first_name' => 'Ghost', 'last_name' => 'Pupil'])
        ->assertStatus(405);

    expect(Student::query()->count())->toBe($before);
});

it('keeps the Hifz progress tab, now served by the module that owns its data', function () {
    config(['quran.module_enabled' => true]);
    $student = makeStudent(['first_name' => 'Hafiza', 'last_name' => 'Pupil']);

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->get('/students/'.$student->id.'/quran-progress')
        ->assertOk()
        ->assertViewIs('students.quran-progress')
        ->assertSee('Hafiza');
});

it('links the React profile to the Hifz tab only while the module is on', function () {
    $student = makeStudent();
    $admin = actingPeopleAdmin();

    config(['quran.module_enabled' => true]);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/people/students/'.$student->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Show')
            ->where('hifzProgressUrl', fn ($url) => str_ends_with((string) $url, '/students/'.$student->id.'/quran-progress')));

    // Off: no link, rather than a link to a 404 (§52.27).
    config(['quran.module_enabled' => false]);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/people/students/'.$student->id)
        ->assertInertia(fn (Assert $page) => $page->where('hifzProgressUrl', null));
});
