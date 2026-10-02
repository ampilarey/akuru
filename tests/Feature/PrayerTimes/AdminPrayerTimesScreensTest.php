<?php

use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The prayer-times admin screens (docs/ADMIN_PANEL.md; C9 slice 12, STATUS
 * §5jn): Inertia pages with every string keyed EN/DV/AR — the islands hub,
 * the import, the recipient groups list and form, the broadcasts list with
 * its filters and the draft form with its preview snapshot and confirm.
 */
it('renders the islands hub and the import as props, and seeds the fixture from the import page', function () {
    $office = actingSystemAdmin(['prayer.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Islands')->where('islands', [])->where('cache_version', 1)
            ->where('t.prayer_islands_title', 'Prayer islands')->where('t.prayer_islands_none', 'No islands. Import salat.db or seed the synthetic fixture.'));

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.import'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Import')->where('cache_version', 1)->where('t.prayer_import_bundled', 'Import bundled dataset'));

    $this->withoutLocalizationMiddleware()->actingAs($office)->from(route('admin.prayer-times.import'))
        ->post(route('admin.prayer-times.import.store'), ['seed_fixture' => 1])
        ->assertRedirect(route('admin.prayer-times.import'))->assertSessionHas('success', 'Synthetic 366-day Malé fixture imported (not Bake&Grill).');

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Islands')
            // Ordered by atoll then name, so Hulhumalé comes before Malé.
            ->where('islands', fn ($islands) => collect($islands)->contains(fn ($island) => $island['id'] === 1 && $island['name_en'] === 'Malé' && $island['is_active'] === true)));

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['prayer_islands_title', 'prayer_import_title', 'prayer_groups_title', 'prayer_broadcasts_title', 'prayer_needs_split', 'prayer_flash_group_saved', 'prayer_flash_imported'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // Prayer times is the system admin's, and needs the permission (ADR-040 slice 2).
    $admin = \App\Domains\Identity\Models\User::factory()->create();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.prayer-times.islands'))->assertForbidden();
});

it('creates, lists and edits a recipient group as props with keyed flashes', function () {
    $office = actingSystemAdmin(['prayer.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.groups.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/GroupForm')->where('group', null)->where('t.prayer_group_new_title', 'New group'));

    $this->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.prayer-times.groups.store'), [
        'name_en' => 'Friday reminders', 'name_dv' => 'ހުކުރު', 'name_ar' => 'الجمعة', 'description' => 'Every Friday.', 'member_refs' => '4, 5', 'is_active' => true,
    ])->assertSessionHas('success', 'Group saved.');
    $group = PrayerRecipientGroup::query()->where('name_en', 'Friday reminders')->sole();
    // MySQL 8 hands JSON object keys back in its own order, so canonically.
    expect($group->member_refs)->toEqualCanonicalizing([['type' => 'user', 'id' => 4], ['type' => 'user', 'id' => 5]])->and($group->is_active)->toBeTrue();

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.groups.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Groups')
            ->where('groups.0.name', 'Friday reminders')->where('groups.0.members', 2)->where('groups.0.is_active', true)->where('t.prayer_col_members', 'Members'));

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.groups.edit', $group))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/GroupForm')->where('group.id', $group->id)->where('group.name_dv', 'ހުކުރު')
            ->where('group.member_refs', fn ($refs) => str_contains($refs, '"id": 4'))->where('group.is_active', true));

    $this->withoutLocalizationMiddleware()->actingAs($office)->from(route('admin.prayer-times.groups.edit', $group))
        ->put(route('admin.prayer-times.groups.update', $group), ['name_en' => 'Friday reminders (paused)', 'member_refs' => '4', 'is_active' => false])
        ->assertRedirect(route('admin.prayer-times.groups.edit', $group))->assertSessionHas('success', 'Group saved.');
    $group->refresh();
    expect($group->name_en)->toBe('Friday reminders (paused)')->and($group->is_active)->toBeFalse()->and($group->member_refs)->toHaveCount(1);
});

