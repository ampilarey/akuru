<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * SPEC §45 "Policies and Permissions":
 *
 *   > Backend must enforce permissions.
 *
 * SPEC §44 "API Requirements":
 *
 *   > **Do not rely only on frontend button hiding.**
 *
 * `POST /announcements` was `auth`-only with no check in the controller body,
 * so **any signed-in account — a pupil, a parent — could post a school-wide
 * announcement**, `type: emergency` and `priority: urgent` included, at any
 * audience or class. The screen was admin-only. The route was not. That is
 * exactly the frontend-button-hiding §44 forbids.
 *
 * It is the same defect, in the same file, as the students/teachers block
 * twenty lines above it, which an earlier slice fixed with this comment: *"Any
 * signed-in account — a parent, a pupil — could therefore list, create, edit
 * and delete students and teachers through these."* That slice fixed three
 * route groups and walked past the fourth.
 *
 * Reading stays open to everyone signed in, which is what a noticeboard is for.
 */
uses(RefreshDatabase::class);

function announcementPayload(): array
{
    return [
        'title' => 'School closed tomorrow',
        'content' => 'Anyone could post this before the guard existed.',
        'type' => 'emergency',
        'priority' => 'urgent',
        'publish_date' => '2026-09-13',
    ];
}

it('refuses a school-wide announcement from a plain signed-in account', function () {
    $pupil = User::factory()->create();
    $before = DB::table('announcements')->count();

    $this->actingAs($pupil)
        ->withoutLocalizationMiddleware()
        ->post('/announcements', announcementPayload())
        ->assertForbidden();

    expect(DB::table('announcements')->count())->toBe($before);
});

it('refuses the compose form too, not only the submit', function () {
    // §44's point exactly: hiding the screen is not the control. Both halves
    // move behind the role, or the form remains a map to an open door.
    $this->actingAs(User::factory()->create())
        ->withoutLocalizationMiddleware()
        ->get('/announcements/create')
        ->assertForbidden();
});

it('still lets staff post one', function () {
    $admin = actingPeopleAdmin();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // `AnnouncementController@store` writes `School::first()?->id ?? 1`, so a
    // school row has to exist for the insert to clear its foreign key. On an
    // empty test database the unguarded route used to 500 on that constraint
    // rather than inserting — which is why the defect looked like a crash
    // instead of what it was.
    // There is no School factory or seeder, and the table has several NOT NULL
    // columns with no default, so the row is filled generically rather than by
    // guessing the list one failure at a time.
    $columns = \Illuminate\Support\Facades\Schema::getColumns('schools');
    $row = [];
    foreach ($columns as $column) {
        if ($column['name'] === 'id' || $column['auto_increment'] ?? false) {
            continue;
        }
        if ($column['nullable'] || $column['default'] !== null) {
            continue;
        }
        $row[$column['name']] = str_contains($column['type_name'], 'int') ? 1 : 'Akuru';
    }
    DB::table('schools')->insert($row + ['created_at' => now(), 'updated_at' => now()]);

    $before = DB::table('announcements')->count();

    $this->actingAs($admin->fresh())
        ->withoutLocalizationMiddleware()
        ->post('/announcements', announcementPayload())
        ->assertRedirect();

    expect(DB::table('announcements')->count())->toBe($before + 1);
});

it('keeps the noticeboard readable by everyone signed in', function () {
    // The guard is on writing. A noticeboard nobody may read is not a fix.
    // Since the admin became a React screen (2026-09-22), a signed-in account
    // without a staff role is sent to the family reader rather than shown
    // the compose form.
    $this->actingAs(User::factory()->create())
        ->withoutLocalizationMiddleware()
        ->get('/announcements')
        ->assertRedirect('/portal/announcements');
});
