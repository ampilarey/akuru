<?php

use App\Domains\Academics\Models\Announcement;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * S2 DoD: "legacy timetable/announcement Blade screens removed". The
 * announcements admin was the last Blade screen in S2: index, create and show,
 * with a compose form that offered English only and no class targeting
 * although the controller validated both. Now one React screen for staff;
 * families keep `/portal/announcements`.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\SchoolSeeder::class);
});

function staffAuthor(): User
{
    $admin = actingPeopleAdmin();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin->fresh();
}

it('renders the React admin for staff, with every notice and the form options', function () {
    $author = staffAuthor();
    Announcement::query()->create([
        'school_id' => 1, 'created_by' => $author->id, 'title' => 'Old, expired',
        'content' => 'Gone from the portal, still here.', 'type' => 'general', 'priority' => 'low',
        'publish_date' => '2020-01-01', 'expiry_date' => '2020-02-01', 'is_published' => true,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($author)
        ->get('/announcements')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Announcements/Index')
            ->has('announcements', 1)
            ->where('announcements.0.title', 'Old, expired')
            ->has('types')
            ->has('priorities')
            ->has('audiences')
            ->has('classes'));
});

it('sends a family account to the portal reader instead', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get('/announcements')
        ->assertRedirect('/portal/announcements');
});

it('publishes a trilingual, class-targeted notice with its HTML cleaned', function () {
    $author = staffAuthor();
    $year = makeYear();
    $class = makeClass($year, 'Grade 3 A');

    $this->withoutLocalizationMiddleware()
        ->actingAs($author)
        ->post('/announcements', [
            'title' => 'Sports day',
            'title_dhivehi' => 'ކުޅިވަރު ދުވަސް',
            'content' => '<p>On the field.</p><script>alert(1)</script>',
            'type' => 'event',
            'priority' => 'high',
            'target_audience' => ['parents', 'students'],
            'target_classes' => [$class->id],
            'publish_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(7)->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/announcements');

    $notice = Announcement::query()->where('title', 'Sports day')->firstOrFail();

    expect($notice->title_dhivehi)->toBe('ކުޅިވަރު ދުވަސް')
        ->and($notice->content)->not->toContain('<script')
        ->and($notice->content)->toContain('On the field.')
        ->and($notice->target_audience)->toBe(['parents', 'students'])
        ->and($notice->target_classes)->toBe([$class->id])
        ->and($notice->created_by)->toBe($author->id)
        ->and($notice->is_published)->toBeTrue();
});

it('refuses a bad type, an unknown class and an expiry before publication', function () {
    $author = staffAuthor();
    $base = ['title' => 'X', 'content' => 'Y', 'priority' => 'low', 'publish_date' => '2026-09-22'];

    $this->withoutLocalizationMiddleware()->actingAs($author)
        ->post('/announcements', $base + ['type' => 'gossip'])
        ->assertSessionHasErrors('type');
    $this->withoutLocalizationMiddleware()->actingAs($author)
        ->post('/announcements', $base + ['type' => 'general', 'target_classes' => [987654]])
        ->assertSessionHasErrors('target_classes.0');
    $this->withoutLocalizationMiddleware()->actingAs($author)
        ->post('/announcements', $base + ['type' => 'general', 'expiry_date' => '2026-09-01'])
        ->assertSessionHasErrors('expiry_date');

    expect(Announcement::query()->count())->toBe(0);
});

it('keeps the old names as redirects and has no compose screen of its own', function () {
    $author = staffAuthor();

    $this->withoutLocalizationMiddleware()->actingAs($author)
        ->get('/announcements/create')->assertRedirect('/announcements');
    $this->withoutLocalizationMiddleware()->actingAs($author)
        ->get('/announcements/1')->assertRedirect('/portal/announcements');

    expect(Route::has('announcements.edit'))->toBeFalse()
        ->and(Route::has('announcements.destroy'))->toBeFalse();
});
