<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * L8 — the public author page (LIBRARY_PLAN §8.7).
 *
 * A writer's work was findable only by scrolling the whole library; the
 * name on an item was plain text. These say: every approved writer has an
 * address, it lists their published work and nothing else, the item page
 * links to it, and the writer can put a face and a bio on it.
 */
function authorPageWriter(string $name = 'Ustadha Aminath'): array
{
    $user = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($user->id, [
        'display_name' => $name,
        'bio' => 'Teaches Arabic grammar in Malé.',
        'qualifications' => 'MA Arabic Linguistics',
        'expertise' => 'Nahw',
        'agreement_accepted' => true,
    ]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return [$user, WriterProfile::query()->where('user_id', $user->id)->firstOrFail()];
}

function authorPageItem(int $writerUserId, string $title, string $type = 'article', bool $publish = true): LibraryItem
{
    $item = app(SaveWriterItemAction::class)->execute($writerUserId, [
        'title' => $title,
        'content_type' => $type,
        'access_type' => 'free_public',
        'body' => '<p>'.$title.' body</p>',
        'abstract' => $title.' in one line.',
    ]);

    if ($publish) {
        $item = app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
    }

    return $item->fresh();
}

it('gives an approved writer an address made from their name', function () {
    [, $profile] = authorPageWriter('Ustadha Aminath');
    [, $twin] = authorPageWriter('Ustadha Aminath');

    expect($profile->slug)->toBe('ustadha-aminath')
        ->and($twin->slug)->toStartWith('ustadha-aminath-')
        ->and($twin->slug)->not->toBe($profile->slug);
});

it('lists only what the office has published, and links each item', function () {
    [$user, $profile] = authorPageWriter();
    $book = authorPageItem($user->id, 'Sun letters', 'book');
    $article = authorPageItem($user->id, 'Moon letters');
    $draft = authorPageItem($user->id, 'Unfinished thoughts', 'article', publish: false);

    // Somebody else's book must not appear on this author's shelf.
    [$other] = authorPageWriter('Somebody Else');
    authorPageItem($other->id, 'Not hers');

    $this->withoutLocalizationMiddleware()->get(route('public.library.author', $profile->slug))
        ->assertOk()
        ->assertSee('Ustadha Aminath')
        ->assertSee('Teaches Arabic grammar in Malé.')
        ->assertSee('MA Arabic Linguistics')
        ->assertSee('2 published works')
        ->assertSee('Sun letters')
        ->assertSee('Moon letters')
        ->assertSee(route('public.library.show', $book->slug), false)
        ->assertSee(route('public.library.show', $article->slug), false)
        ->assertDontSee('Unfinished thoughts')
        ->assertDontSee('Not hers');

    expect($draft->status->value)->toBe('draft');
});

it('is 404 for a name nobody has, and for a writer the office suspended', function () {
    [, $profile] = authorPageWriter();

    $this->withoutLocalizationMiddleware()->get(route('public.library.author', 'nobody-here'))->assertNotFound();

    $profile->forceFill(['status' => 'suspended'])->save();
    $this->withoutLocalizationMiddleware()->get(route('public.library.author', $profile->slug))->assertNotFound();
});

it('links the author from the item page and the library, and filters the library by author', function () {
    [$user, $profile] = authorPageWriter();
    $item = authorPageItem($user->id, 'Sun letters');

    // An office-uploaded item with a named author but no writer account: the
    // name stays plain text, because there is no page to send anyone to.
    $admin = User::factory()->create();
    $office = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Office handout',
        'content_type' => 'course_material',
        'access_type' => 'free_public',
        'body' => '<p>Handout</p>',
        'created_by' => $admin->id,
        'authors' => ['Guest Lecturer'],
    ]);
    app(PublishLibraryItemAction::class)->execute($office->id, $admin->id);

    $authorUrl = route('public.library.author', $profile->slug);

    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->assertOk()->assertSee($authorUrl, false);
    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $office->slug))->assertOk()->assertSee('Guest Lecturer')->assertDontSee('library/authors/');

    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['author' => $profile->slug]))
        ->assertOk()
        ->assertSee('Sun letters')
        ->assertDontSee('Office handout')
        ->assertSee('Showing works by one author.');

    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))->assertOk()->assertSee('Sun letters')->assertSee('Office handout');
});

it('lets the writer put a face and a bio on their page, and keeps the address across a rename', function () {
    Storage::fake('public');
    [$user, $profile] = authorPageWriter();
    $slugBefore = $profile->slug;

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), [
            'display_name' => 'Ustadha Aminath Ali',
            'bio' => 'Now teaching Tajweed too.',
            'qualifications' => 'MA Arabic Linguistics',
            'expertise' => 'Nahw, Tajweed',
            'photo' => UploadedFile::fake()->image('portrait.jpg', 400, 400),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $profile->refresh();

    expect($profile->display_name)->toBe('Ustadha Aminath Ali')
        ->and($profile->slug)->toBe($slugBefore)
        ->and($profile->photo_media_file_id)->not->toBeNull();

    $page = $this->withoutLocalizationMiddleware()->get(route('public.library.author', $profile->slug))->assertOk();
    $page->assertSee('Ustadha Aminath Ali')
        ->assertSee('Now teaching Tajweed too.')
        ->assertSee('<img', false)
        ->assertSee('writer-portraits/', false);
});

