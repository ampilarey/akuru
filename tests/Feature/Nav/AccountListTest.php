<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * docs/SIGN_IN_PLAN.md ID4: the phone's drawer opens with *Your accounts* —
 * every workspace the person holds, the current one marked, one tap to
 * switch — the way EduPage lists a parent's and a pupil's accounts. The shell
 * renders it from the shared `auth.workspaces`, so the list is on every
 * Inertia page, labelled in the request's language.
 */
function accountListParentLearner(): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('parent');
    $student = app(RegisterCourseStudentAction::class)->forSelf((int) $user->id, ['first_name' => 'Adult', 'last_name' => 'Learner', 'dob' => '1990-01-01']);
    CourseEnrollment::query()->create([
        'course_id' => Course::factory()->create()->id,
        'unified_student_id' => $student['id'],
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrolled_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    return $user;
}

it('gives every Inertia page the person\'s accounts, the current one first by the map, with the drawer\'s words', function () {
    $user = accountListParentLearner();

    foreach ([route('portal.home'), route('my.enrollments'), route('portal.notifications')] as $url) {
        $this->withoutLocalizationMiddleware()->actingAs($user)->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.workspace', 'family')
                ->where('auth.workspaces', fn ($list) => collect($list)->pluck('label')->all() === ['Family', 'My learning']
                    && collect($list)->pluck('href')->all() === ['/portal/home', '/learn'])
                ->where('i18n.nav.your_accounts', 'Your accounts')
                ->where('i18n.nav.language', 'Language'));
    }

    // Switching from the drawer posts the same choice the header's pill does.
    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('workspace.switch', 'learner'))->assertRedirect('/learn');
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('learn.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.workspace', 'learner'));
});

it('labels the accounts and the drawer in Dhivehi on a Dhivehi page', function () {
    $user = accountListParentLearner();
    app()->setLocale('dv');

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.workspaces.1.label', trans('nav.workspace_learner', [], 'dv'))
            ->where('i18n.nav.your_accounts', trans('nav.your_accounts', [], 'dv'))
            ->where('i18n.nav.language', trans('nav.language', [], 'dv')));

    expect(trans('nav.your_accounts', [], 'dv'))->not->toBe('Your accounts')
        ->and(trans('nav.workspace_learner', [], 'dv'))->not->toBe('My learning');
});
