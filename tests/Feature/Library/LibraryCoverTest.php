<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §36 "cover image upload". The cover was a URL only the office
 * could type; now it is an uploaded file (public media) shown on the shelf,
 * the item page, the author page and the writer's own list.
 */
function coverFile(string $name = 'cover.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 300, 400);
}

it('lets the office upload a cover, which every public view shows', function () {
    Storage::fake('public');
    $admin = actingPeopleAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.store'), [
            'title' => 'Covered Primer',
            'content_type' => 'book',
            'access_type' => 'free_public',
            'body' => '<p>Text.</p>',
            'cover' => coverFile(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = LibraryItem::query()->where('title', 'Covered Primer')->firstOrFail();
    $media = MediaFile::query()->findOrFail($item->cover_media_file_id);
    // A cover is published on purpose; the PDF next to it is not.
    expect($media->visibility)->toBe('public')
        ->and($media->disk)->toBe('public')
        ->and($media->path)->toStartWith('library-covers/');

    app(PublishLibraryItemAction::class)->execute($item->id, $admin->id);
    $url = Storage::disk('public')->url($media->path);

    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()
        ->assertSee('data-cover="'.$item->slug.'"', false)
        ->assertSee($url, false);
    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee($url, false);
});

it('keeps a typed cover URL as the fallback, and shows nothing for a bare word', function () {
    $admin = User::factory()->create();
    $typed = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Typed Cover',
        'content_type' => 'article',
        'access_type' => 'free_public',
        'cover_image' => 'https://cdn.example.test/cover.jpg',
        'body' => '<p>Text.</p>',
    ]);
    $bare = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Bare Cover',
        'content_type' => 'article',
        'access_type' => 'free_public',
        'cover_image' => 'not a url',
        'body' => '<p>Text.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($typed->id, $admin->id);
    app(PublishLibraryItemAction::class)->execute($bare->id, $admin->id);

    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()
        ->assertSee('https://cdn.example.test/cover.jpg', false)
        ->assertDontSee('data-cover="'.$bare->slug.'"', false);
});

it('lets a writer upload a cover with a draft and see it in their list and on their author page', function () {
    Storage::fake('public');
    $writer = User::factory()->create();
    $application = WriterApplication::query()->create([
        'user_id' => $writer->id,
        'display_name' => 'Aminath Cover',
        'agreement_accepted_at' => now(),
    ]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), [
            'title' => 'Covered Draft',
            'content_type' => 'book',
            'access_type' => 'paid',
            'price' => 30,
            'body' => '<p>Text.</p>',
            'cover' => coverFile('draft.png'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = LibraryItem::query()->where('title', 'Covered Draft')->firstOrFail();
    expect($item->cover_media_file_id)->not->toBeNull();
    $url = Storage::disk('public')->url(MediaFile::query()->findOrFail($item->cover_media_file_id)->path);

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->get(route('write.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('dashboard.items.0.cover_url', $url));

    // Editing without a new file keeps the cover; a new file replaces it.
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->put(route('write.items.update', $item->id), [
            'title' => 'Covered Draft',
            'content_type' => 'book',
            'access_type' => 'paid',
            'body' => '<p>Text, edited.</p>',
        ])
        ->assertSessionHasNoErrors();
    expect($item->refresh()->cover_media_file_id)->toBe($item->cover_media_file_id);

    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
    $slug = $item->refresh()->writer->slug;
    $this->withoutLocalizationMiddleware()->get(route('public.library.author', $slug))
        ->assertOk()
        ->assertSee('data-cover="'.$item->slug.'"', false);
});

it('refuses a cover that is not an image', function () {
    Storage::fake('public');
    $admin = actingPeopleAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->from(route('admin.library.index'))
        ->post(route('admin.library.items.store'), [
            'title' => 'Bad Cover',
            'content_type' => 'book',
            'access_type' => 'free_public',
            'cover' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('cover');

    expect(LibraryItem::query()->where('title', 'Bad Cover')->exists())->toBeFalse();
});
