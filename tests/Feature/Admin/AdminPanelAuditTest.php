<?php

use App\Domains\Courses\Models\Course;
use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;
use App\Domains\Website\Models\Page;
use App\Support\Navigation\BuildNavigationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The admin-panel audit (STATUS §5hs, docs/ADMIN_PANEL.md): what it fixed.
 * Four listings gained the CSV every listing is owed; the Inertia shell's
 * More menu gained the whole panel, its Blade screens as full page loads;
 * the prayer-times import caps its upload; the test-data command that
 * wipes every non-admin account, their payments included, is refused on
 * production.
 */
function auditAdmin(string $role = 'admin', array $permissions = []): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function auditAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('exports the instructors, the prayer recipient groups, the CMS pages and the CMS courses as the screens list them', function () {
    $office = auditAdmin('admin', ['prayer.manage']);
    $teacher = auditAdmin('teacher');

    Instructor::query()->create(['name' => 'Ustaadh Audit', 'email' => 'audit@example.test', 'specialization' => 'Tajweed', 'is_active' => true, 'sort_order' => 1]);
    PrayerRecipientGroup::query()->create(['name_en' => 'Audit group', 'member_refs' => ['7700001', '7700002'], 'is_active' => true, 'created_by' => $office->id]);
    Page::query()->create(['title' => 'Audit page', 'slug' => 'audit-page', 'body' => '<p>Hi</p>', 'is_published' => true, 'published_at' => now()]);
    Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId(['name' => 'Audit category', 'slug' => 'audit-'.Str::random(6), 'order' => 0, 'created_at' => now(), 'updated_at' => now()]),
        'title' => 'Audit course', 'slug' => 'audit-course', 'short_desc' => 'Short.', 'body' => 'Body.', 'cover_image' => '', 'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);

    foreach ([
        'admin.instructors.export' => ['id,name,email', 'Ustaadh Audit', 'Tajweed'],
        'admin.prayer-times.groups.export' => ['id,name,members', 'Audit group', ',2,'],
        'admin.pages.export' => ['id,title,slug', 'Audit page', 'audit-page'],
        'admin.courses.export' => ['id,title,slug,category', 'Audit course', 'Audit category'],
    ] as $route => $expected) {
        $body = auditAs($office)->get(route($route))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        foreach ($expected as $needle) {
            expect($body)->toContain($needle);
        }
        auditAs($teacher)->get(route($route))->assertForbidden();
    }

    // The link is on each screen.
    foreach (['admin.instructors.index', 'admin.prayer-times.groups.index', 'admin.pages.index', 'admin.courses.index'] as $screen) {
        auditAs($office)->get(route($screen))->assertOk()->assertSee('data-testid="export-csv"', false);
    }
});

it('lists the whole admin panel in the Inertia More menu, Blade screens marked for a full page load and gated by their routes', function () {
    $admin = auditAdmin('admin', ['commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage', 'bookshop.manage']);
    $nav = app(BuildNavigationAction::class)->execute($admin, 'en');
    $group = collect($nav['groups'])->firstWhere('key', 'admin_group');
    $items = collect($group['items'])->keyBy('href');

    expect($items->keys()->all())->toContain('/admin/enrollments', '/admin/instructors', '/admin/public-site/pages', '/admin/commerce', '/admin/library', '/admin/bookshop', '/admin/prayer-times/islands', '/admin/pronunciation', '/admin/translations', '/admin/operations')
        // super_admin only: hidden from an admin by the route's own gate.
        ->not->toContain('/admin/users', '/admin/settings');
    expect($items['/admin/enrollments']['hard'] ?? false)->toBeTrue()
        ->and($items['/admin/public-site/pages']['hard'] ?? false)->toBeTrue()
        ->and($items['/admin/commerce'])->not->toHaveKey('hard')
        ->and($items['/admin/enrollments']['label'])->toBe('Enrolments');

    // A super admin sees the two; a teacher sees none of the panel.
    $superNav = app(BuildNavigationAction::class)->execute(auditAdmin('super_admin'), 'en');
    $superItems = collect(collect($superNav['groups'])->firstWhere('key', 'admin_group')['items'] ?? [])->pluck('href');
    expect($superItems->all())->toContain('/admin/users', '/admin/settings', '/admin/enrollments');
    $teacherNav = app(BuildNavigationAction::class)->execute(auditAdmin('teacher'), 'en');
    expect(collect($teacherNav['groups'])->firstWhere('key', 'admin_group'))->toBeNull();

    // The shell receives the flag.
    auditAs($admin)->get(route('admin.operations.index'))->assertInertia(fn ($page) => $page->has('nav.groups'));
    app()->setLocale('dv');
    expect(collect(app(BuildNavigationAction::class)->execute($admin, 'dv')['groups'])->firstWhere('key', 'admin_group')['items'][1]['label'])->toBe('އެންރޯލްމަންޓް');
});

it('refuses a prayer-times database over 20 MB', function () {
    $office = auditAdmin('admin', ['prayer.manage']);
    auditAs($office)->post(route('admin.prayer-times.import.store'), ['salat_db' => UploadedFile::fake()->create('salat.db', 21000)])
        ->assertSessionHasErrors('salat_db');
});

it('refuses to clear non-admin users on production', function () {
    auditAdmin('admin');
    User::factory()->create();
    app()->detectEnvironment(fn () => 'production');
    test()->artisan('users:clear-non-admin', ['--force' => true])->expectsOutputToContain('refused on production')->assertFailed();
    expect(User::query()->count())->toBe(2);
    app()->detectEnvironment(fn () => 'testing');
    test()->artisan('users:clear-non-admin', ['--force' => true])->assertSuccessful();
    expect(User::query()->count())->toBe(1);
});
