<?php

use App\Domains\Identity\Models\User;
use App\Domains\Portal\Actions\ComposeTeacherHomeAction;
use App\Support\Navigation\BuildNavigationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * TD1 (STATUS §5ub): the school audit — the seeded teacher and headmaster on a
 * phone, every page their menu offers, in Dhivehi and Arabic. Three links
 * answered 403 to the person they were offered to.
 */
uses(RefreshDatabase::class);

function td1Hrefs(User $user): array
{
    $nav = app(BuildNavigationAction::class)->execute($user, 'en', 'school');
    $hrefs = [];
    array_walk_recursive($nav, function ($value, $key) use (&$hrefs) {
        if ($key === 'href' && is_string($value)) {
            $hrefs[] = parse_url($value, PHP_URL_PATH);
        }
    });

    return array_values(array_unique($hrefs));
}

it('offers a teacher neither the timetable builder nor custom fields, and the office both', function () {
    $this->seed();
    $teacher = User::query()->where('email', 'teacher@akuru.edu.mv')->firstOrFail();
    $headmaster = User::query()->where('email', 'headmaster@akuru.edu.mv')->firstOrFail();

    expect(td1Hrefs($teacher))->not->toContain('/academics/timetable')->not->toContain('/people/custom-fields');
    expect(td1Hrefs($headmaster))->toContain('/academics/timetable');
    expect(in_array('/people/custom-fields', td1Hrefs($headmaster), true))->toBe($headmaster->can('custom_fields.manage'));

    // And whatever the teacher's menu offers under /academics or /people opens.
    foreach (array_filter(td1Hrefs($teacher), fn ($h) => preg_match('#^/(academics|people)/#', (string) $h)) as $href) {
        $this->withoutLocalizationMiddleware()->actingAs($teacher)->get($href)->assertStatus(200);
    }
});

it('sends a teacher\'s timetable tile to their own lessons, and the office\'s to the builder', function () {
    $this->seed();
    $teacher = User::query()->where('email', 'teacher@akuru.edu.mv')->firstOrFail();

    $tile = fn (array $roles) => collect(app(ComposeTeacherHomeAction::class)->execute($teacher->id, $roles)['tiles'])->firstWhere('key', 'timetable');
    expect($tile(['teacher'])['href'])->toBe('/portal/teacher#lessons')
        ->and($tile(['teacher', 'headmaster'])['href'])->toBe('/academics/timetable');
});

it('shows My meetings, empty, to staff with no teacher record, where it refused them', function () {
    $this->seed();
    $headmaster = User::query()->where('email', 'headmaster@akuru.edu.mv')->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs($headmaster)->get('/teach/meetings')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Teach/Meetings')->where('teacher', null)->where('slots', []));
    $this->withoutLocalizationMiddleware()->actingAs($headmaster)->get('/teach/meetings/export')->assertOk();
});
