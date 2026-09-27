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
use Inertia\Testing\AssertableInertia as Assert;
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
    // The website's screens are the system admin's (ADR-040 slice 2).
    $office = auditAdmin('super_admin', ['prayer.manage']);
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

    // The link is on each screen. The instructors list is Inertia since C9
    // slice 3, so its link is in the page's props (and `admin.mjs` sees the
    // rendered `export-csv` test id); the three Blade screens carry it in HTML.
    auditAs($office)->get(route('admin.instructors.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Instructors/Index')->where('t.instructors_export', 'Export CSV'));
    auditAs($office)->get(route('admin.pages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Pages')->where('t.pages_export', 'Export CSV'));
    foreach (['admin.prayer-times.groups.index', 'admin.courses.index'] as $screen) {
        auditAs($office)->get(route($screen))->assertOk()->assertSee('data-testid="export-csv"', false);
    }
});

it('offers the admin panel in the Inertia More menu by workspace, Blade screens marked for a full page load and gated by their routes', function () {
    // The Institute's three parts to the system admin; Admissions to the
    // school office; nothing of the panel to a teacher (STATUS §5id).
    $super = auditAdmin('super_admin', ['commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage', 'bookshop.manage']);
    $nav = app(BuildNavigationAction::class)->execute($super, 'en');
    expect(array_column($nav['groups'], 'key'))->toBe(['panel_website', 'panel_money', 'panel_system', 'mine']);
    $items = collect($nav['groups'])->flatMap(fn ($group) => $group['items'])->keyBy('href');
    expect($items->keys()->all())->toContain('/admin/instructors', '/admin/public-site/pages', '/admin/commerce', '/admin/library', '/admin/bookshop', '/admin/prayer-times/islands', '/admin/pronunciation', '/admin/translations', '/admin/operations', '/admin/users', '/admin/settings')
        ->not->toContain('/admin/enrollments');
    // C9 slice 10: the pages CMS is Inertia; prayer times is still Blade.
    expect($items['/admin/prayer-times/islands']['hard'] ?? false)->toBeTrue()
        ->and($items['/admin/public-site/pages'])->not->toHaveKey('hard')
        // C9 slice 2: Manage users is an Inertia page now, like Commerce.
        ->and($items['/admin/users'])->not->toHaveKey('hard')
        ->and($items['/admin/commerce'])->not->toHaveKey('hard');

    // The educational admin: Admissions, and none of the Institute.
    $admin = auditAdmin('admin', ['operations.manage']);
    $adminNav = app(BuildNavigationAction::class)->execute($admin, 'en');
    $adminItems = collect($adminNav['groups'])->flatMap(fn ($group) => $group['items'])->keyBy('href');
    expect(array_column($adminNav['groups'], 'key'))->toContain('panel_admissions')->not->toContain('panel_website', 'panel_money', 'panel_system')
        ->and($adminItems->keys()->all())->toContain('/admin/enrollments')->not->toContain('/admin/commerce', '/admin/users', '/admin/public-site/pages')
        // C9 slice 4: the enrolment lists are Inertia pages now.
        ->and($adminItems['/admin/enrollments'])->not->toHaveKey('hard')
        ->and($adminItems['/admin/enrollments']['label'])->toBe('Enrolments');

    // A teacher sees none of the panel.
    $teacherNav = app(BuildNavigationAction::class)->execute(auditAdmin('teacher'), 'en');
    expect(collect($teacherNav['groups'])->pluck('key')->filter(fn ($key) => str_starts_with($key, 'panel_'))->all())->toBe([]);

    // The shell receives it, labelled in the request language.
    auditAs($super)->get(route('admin.operations.index'))->assertInertia(fn ($page) => $page->has('nav.groups')->where('nav.workspace', 'institute'));
    app()->setLocale('dv');
    expect(collect(app(BuildNavigationAction::class)->execute($admin, 'dv')['groups'])->firstWhere('key', 'panel_admissions')['items'][0]['label'])->toBe('އެންރޯލްމަންޓް');
    app()->setLocale('en');
});

it('refuses a prayer-times database over 20 MB', function () {
    $office = auditAdmin('super_admin', ['prayer.manage']);
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
