<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Models\DashboardAnalytics;
use App\Domains\Settings\Models\UserActivity;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Page-view bookkeeping happens after the response (ADMIN_PANEL.md §7 P3,
 * STATUS §5nn). `TrackUserActivity` sits on 943 routes and writes a
 * `user_activities` row and a `dashboard_analytics` upsert per signed-in
 * page view; it used to do so between building the response and sending
 * it, so a slow insert held the page. Now `handle()` writes nothing and
 * `terminate()` writes both, once the response has gone.
 *
 * Counts are deltas: signing a user in records activity of its own.
 */
function signedInRequestFor(string $path, string $routeName): Request
{
    $request = Request::create($path, 'GET');
    $route = Route::get($path, fn () => 'ok')->name($routeName);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('writes nothing while the response is being built', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $request = signedInRequestFor('/probe/settings', 'admin.settings.index');
    $before = UserActivity::count();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = app(TrackUserActivity::class)->handle($request, fn () => response('page'));
    $writes = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^(insert|update)/i', $q['query']) === 1);
    DB::disableQueryLog();

    expect($response->getContent())->toBe('page')
        ->and($writes)->toBeEmpty()
        ->and(UserActivity::count())->toBe($before);
});

it('records the visit once the response has gone', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $request = signedInRequestFor('/probe/settings', 'admin.settings.index');
    $response = response('page');
    $before = UserActivity::count();

    app(TrackUserActivity::class)->handle($request, fn () => $response);
    expect(UserActivity::count())->toBe($before);

    app(TrackUserActivity::class)->terminate($request, $response);

    $row = UserActivity::query()->latest('id')->first();
    expect(UserActivity::count())->toBe($before + 1)
        ->and($row->user_id)->toBe($user->id)
        ->and($row->activity_type)->toBe('admin_action')
        ->and($row->metadata['route'] ?? null)->toBe('admin.settings.index');
});

it('records a page view and its daily metric for an ordinary screen, and nothing for a guest', function () {
    $request = signedInRequestFor('/probe/page', 'portal.something');
    $response = response('page');
    $guestBefore = UserActivity::count();

    app(TrackUserActivity::class)->terminate($request, $response);
    expect(UserActivity::count())->toBe($guestBefore);

    $user = User::factory()->create();
    $this->actingAs($user);
    $before = UserActivity::where('activity_type', 'page_view')->count();
    app(TrackUserActivity::class)->terminate($request, $response);
    app(TrackUserActivity::class)->terminate($request, $response);

    expect(UserActivity::where('activity_type', 'page_view')->count())->toBe($before + 2)
        ->and(DashboardAnalytics::where('user_id', $user->id)->where('metric_type', 'page_views')->count())->toBe(1);
});

it('still records a visit made through the kernel', function () {
    $user = User::factory()->create();
    $before = UserActivity::where('user_id', $user->id)->count();

    $this->actingAs($user)->get('/dashboard');

    expect(UserActivity::where('user_id', $user->id)->count())->toBeGreaterThan($before);
});