it('drafts, previews and refuses to confirm a broadcast with nobody consented, all as props', function () {
    seedPrayerTimesFixture();
    $office = actingSystemAdmin(['prayer.manage']);
    $plain = makePrayerContactUser('+9607771001');
    $group = PrayerRecipientGroup::query()->create(['name_en' => 'Office', 'created_by' => $office->id, 'is_active' => true]);

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.broadcasts.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/BroadcastForm')->where('broadcast', null)
            ->where('islands', fn ($islands) => collect($islands)->contains(fn ($island) => $island['id'] === 1 && $island['name'] === 'Malé'))->where('groups.0.name', 'Office')
            ->where('modes', ['daily', 'range', 'change_only'])->where('languages', ['en', 'dv', 'ar'])->where('t.prayer_save_draft', 'Save draft'));

    $this->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.prayer-times.broadcasts.store'), [
        'mode' => 'daily', 'island_id' => 1, 'date_from' => '2025-01-10', 'language' => 'dv', 'recipient_group_id' => '', 'recipient_refs' => (string) $plain->id,
    ])->assertSessionHas('success', 'Draft saved.');
    $broadcast = PrayerBroadcast::query()->latest('id')->first();
    expect($broadcast->status->value)->toBe('draft')->and($broadcast->recipient_refs)->toEqualCanonicalizing([['type' => 'user', 'id' => $plain->id]]);

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.broadcasts.edit', $broadcast))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/BroadcastForm')->where('broadcast.id', $broadcast->id)->where('broadcast.status', 'draft')
            ->where('broadcast.language', 'dv')->where('broadcast.date_from', '2025-01-10')->where('broadcast.snapshot', null)
            ->where('broadcast.recipient_refs', fn ($refs) => str_contains($refs, '"id":'.$plain->id)));

    // Preview: the plain user has not consented, so nobody is included; confirm is refused in the Action's words.
    $this->withoutLocalizationMiddleware()->actingAs($office)->from(route('admin.prayer-times.broadcasts.edit', $broadcast))
        ->post(route('admin.prayer-times.broadcasts.preview', $broadcast))
        ->assertRedirect(route('admin.prayer-times.broadcasts.edit', $broadcast))->assertSessionHas('success', 'Preview ready. Review the snapshot then confirm.');
    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.broadcasts.edit', $broadcast))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/BroadcastForm')->where('broadcast.status', 'previewed')
            ->where('broadcast.snapshot.included_count', 0)->where('broadcast.snapshot.excluded_count', 1)->where('broadcast.snapshot.needs_split', false)
            ->where('broadcast.snapshot.messages.dv', fn ($message) => is_string($message) && $message !== ''));
    $this->withoutLocalizationMiddleware()->actingAs($office)->from(route('admin.prayer-times.broadcasts.edit', $broadcast))
        ->post(route('admin.prayer-times.broadcasts.confirm', $broadcast))->assertSessionHasErrors('confirm');
    expect($broadcast->fresh()->status->value)->toBe('previewed');

    // The list, filtered.
    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.broadcasts.index', ['status' => 'previewed']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Broadcasts')->has('broadcasts', 1)
            ->where('broadcasts.0.id', $broadcast->id)->where('broadcasts.0.status', 'previewed')->where('broadcasts.0.island', 'Malé')
            ->where('filters.status', 'previewed')->where('t.prayer_all_statuses', 'All statuses'));
    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.broadcasts.index', ['status' => 'queued']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Broadcasts')->has('broadcasts', 0));
});

it('pages the islands twenty-five at a time and searches by island or atoll (ADMIN_PANEL.md §7 P5)', function () {
    $office = actingSystemAdmin(['prayer.manage']);
    // The fixture brings the category the islands hang off, and a few islands of its own.
    $this->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.prayer-times.import.store'), ['seed_fixture' => 1]);
    $base = PrayerIsland::query()->count();
    $categoryId = (int) PrayerIsland::query()->min('category_id');
    foreach (range(1, 30) as $n) {
        PrayerIsland::query()->create([
            'id' => 5000 + $n, 'category_id' => $categoryId,
            'atoll' => 'އަތޮޅު', 'atoll_latin' => $n <= 15 ? 'Qoph' : 'Waw',
            'name' => 'ރަށް '.$n, 'name_latin' => sprintf('Zz Island %02d', $n),
            'offset_minutes' => 0, 'latitude' => 4.1, 'longitude' => 73.5, 'is_active' => true,
        ]);
    }
    PrayerIsland::query()->create([
        'id' => 5999, 'category_id' => $categoryId, 'atoll' => 'ޒ', 'atoll_latin' => 'Zeta', 'name' => 'ޒެޑްވިލް', 'name_latin' => 'Zedville',
        'offset_minutes' => 2, 'latitude' => 4.2, 'longitude' => 73.6, 'is_active' => false,
    ]);
    $total = $base + 31;
    $pages = (int) ceil($total / 25);

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PrayerTimes/Islands')
            ->has('islands', 25)
            ->where('pagination.total', $total)->where('pagination.last_page', $pages)->where('pagination.current_page', 1)
            ->where('pagination.next', fn ($url) => str_contains((string) $url, 'page=2'))
            ->where('filters.q', ''));

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands', ['page' => $pages]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('islands', $total - 25 * ($pages - 1))->where('pagination.current_page', $pages)->where('pagination.next', null));

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands', ['q' => 'zed']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('islands', 1)->where('islands.0.name_en', 'Zedville')->where('filters.q', 'zed')->where('pagination.total', 1));

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands', ['q' => 'Waw']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('islands', 15)->where('pagination.total', 15));

    // The CSV still carries every island.
    $csv = $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.prayer-times.islands.export'))->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBeGreaterThanOrEqual($total);
});
