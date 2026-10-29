<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use App\Domains\Settings\Actions\NotifyBackupFailureAction;
use App\Domains\Settings\Actions\RunBackupAction;
use App\Domains\Settings\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * BK2 (STATUS §5ur): System Settings shows the last good backup and turns
 * red when it is more than 26 hours old; a failed run, or one kept on this
 * server only, is told to every super admin.
 */
uses(RefreshDatabase::class);

function bk2SuperAdmin(): User
{
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('shows the last good backup, and turns red after 26 hours without one', function () {
    $admin = bk2SuperAdmin();
    $web = test()->withoutLocalizationMiddleware()->actingAs($admin);

    // None yet: red, and says so.
    $web->get('/admin/settings')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Index')
        ->where('backup.stale', true)->where('backup.last_good', null)->where('backup.offsite_configured', false)->has('t.backup_never'));

    BackupRun::query()->create(['name' => 'akuru-20261029-030000.akb', 'status' => 'succeeded', 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3), 'size_bytes' => 5 * 1048576, 'offsite' => true]);
    $web->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('backup.stale', false)
        ->where('backup.last_good.size_mb', 5)->where('backup.last_good.offsite', true));

    // A failed run since: the good one still counts, and the failure is shown.
    BackupRun::query()->create(['status' => 'failed', 'started_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'error' => 'mysqldump failed: access denied']);
    $web->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('backup.stale', false)
        ->where('backup.last.status', 'failed')->where('backup.last.error', 'mysqldump failed: access denied'));

    $this->travel(30)->hours();
    $web->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('backup.stale', true));

    // Only the super admin sees it.
    test()->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();
});

it('tells every super admin of a failed or local-only run, and nobody of a good one', function () {
    $first = bk2SuperAdmin();
    $second = bk2SuperAdmin();
    $bystander = User::factory()->create();
    $notify = app(NotifyBackupFailureAction::class);

    expect($notify->execute(BackupRun::query()->create(['status' => 'succeeded', 'started_at' => now(), 'finished_at' => now()])))->toBe(0);
    expect($notify->execute(BackupRun::query()->create(['status' => 'failed', 'started_at' => now(), 'finished_at' => now(), 'error' => 'disk may be full'])))->toBe(2);
    expect($notify->execute(BackupRun::query()->create(['status' => 'partial', 'started_at' => now(), 'finished_at' => now(), 'offsite_error' => 'bucket refused'])))->toBe(2);

    $notices = UserNotification::query()->whereIn('user_id', [$first->id, $second->id])->get();
    expect($notices)->toHaveCount(4)
        ->and($notices->pluck('title')->unique()->values()->all())->toBe([__('admin.backup_failed_title'), __('admin.backup_partial_title')])
        ->and($notices->first()->message)->toContain('disk may be full')
        ->and(UserNotification::query()->where('user_id', $bystander->id)->count())->toBe(0);
});

it('raises the alert from the nightly run itself when it fails', function () {
    $admin = bk2SuperAdmin();
    config(['backup.binaries.mysqldump' => '/nonexistent/mysqldump', 'backup.local_path' => storage_path('framework/testing/bk2-'.bin2hex(random_bytes(4)))]);
    app(RunBackupAction::class)->execute();
    expect(UserNotification::query()->where('user_id', $admin->id)->where('title', __('admin.backup_failed_title'))->exists())->toBeTrue();
});
