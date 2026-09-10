<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E4 walked over HTTP: a family opens the noticeboard and sees the notices
 * aimed at them, and only those.
 */
it('shows a family the notices aimed at them', function () {
    Role::findOrCreate('parent', 'web');

    makeNotice(['target_audience' => ['parents'], 'title' => 'Parent evening']);
    makeNotice(['target_audience' => ['teachers'], 'title' => 'Staff briefing']);

    $user = User::factory()->create();
    $user->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get('/portal/announcements')
        ->assertOk()
        ->assertSee('Parent evening')
        ->assertDontSee('Staff briefing');
});

it('shows an honest empty state when nothing is posted', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get('/portal/announcements')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Announcements')
            ->where('announcements', [])
        );
});

it('exports the same rows as the page', function () {
    Role::findOrCreate('parent', 'web');
    makeNotice(['target_audience' => ['parents'], 'title' => 'Parent evening']);
    makeNotice(['target_audience' => ['teachers'], 'title' => 'Staff briefing']);

    $user = User::factory()->create();
    $user->assignRole('parent');

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get('/portal/announcements/export')
        ->assertOk();

    $csv = $response->streamedContent();

    // The CSV must not leak what the page hides.
    expect($csv)->toContain('Parent evening')
        ->and($csv)->not->toContain('Staff briefing');
});

it('puts a noticeboard tile on the portal home with an urgent badge', function () {
    Role::findOrCreate('student', 'web');

    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    makeNotice(['priority' => 'urgent', 'title' => 'School closed']);
    makeNotice(['priority' => 'medium', 'title' => 'Sports day']);

    $user = User::query()->findOrFail($student->user_id);
    $user->assignRole('student');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get('/portal/home')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $tiles = collect($page->toArray()['props']['tiles']);
            $tile = $tiles->firstWhere('key', 'announcements');

            expect($tile)->not->toBeNull()
                ->and($tile['badge'])->toBe(1)
                ->and($tile['status'])->toBe('2 notices');

            return $page;
        });
});

it('requires a login', function () {
    $this->withoutLocalizationMiddleware()
        ->get('/portal/announcements')
        ->assertRedirect();
});
