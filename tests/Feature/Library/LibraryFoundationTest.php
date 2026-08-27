<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryCategory;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('walks admin create -> publish -> public listing, search and free reading', function () {
    $admin = actingPeopleAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.library.categories.store'), ['name' => 'Arabic Studies'])
        ->assertRedirect();
    $category = LibraryCategory::query()->firstOrFail();
    expect($category->slug)->toBe('arabic-studies');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.library.items.store'), [
            'title' => 'Nahw Primer',
            'content_type' => 'article',
            'access_type' => 'free_public',
            'library_category_id' => $category->id,
            'abstract' => 'A first look at Arabic grammar.',
            'body' => '<p>Bismillah — the nahw journey begins.</p>',
            'tags' => ['grammar', 'nahw'],
            'authors' => [['name' => 'Ustadh Ahmed']],
            'reading_time' => 7,
        ])
        ->assertRedirect();

    $item = LibraryItem::query()->firstOrFail();
    expect($item->status->value)->toBe('draft')
        ->and($item->tags()->count())->toBe(2)
        ->and($item->authors()->count())->toBe(1);

    // Drafts are invisible publicly (business rule §43.3 — admin must approve).
    $this->get(route('public.library.index'))->assertOk()->assertDontSee('Nahw Primer');
    $this->get(route('public.library.show', $item->slug))->assertNotFound();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.library.items.publish', $item->id), ['publish' => true])
        ->assertRedirect();
    $item->refresh();
    expect($item->status->value)->toBe('published')
        ->and((int) $item->approved_by)->toBe($admin->id)
        ->and($item->published_at)->not->toBeNull();

    $this->get(route('public.library.index'))->assertOk()->assertSee('Nahw Primer')->assertSee('Ustadh Ahmed');
    $this->get(route('public.library.index', ['q' => 'grammar']))->assertOk()->assertSee('Nahw Primer');
    $this->get(route('public.library.index', ['q' => 'astronomy']))->assertOk()->assertDontSee('Nahw Primer');
    $this->get(route('public.library.index', ['category' => 'arabic-studies']))->assertOk()->assertSee('Nahw Primer');

    // Free-public body reads without login.
    $this->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee('the nahw journey begins', false);

    $this->get(route('public.library.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('free_login lists publicly but gates the body behind sign-in', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Members Reading',
        'content_type' => 'article',
        'access_type' => 'free_login',
        'body' => '<p>Secret free content for members.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    $this->get(route('public.library.index'))->assertOk()->assertSee('Members Reading');

    $this->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertDontSee('Secret free content')
        ->assertSee('Sign in');

    $this->actingAs(User::factory()->create())
        ->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee('Secret free content for members', false);
});

it('gates admin routes and stores the PDF original privately', function () {
    Queue::fake();
    $stranger = User::factory()->create();
    $this->withoutLocalizationMiddleware()
        ->actingAs($stranger)
        ->get(route('admin.library.index'))
        ->assertForbidden();

    $admin = actingPeopleAdmin(['library.manage']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.library.index'))
        ->assertOk();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.library.items.store'), [
            'title' => 'Tajweed Handbook',
            'content_type' => 'book',
            'pdf' => UploadedFile::fake()->create('tajweed.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    $item = LibraryItem::query()->where('title', 'Tajweed Handbook')->firstOrFail();
    $media = MediaFile::query()->findOrFail($item->pdf_media_file_id);
    // Business rule §43.6: the original is never public.
    expect($media->visibility)->toBe('private')
        ->and($media->disk)->toBe('local');
});
