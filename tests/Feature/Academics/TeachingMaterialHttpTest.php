<?php

use App\Domains\Academics\Actions\SaveTeachingMaterialAction;
use App\Domains\Academics\Models\TeachingMaterial;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * E13a walked over HTTP.
 *
 * A CI-green action nobody can reach is the lesson of the pilot rehearsals, so
 * these check the screen renders, the form posts, and the picker on the register
 * shows what is attached.
 */
function teachingStaff(): User
{
    $user = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $user->givePermissionTo('registers.fill');

    return $user;
}

it('renders the material library for a teacher', function () {
    $staff = teachingStaff();
    app(SaveTeachingMaterialAction::class)->execute(['title' => 'Alphabet worksheet'], (int) $staff->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.materials.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Materials/Index')
            ->has('materials', 1)
            ->where('materials.0.title', 'Alphabet worksheet')
            ->where('userId', (int) $staff->id)
        );
});

it('keeps the library away from an account with no register permission', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('academics.materials.index'))
        ->assertForbidden();
});

it('saves a material posted from the screen', function () {
    $staff = teachingStaff();

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->post(route('academics.materials.store'), [
            'title' => 'Number chant',
            'body' => 'Sing to the class.',
            'tags' => 'song, warmup',
        ])
        ->assertRedirect(route('academics.materials.index'));

    $material = TeachingMaterial::query()->sole();

    expect($material->title)->toBe('Number chant')
        ->and($material->tags)->toBe(['song', 'warmup'])
        ->and((int) $material->created_by)->toBe((int) $staff->id);
});

it('refuses an edit posted against a material somebody else wrote', function () {
    $material = app(SaveTeachingMaterialAction::class)
        ->execute(['title' => 'Mine'], (int) User::factory()->create()->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs(teachingStaff())
        ->put(route('academics.materials.update', $material), ['title' => 'Theirs'])
        ->assertSessionHasErrors('title');

    expect($material->refresh()->title)->toBe('Mine');
});

it('searches the library from the query string', function () {
    $staff = teachingStaff();
    $save = app(SaveTeachingMaterialAction::class);
    $save->execute(['title' => 'Alphabet worksheet'], (int) $staff->id);
    $save->execute(['title' => 'Number chant'], (int) $staff->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.materials.index', ['q' => 'chant']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('materials', 1)
            ->where('materials.0.title', 'Number chant')
            ->where('filters.q', 'chant')
        );
});

it('exports the filtered library as csv', function () {
    $staff = teachingStaff();
    $save = app(SaveTeachingMaterialAction::class);
    $save->execute(['title' => 'Alphabet worksheet', 'tags' => 'printable'], (int) $staff->id);
    $save->execute(['title' => 'Number chant'], (int) $staff->id);

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.materials.export', ['q' => 'alphabet']));

    $response->assertOk();
    $csv = $response->streamedContent();

    // What you were looking at, not everything.
    expect($csv)->toContain('Alphabet worksheet')
        ->and($csv)->toContain('printable')
        ->and($csv)->not->toContain('Number chant');
});

it('shows the picker and attaches what the register posts', function () {
    // Today's date: a back-dated register locks, and the submit is refused
    // before the materials are ever reached.
    $log = makeLessonLog(['date' => now()->toDateString()]);
    $staff = User::query()->findOrFail($log->teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $staff->givePermissionTo('registers.fill');

    $material = app(SaveTeachingMaterialAction::class)
        ->execute(['title' => 'Alphabet worksheet'], (int) $staff->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->put(route('academics.registers.update', $log), [
            'taught_summary' => 'Letters',
            'material_ids' => [(int) $material->id],
        ])
        // Without this a refused submit still redirects, and the assertion
        // below would be passing on an empty pivot for the wrong reason.
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($log->teachingMaterials()->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.registers.show', $log))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Registers/Show')
            ->where('attachedMaterials', [(int) $material->id])
            ->has('materialLibrary', 1)
        );
});

it('leaves attachments alone when the register posts no material field', function () {
    $log = makeLessonLog(['date' => now()->toDateString()]);
    $staff = User::query()->findOrFail($log->teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $staff->givePermissionTo('registers.fill');
    $material = app(SaveTeachingMaterialAction::class)->execute(['title' => 'Worksheet'], (int) $staff->id);
    app(\App\Domains\Academics\Actions\AttachMaterialsToLessonAction::class)
        ->execute($log, [(int) $material->id], (int) $staff->id);

    // §5t: `public/build` is committed, so a browser can still be running the
    // previous bundle, which posts no `material_ids` at all. That must not be
    // read as "the teacher unticked everything".
    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->put(route('academics.registers.update', $log), ['taught_summary' => 'Letters'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($log->teachingMaterials()->count())->toBe(1);
});
