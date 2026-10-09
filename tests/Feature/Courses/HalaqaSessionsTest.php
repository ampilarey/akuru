<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Support\Navigation\BuildNavigationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * The halaqa sheet gets a door (STATUS §5pu; C19, found with CT5a).
 *
 * `/teach/quran-sessions/{id}` was reached only by typing its address: the
 * teacher's schedule links a session to attendance, and a Qur'an-only link
 * there would branch the Offerings core (rule 6). The Qur'an component now
 * lists its own sessions — a hifz course's — for the teacher who teaches them,
 * or every one for whoever runs the courses.
 */
uses(RefreshDatabase::class);

/** An offering on a course of the given type, with a session at `$when` taught by `$teacherUserId`. */
function puSession(string $courseType, ?int $teacherUserId, string $when, string $title): CourseOfferingSession
{
    $creator = User::factory()->create();
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => "{$title} course",
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $creator->id,
    ]);
    $course->course_type = $courseType;
    $course->save();
    $offering = CourseOffering::query()->where('course_id', $course->id)->first()
        ?? CourseOffering::query()->create([
            'course_id' => $course->id, 'title' => "{$title} offering", 'slug' => 'pu-'.$course->id,
            'delivery_mode' => 'face_to_face', 'status' => 'open', 'pin_mode' => 'latest',
            'academic_year_id' => makeYear(['name' => 'PU year '.$course->id])->id,
        ]);

    return CourseOfferingSession::query()->create([
        'course_offering_id' => $offering->id,
        'title' => $title,
        'session_type' => 'face_to_face',
        'starts_at' => $when,
        'teacher_user_id' => $teacherUserId,
    ]);
}

it('lists a teacher the halaqa sessions they teach, each opening its sheet', function () {
    $teacher = makeTeacherRow()->user;
    $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
    $other = makeTeacherRow()->user;

    $mine = puSession('hifz', $teacher->id, now()->addDay()->toDateTimeString(), 'Mine tomorrow');
    $yesterday = puSession('hifz', $teacher->id, now()->subDay()->toDateTimeString(), 'Mine yesterday');
    puSession('hifz', $other->id, now()->addDay()->toDateTimeString(), 'Someone else’s');
    puSession('standard', $teacher->id, now()->addDay()->toDateTimeString(), 'Not a halaqa');
    puSession('hifz', $teacher->id, now()->subDays(30)->toDateTimeString(), 'A month ago');

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('teach.quran-sessions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Teach/QuranSessions')
            ->where('scope', 'mine')
            ->where('sessions', fn ($rows) => collect($rows)->pluck('id')->all() === [$yesterday->id, $mine->id]));

    // The door leads to the sheet.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('teach.quran-sessions.show', $mine->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Teach/QuranSessionSheet'));

    // And the menu offers it.
    $hrefs = collect(app(BuildNavigationAction::class)->execute($teacher, 'en')['groups'])
        ->flatMap(fn (array $group) => $group['items'])
        ->pluck('href');
    expect($hrefs)->toContain('/teach/quran-sessions');
});

it('lists every halaqa session to whoever runs the courses, and none to a family', function () {
    $teacher = makeTeacherRow()->user;
    $a = puSession('hifz', $teacher->id, now()->addDay()->toDateTimeString(), 'Taught');
    $b = puSession('hifz', null, now()->addDays(2)->toDateTimeString(), 'No teacher yet');
    puSession('standard', null, now()->addDay()->toDateTimeString(), 'Not a halaqa');
    $office = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('teach.quran-sessions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'all')
            ->where('sessions', fn ($rows) => collect($rows)->pluck('id')->all() === [$a->id, $b->id]));

    $parent = User::factory()->create();
    $parent->assignRole(Role::findOrCreate('parent', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('teach.quran-sessions.index'))->assertForbidden();
});

it('serves the halaqa sessions in Dhivehi', function () {
    $office = actingPeopleAdmin(['courses.manage']);
    puSession('hifz', null, now()->addDay()->toDateTimeString(), 'Dhivehi list');

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('teach.quran-sessions.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('t.qsessions_title', trans('teach.qsessions_title', [], 'dv')));
    app()->setLocale('en');

    expect(trans('nav.quran_sessions', [], 'dv'))->not->toBe('Halaqa sessions')
        ->and(trans('nav.quran_sessions', [], 'ar'))->not->toBe('Halaqa sessions');
});
