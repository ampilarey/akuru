<?php

use App\Domains\Courses\Components\Quran\Models\QuranAyah;
use App\Domains\Courses\Components\Quran\Models\QuranMushaf;
use App\Domains\Courses\Components\Quran\Models\QuranWord;
use App\Domains\Courses\Components\Quran\Models\QuranWordPosition;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * A mushaf's pages and page images, and a lock that holds (STATUS §5pt; C19,
 * found with CT5b).
 *
 * - A mushaf uploaded without a page count could never get pages: the upload
 *   form made them, and nothing else could.
 * - Nothing set a page's image, so every page said it had none.
 * - `lock` was the step after approval, and nothing read it.
 * - A word's box took any page id and any word id, so it could join one
 *   mushaf's word to another mushaf's page.
 */
uses(RefreshDatabase::class);

/** The Hifz dean, who alone manages mushafs (`QuranMushafPolicy`). */
function ptDean(): User
{
    $dean = actingPeopleAdmin(['view_hifz_programs', 'manage_quran_mushaf']);
    $dean->assignRole(Role::findOrCreate('headmaster', 'web'));

    return $dean;
}

it('gives a mushaf uploaded without a page count its pages afterwards, and never takes one away', function () {
    $dean = ptDean();
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($dean);
    $as()->post(route('quran.mushafs.store'), ['name' => 'No count'])->assertSessionHasNoErrors();
    $mushaf = QuranMushaf::query()->where('name', 'No count')->sole();
    expect($mushaf->pages()->count())->toBe(0);

    $as()->post(route('quran.mushafs.pages.store', $mushaf), ['page_count' => 3])
        ->assertSessionHas('success', 'Pages added: the mushaf now has 3.');
    expect($mushaf->pages()->orderBy('page_number')->pluck('page_number')->all())->toBe([1, 2, 3])
        ->and($mushaf->fresh()->page_count)->toBe(3);
    $as()->get(route('quran.pages.show', ['mushaf' => $mushaf->id, 'pageNumber' => 3]))->assertOk();

    $as()->post(route('quran.mushafs.pages.store', $mushaf), ['page_count' => 2])
        ->assertSessionHasErrors(['page_count' => 'This mushaf already has 3 pages, and pages are never taken away.']);
    $as()->post(route('quran.mushafs.pages.store', $mushaf), ['page_count' => 5])->assertSessionHasNoErrors();
    expect($mushaf->pages()->count())->toBe(5);

    // A teacher is no dean.
    $teacher = User::factory()->create();
    $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('quran.mushafs.pages.store', $mushaf), ['page_count' => 9])->assertForbidden();
    expect($mushaf->pages()->count())->toBe(5);
});

it('keeps a page image the page view shows, and lets the old file go when it is replaced', function () {
    Storage::fake('public');
    $dean = ptDean();
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($dean);
    $as()->post(route('quran.mushafs.store'), ['name' => 'With images', 'page_count' => 2]);
    $mushaf = QuranMushaf::query()->where('name', 'With images')->sole();
    $page = $mushaf->pages()->where('page_number', 1)->sole();

    $as()->post(route('quran.pages.image.store', ['mushaf' => $mushaf->id, 'page' => $page->id]), [
        'page_image' => UploadedFile::fake()->image('page-1.png', 600, 900),
    ])->assertSessionHas('success', 'Page image saved.');
    $first = $page->fresh();
    Storage::disk('public')->assertExists($first->image_path);
    expect($first->width)->toBe(600)->and($first->height)->toBe(900);
    $as()->get(route('quran.pages.show', ['mushaf' => $mushaf->id, 'pageNumber' => 1]))
        ->assertInertia(fn (Assert $view) => $view->where('page.image_url', asset('storage/'.$first->image_path)));

    $as()->post(route('quran.pages.image.store', ['mushaf' => $mushaf->id, 'page' => $page->id]), [
        'page_image' => UploadedFile::fake()->image('page-1-better.jpg', 700, 1000),
    ])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($first->image_path);
    Storage::disk('public')->assertExists($page->fresh()->image_path);

    // Not an image: refused, with the field named.
    $as()->post(route('quran.pages.image.store', ['mushaf' => $mushaf->id, 'page' => $page->id]), [
        'page_image' => UploadedFile::fake()->create('page.pdf', 20, 'application/pdf'),
    ])->assertSessionHasErrors('page_image');
});

