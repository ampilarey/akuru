<?php

use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The instructors screens (docs/ADMIN_PANEL.md; C9 slice 3, STATUS §5je):
 * Inertia pages with every string keyed EN/DV/AR, the same create, edit
 * (with a portrait) and delete the Blade screens did, and the CSV.
 */
function instructorsAdmin(): User
{
    $user = User::factory()->create(['name' => 'The System Admin']);
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    return $user->fresh();
}

function instructorsAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('lists the instructors as the website orders them, with the portrait, the course count and the keyed strings', function () {
    Storage::fake('public');
    $super = instructorsAdmin();
    Instructor::query()->create(['name' => 'Ustaadh Zed', 'sort_order' => 2, 'is_active' => false, 'qualification' => 'MA']);
    $first = Instructor::query()->create(['name' => 'Ustaadh Alif', 'sort_order' => 1, 'is_active' => true, 'specialization' => 'Tajweed', 'photo' => 'instructors/alif.jpg']);

    instructorsAs($super)->get(route('admin.instructors.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Instructors/Index')
            ->where('total', 2)
            ->where('instructors.0.id', $first->id)
            ->where('instructors.0.specialization', 'Tajweed')
            ->where('instructors.0.courses_count', 0)
            ->where('instructors.0.is_active', true)
            ->where('instructors.0.photo_url', Storage::disk('public')->url('instructors/alif.jpg'))
            ->where('instructors.1.name', 'Ustaadh Zed')
            ->where('instructors.1.is_active', false)
            ->where('instructors.1.photo_url', null)
            ->where('pagination.last_page', 1)
            ->where('t.instructors_title', 'Instructors')
            ->where('t.instructors_export', 'Export CSV'));

    // Dhivehi and Arabic carry every key the page reads.
    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['instructors_title', 'instructors_add', 'instructors_delete_confirm', 'instructors_field_active', 'instructors_created'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // Not the educational admin's screen (ADR-040 slice 2).
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    instructorsAs($admin)->get(route('admin.instructors.index'))->assertForbidden();
});

it('creates an instructor with a portrait from the form, edits them keeping the slug, and deletes them', function () {
    Storage::fake('public');
    $super = instructorsAdmin();

    instructorsAs($super)->get(route('admin.instructors.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Instructors/Form')->where('instructor', null)->where('t.instructors_new_title', 'Add instructor'));

    instructorsAs($super)->post(route('admin.instructors.store'), [
        'name' => 'Ustaadh New', 'qualification' => 'BA', 'specialization' => 'Fiqh', 'email' => 'new@example.test', 'phone' => '7700000',
        'bio' => 'Teaches fiqh.', 'sort_order' => 3, 'is_active' => '1', 'photo' => UploadedFile::fake()->image('new.jpg', 300, 300),
    ])->assertRedirect(route('admin.instructors.index'))->assertSessionHas('success', 'Instructor created.');

    $row = Instructor::query()->where('name', 'Ustaadh New')->firstOrFail();
    expect($row->slug)->toBe('ustaadh-new')->and($row->is_active)->toBeTrue()->and($row->sort_order)->toBe(3)->and($row->photo)->toStartWith('instructors/');
    Storage::disk('public')->assertExists($row->photo);

    // The form reads the row back, portrait included.
    instructorsAs($super)->get(route('admin.instructors.edit', $row))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Instructors/Form')->where('instructor.id', $row->id)->where('instructor.name', 'Ustaadh New')->where('instructor.photo_url', Storage::disk('public')->url($row->photo)));

    // A rename keeps the slug the website links to; the portrait stays when no new one is sent; is_active off is honoured.
    instructorsAs($super)->put(route('admin.instructors.update', $row), ['name' => 'Ustaadh Renamed', 'sort_order' => 0, 'is_active' => '0'])
        ->assertRedirect(route('admin.instructors.index'))->assertSessionHas('success', 'Instructor updated.');
    $row->refresh();
    expect($row->name)->toBe('Ustaadh Renamed')->and($row->slug)->toBe('ustaadh-new')->and($row->is_active)->toBeFalse()->and($row->photo)->toStartWith('instructors/');

    // A file that is not an image is refused at the door.
    instructorsAs($super)->from(route('admin.instructors.edit', $row))->put(route('admin.instructors.update', $row), ['name' => 'X', 'photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');

    instructorsAs($super)->delete(route('admin.instructors.destroy', $row))->assertRedirect(route('admin.instructors.index'))->assertSessionHas('success', 'Instructor deleted.');
    expect(Instructor::query()->whereKey($row->id)->exists())->toBeFalse();
});
