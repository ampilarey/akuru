<?php

use App\Domains\Identity\Models\User;
use App\Domains\Pronunciation\Models\AiModelVersion;
use App\Domains\Pronunciation\Models\AiModelVersionEvent;
use App\Domains\Pronunciation\Models\ArabicPronunciationAttempt;
use App\Support\Navigation\BuildNavigationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Four defects of the office's and the teacher's pronunciation screens, found
 * translating them (STATUS §5pr, fixed §5ps).
 */
uses(RefreshDatabase::class);

/** @return array{0: int, 1: int} the ids of baa and fatha */
function psSound(): array
{
    return [
        (int) DB::table('arabic_letters')->where('key_name', 'baa')->value('id'),
        (int) DB::table('arabic_harakas')->where('key_name', 'fatha')->value('id'),
    ];
}

it('lets the dean into the review queue, and the navigation offers the dean the door', function () {
    // The queue admitted a `dean`, a role nobody holds: the dean is
    // `headmaster`. The navigation, written to match, left the dean out.
    Queue::fake();
    [$baa, $fatha] = psSound();
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->post(route('learn.pronounce.store'), [
        'expected_letter_id' => $baa,
        'expected_haraka_id' => $fatha,
        'audio' => UploadedFile::fake()->create('attempt.webm', 40, 'audio/webm'),
    ]);
    $attempt = ArabicPronunciationAttempt::query()->sole();
    $dean = User::factory()->create();
    $dean->assignRole(Role::findOrCreate('headmaster', 'web'));

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('teach.pronunciation'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Pronunciation/Teach')->where('review_queue.0.id', $attempt->id));
    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->post(route('teach.pronunciation.review', $attempt->id), ['verified_letter_id' => $baa, 'verified_haraka_id' => $fatha])
        ->assertSessionHasNoErrors();
    expect($attempt->fresh()->status)->toBe('teacher_reviewed');

    $hrefs = collect(app(BuildNavigationAction::class)->execute($dean, 'en')['groups'])
        ->flatMap(fn (array $group) => $group['items'])
        ->pluck('href');
    expect($hrefs)->toContain('/teach/pronunciation');

    // A parent is still no teacher.
    $parent = User::factory()->create();
    $parent->assignRole(Role::findOrCreate('parent', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('teach.pronunciation'))->assertForbidden();
});

it('audits an activation as a rollback only when it goes back to an earlier version', function () {
    // The screen sent `rollback: 1` with every activation, and the action took
    // the caller's word, so switching on the first model ever was logged as
    // rolling back to it.
    $admin = actingSystemAdmin(['pronunciation.manage']);
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($admin);
    foreach (['v1', 'v2'] as $name) {
        $as()->post(route('admin.pronunciation.versions.store'), ['version_name' => $name, 'model_path' => "/models/{$name}.h5"])
            ->assertSessionHasNoErrors();
    }
    $v1 = AiModelVersion::query()->where('version_name', 'v1')->sole();
    $v2 = AiModelVersion::query()->where('version_name', 'v2')->sole();

    $as()->post(route('admin.pronunciation.versions.activate', $v1->id))->assertSessionHasNoErrors();
    $as()->post(route('admin.pronunciation.versions.activate', $v2->id))->assertSessionHasNoErrors();
    $as()->post(route('admin.pronunciation.versions.activate', $v1->id))->assertSessionHasNoErrors();
    // A tab still holding the old page sends the old flag; it is not believed.
    $as()->post(route('admin.pronunciation.versions.activate', $v2->id), ['rollback' => 1])->assertSessionHasNoErrors();

    expect(AiModelVersionEvent::query()->where('action', '!=', 'registered')->orderBy('id')->pluck('action')->all())
        ->toBe(['activated', 'activated', 'rolled_back', 'activated'])
        ->and($v2->fresh()->is_active)->toBeTrue()
        ->and(AiModelVersion::query()->where('is_active', true)->count())->toBe(1);
});

it('refuses a version name used twice, and says why, rather than answering 500', function () {
    $admin = actingSystemAdmin(['pronunciation.manage']);
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($admin);
    $as()->post(route('admin.pronunciation.versions.store'), ['version_name' => 'v1', 'model_path' => '/models/v1.h5'])
        ->assertSessionHasNoErrors();

    $as()->from(route('admin.pronunciation.index'))
        ->post(route('admin.pronunciation.versions.store'), ['version_name' => 'v1', 'model_path' => '/models/v1-again.h5'])
        ->assertRedirect(route('admin.pronunciation.index'))
        ->assertSessionHasErrors(['version_name' => 'A version named v1 is already registered.']);
    expect(AiModelVersion::query()->count())->toBe(1);
});

it('keeps the haraka accuracy the version form now asks for', function () {
    $admin = actingSystemAdmin(['pronunciation.manage']);
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($admin);
    $as()->post(route('admin.pronunciation.versions.store'), [
        'version_name' => 'v3',
        'model_path' => '/models/v3.h5',
        'validation_letter_accuracy' => 0.9,
        'validation_haraka_accuracy' => 0.8,
    ])->assertSessionHasNoErrors();

    $as()->get(route('admin.pronunciation.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('model_versions.0.letter_accuracy', 0.9)->where('model_versions.0.haraka_accuracy', 0.8));
    // The server always took it; the form had no field for it.
    expect(file_get_contents(resource_path('js/Pages/Pronunciation/Admin.jsx')))
        ->toContain("versionForm.setData('validation_haraka_accuracy'");
});