it('refuses every change to a locked mushaf, and changes nothing', function () {
    Storage::fake('public');
    $dean = ptDean();
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($dean);
    $as()->post(route('quran.mushafs.store'), ['name' => 'Final', 'page_count' => 1]);
    $mushaf = QuranMushaf::query()->where('name', 'Final')->sole();
    $page = $mushaf->pages()->sole();
    $as()->post(route('quran.mushafs.import-ayah', $mushaf), ['surah_number' => 1, 'ayah_number' => 1, 'text_uthmani' => 'بِسْمِ', 'page_number' => 1, 'words' => ['بِسْمِ']])
        ->assertSessionHasNoErrors();
    $word = QuranWord::query()->where('quran_mushaf_id', $mushaf->id)->sole();
    $mushaf->update(['locked' => true]);
    $locked = 'This mushaf is locked, so it can no longer be changed.';

    $as()->post(route('quran.mushafs.import-ayah', $mushaf), ['surah_number' => 1, 'ayah_number' => 2, 'text_uthmani' => 'ٱلْحَمْدُ'])
        ->assertSessionHasErrors(['mushaf' => $locked]);
    $as()->post(route('quran.mushafs.pages.store', $mushaf), ['page_count' => 4])
        ->assertSessionHasErrors(['mushaf' => $locked]);
    $as()->post(route('quran.pages.image.store', ['mushaf' => $mushaf->id, 'page' => $page->id]), ['page_image' => UploadedFile::fake()->image('p.png', 10, 10)])
        ->assertSessionHasErrors(['mushaf' => $locked]);
    $as()->post(route('quran.pages.positions.store', ['mushaf' => $mushaf->id, 'page' => $page->id]), ['quran_word_id' => $word->id, 'x' => 1, 'y' => 1, 'width' => 5, 'height' => 3])
        ->assertSessionHasErrors(['mushaf' => $locked]);

    expect(QuranAyah::query()->where('quran_mushaf_id', $mushaf->id)->count())->toBe(1)
        ->and($mushaf->pages()->count())->toBe(1)
        ->and($page->fresh()->image_path)->toBeNull()
        ->and(QuranWordPosition::query()->count())->toBe(0);
    $as()->get(route('quran.mushafs.show', $mushaf))
        ->assertInertia(fn (Assert $view) => $view->where('mushaf.locked', true));
});

it('keeps a word’s box to its own mushaf’s page and word', function () {
    $dean = ptDean();
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($dean);
    foreach (['One', 'Two'] as $name) {
        $as()->post(route('quran.mushafs.store'), ['name' => $name, 'page_count' => 1]);
    }
    [$one, $two] = [QuranMushaf::query()->where('name', 'One')->sole(), QuranMushaf::query()->where('name', 'Two')->sole()];
    $as()->post(route('quran.mushafs.import-ayah', $two), ['surah_number' => 1, 'ayah_number' => 1, 'text_uthmani' => 'بِسْمِ', 'page_number' => 1, 'words' => ['بِسْمِ']]);
    $twosWord = QuranWord::query()->where('quran_mushaf_id', $two->id)->sole();
    $box = ['x' => 1, 'y' => 1, 'width' => 5, 'height' => 3];

    // Two's page under One's address.
    $as()->post(route('quran.pages.positions.store', ['mushaf' => $one->id, 'page' => $two->pages()->sole()->id]), ['quran_word_id' => $twosWord->id] + $box)
        ->assertNotFound();
    // Two's word on One's page.
    $as()->post(route('quran.pages.positions.store', ['mushaf' => $one->id, 'page' => $one->pages()->sole()->id]), ['quran_word_id' => $twosWord->id] + $box)
        ->assertSessionHasErrors('quran_word_id');
    expect(QuranWordPosition::query()->count())->toBe(0);

    // Its own word on its own page is kept.
    $as()->post(route('quran.pages.positions.store', ['mushaf' => $two->id, 'page' => $two->pages()->sole()->id]), ['quran_word_id' => $twosWord->id] + $box)
        ->assertSessionHas('success', 'Word position saved.');
    expect(QuranWordPosition::query()->sole()->quran_mushaf_id)->toBe($two->id);
});
