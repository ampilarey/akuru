<?php

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Identity\Models\User;
use Database\Seeders\ClassSeeder;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SurahSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Two things the Hifz walk (STATUS §5fn) found the surviving Blade screens
 * doing quietly. Review and Approve on the milestones list redirect back with
 * a flash the list never rendered, so the row changed and the person who
 * pressed the button was told nothing; and the reports CSV streamed with no
 * content type, so it arrived as `text/html` — a browser shows it as a page
 * of commas rather than saving a file. Both now report back.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(SchoolSeeder::class);
    $this->seed(ClassSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SurahSeeder::class);
    $this->seed(HifzDemoSeeder::class);
});

it('tells the supervisor the milestone went to the dean, on the screen they pressed it from', function () {
    $supervisor = User::where('email', 'supervisor@akuru.edu.mv')->firstOrFail();
    $milestone = HifzMilestone::where('status', 'pending')->firstOrFail();

    $this->withoutLocalizationMiddleware()
        ->actingAs($supervisor)
        ->from(route('hifz.milestones.index'))
        ->post(route('hifz.milestones.supervisor-review', $milestone))
        ->assertRedirect(route('hifz.milestones.index'))
        ->assertSessionHas('success', 'Milestone reviewed and sent to dean.');

    $this->withoutLocalizationMiddleware()
        ->actingAs($supervisor)
        ->withSession(['success' => 'Milestone reviewed and sent to dean.'])
        ->get(route('hifz.milestones.index'))
        ->assertOk()
        ->assertSee('Milestone reviewed and sent to dean.');

    foreach (['milestones/index', 'enrollments/create'] as $view) {
        expect(file_get_contents(resource_path("views/hifz/{$view}.blade.php")))->toContain("@include('hifz.partials.alerts')");
    }
});

it('serves the reports export as CSV', function () {
    $dean = User::where('email', 'headmaster@akuru.edu.mv')->firstOrFail();

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($dean)
        ->get(route('hifz.reports.export', ['type' => 'sessions']))
        ->assertOk();

    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/csv')
        ->and((string) $response->headers->get('Content-Disposition'))->toContain('hifz-sessions-');
});