it('refuses a portrait that is not an image, and a profile edit from somebody who is not a writer', function () {
    [$user] = authorPageWriter();

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), [
            'display_name' => 'Ustadha Aminath',
            'photo' => UploadedFile::fake()->create('not-a-portrait.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('photo');

    $reader = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('write.profile'), ['display_name' => 'Impostor'])
        ->assertSessionHasErrors('writer');

    expect(WriterProfile::query()->where('display_name', 'Impostor')->exists())->toBeFalse();
});

/**
 * B6 (§8.7, STATUS §5im): featured works and links. A writer pins up to
 * three of their own published works at the top of the page and gives
 * readers a website and social addresses.
 */
it('pins the writer\'s featured works first and shows their links', function () {
    [$user, $profile] = authorPageWriter();
    $first = authorPageItem($user->id, 'Sun letters', 'book');
    $second = authorPageItem($user->id, 'Moon letters');
    authorPageItem($user->id, 'Shadda rules');

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), [
            'display_name' => 'Ustadha Aminath',
            'featured_item_ids' => [$second->id, $first->id],
            'social_links' => ['website' => 'https://aminath.example.mv', 'x' => 'https://x.com/aminath', 'facebook' => ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $profile->refresh();
    // The pins keep their order (a JSON array); the links are a JSON object,
    // whose key order MySQL 8 normalises on write (shorter keys first) while
    // MariaDB keeps insertion order — the page orders them itself, so the
    // comparison here is by content.
    expect($profile->featured_item_ids)->toBe([$second->id, $first->id])
        ->and($profile->social_links)->toEqualCanonicalizing(['website' => 'https://aminath.example.mv', 'x' => 'https://x.com/aminath']);

    $html = $this->withoutLocalizationMiddleware()->get(route('public.library.author', $profile->slug))->assertOk()
        ->assertSee('Featured works')
        ->assertSee('https://aminath.example.mv', false)
        ->assertSee('https://x.com/aminath', false)
        ->assertSee('rel="nofollow noopener"', false)
        ->getContent();

    // The pins come first, in the writer's order, and the full shelf still lists everything.
    expect(strpos($html, 'data-testid="featured-works"'))->toBeLessThan(strpos($html, 'Published works'))
        ->and(strpos($html, 'Moon letters'))->toBeLessThan(strpos($html, 'Sun letters'))
        // Titles as headings: an unpinned work once, a pinned one on both shelves.
        ->and(substr_count($html, '>Shadda rules<'))->toBe(1)
        ->and(substr_count($html, '>Sun letters<'))->toBe(2);

    // The writer's own editor reads the pins and the links back.
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('write.index'))
        ->assertInertia(fn ($page) => $page->where('dashboard.profile.featured_item_ids', [$second->id, $first->id])
            ->where('dashboard.profile.social_links.website', 'https://aminath.example.mv'));
});

it('refuses a pin that is not the writer\'s own published work, a fourth pin, and a bare address', function () {
    [$user, $profile] = authorPageWriter();
    $own = authorPageItem($user->id, 'Sun letters');
    $draft = authorPageItem($user->id, 'Unfinished thoughts', 'article', publish: false);
    [$other] = authorPageWriter('Somebody Else');
    $theirs = authorPageItem($other->id, 'Not hers');

    foreach ([[$draft->id], [$theirs->id], [$own->id, 999999]] as $ids) {
        $this->withoutLocalizationMiddleware()->actingAs($user)
            ->post(route('write.profile'), ['display_name' => 'Ustadha Aminath', 'featured_item_ids' => $ids])
            ->assertSessionHasErrors('featured_item_ids');
    }
    $more = [$own->id, authorPageItem($user->id, 'B')->id, authorPageItem($user->id, 'C')->id, authorPageItem($user->id, 'D')->id];
    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), ['display_name' => 'Ustadha Aminath', 'featured_item_ids' => $more])
        ->assertSessionHasErrors('featured_item_ids');

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), ['display_name' => 'Ustadha Aminath', 'social_links' => ['website' => 'aminath.example.mv']])
        ->assertSessionHasErrors('social_links.website');
    // An address the page does not know is dropped, not kept.
    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), ['display_name' => 'Ustadha Aminath', 'social_links' => ['myspace' => 'https://myspace.com/a']])
        ->assertSessionHasNoErrors();

    expect($profile->refresh()->featured_item_ids)->toBeEmpty()
        ->and($profile->social_links)->toBe([]);

    // A pin the office has since archived leaves the shelf without an error.
    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('write.profile'), ['display_name' => 'Ustadha Aminath', 'featured_item_ids' => [$own->id]])
        ->assertSessionHasNoErrors();
    $own->forceFill(['status' => 'archived'])->save();
    $this->withoutLocalizationMiddleware()->get(route('public.library.author', $profile->slug))
        ->assertOk()->assertDontSee('Featured works');
});
