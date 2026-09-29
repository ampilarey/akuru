<?php

use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ImportWebsiteResearchAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Enums\LibraryDelivery;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Website\Actions\BuildPublicSitemapAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * RESEARCH_ARTICLES_PLAN R2 (STATUS §5kp): the website's research papers move
 * into the Digital Library, the old addresses redirect there, and the website
 * research CMS is retired. The `posts` rows stay (rule 9).
 */
function r2Post(array $overrides = []): Post
{
    return Post::query()->create($overrides + [
        'type' => PostType::Research->value,
        'title' => 'Tafsir in Dhivehi',
        'slug' => 'tafsir-in-dhivehi',
        'summary' => 'How Dhivehi tafsir developed.',
        'abstract' => 'How Dhivehi tafsir developed.',
        'body' => '<p onclick="x()">The paper.</p>',
        'citation_note' => 'Akuru Working Paper 2025',
        'authors' => [],
        'is_published' => true,
        'published_at' => '2025-03-01 09:00:00',
        'author_id' => User::factory()->create()->id,
    ]);
}

function r2PublicPdf(): int
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, (string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'));

    return app(StorePublicMediaAction::class)->execute(
        new UploadedFile($path, 'paper.pdf', 'application/pdf', null, true),
        null,
        ['application/pdf'],
        ['alt' => 'paper.pdf'],
        'research-pdfs',
    )['id'];
}

it('imports a research post as a published library item, with its teacher, its outsider and its PDF as a private original', function () {
    Storage::fake('public');
    Storage::fake('local');
    $teacher = Instructor::query()->create(['name' => 'Ustaadha Shifa', 'slug' => 'shifa', 'is_active' => true, 'sort_order' => 1]);
    $pdfId = r2PublicPdf();
    $post = r2Post(['authors' => [['instructor_id' => $teacher->id], ['name' => 'Guest Scholar']], 'pdf_document_id' => $pdfId]);

    $rows = app(ImportWebsiteResearchAction::class)->execute();

    $item = LibraryItem::query()->where('imported_post_id', $post->id)->sole();
    expect($rows[0]['outcome'])->toBe('imported')
        ->and($item->slug)->toBe('tafsir-in-dhivehi')
        ->and($item->content_type->value)->toBe('research')
        ->and($item->access_type->value)->toBe('free_public')
        ->and($item->delivery)->toBe(LibraryDelivery::Both)
        ->and($item->status)->toBe(LibraryItemStatus::Published)
        ->and($item->published_at->toDateString())->toBe('2025-03-01')
        ->and($item->citations)->toBe('Akuru Working Paper 2025')
        ->and((string) $item->body)->not->toContain('onclick')
        ->and($item->authors->pluck('name')->all())->toBe(['Ustaadha Shifa', 'Guest Scholar'])
        ->and((int) $item->authors[0]->instructor_profile_id)->toBe($teacher->id);

    // The PDF is a new, private copy; the website's public file stays.
    $copy = MediaFile::query()->findOrFail($item->pdf_media_file_id);
    expect($copy->visibility)->toBe('private')
        ->and((int) $copy->id)->not->toBe($pdfId)
        ->and(MediaFile::query()->findOrFail($pdfId)->visibility)->toBe('public')
        // The post had a body as well, and a body is what the reader shows
        // when both exist (LIBRARY_PLAN §36); the PDF is the download.
        ->and($item->pages()->count())->toBe(1);

    // The imported paper reads, downloads, names its teacher — and is not
    // marked peer-reviewed, because no reviewer accepted it.
    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee('data-testid="library-download-link"', false)
        ->assertSee('href="'.route('public.instructors.show', 'shifa').'"', false);
    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['content_type' => 'research', 'peer_reviewed' => 1]))
        ->assertDontSee('Tafsir in Dhivehi');
});

it('imports nothing twice, keeps an unpublished post a draft, and writes nothing on a dry run', function () {
    Storage::fake('local');
    r2Post();
    $draft = r2Post(['title' => 'Unfinished', 'slug' => 'unfinished', 'is_published' => false]);

    $dry = app(ImportWebsiteResearchAction::class)->execute(true);
    expect(array_column($dry, 'outcome'))->toBe(['would import', 'would import'])
        ->and(LibraryItem::query()->count())->toBe(0);

    app(ImportWebsiteResearchAction::class)->execute();
    $again = app(ImportWebsiteResearchAction::class)->execute();

    expect(array_column($again, 'outcome'))->toBe(['already imported', 'already imported'])
        ->and(LibraryItem::query()->count())->toBe(2)
        ->and(LibraryItem::query()->where('imported_post_id', $draft->id)->sole()->status)->toBe(LibraryItemStatus::Draft);
});

it('keeps the library\'s own item when a slug clashes, and still redirects the old address to the paper', function () {
    Storage::fake('local');
    app(SaveLibraryItemAction::class)->execute(['title' => 'Tafsir in Dhivehi', 'slug' => 'tafsir-in-dhivehi', 'content_type' => 'book', 'access_type' => 'free_public']);
    r2Post();

    $rows = app(ImportWebsiteResearchAction::class)->execute();

    expect($rows[0]['library_slug'])->toBe('tafsir-in-dhivehi-paper')
        ->and($rows[0]['note'])->toContain('slug taken');
    $this->withoutLocalizationMiddleware()->get('/research/tafsir-in-dhivehi')
        ->assertStatus(301)
        ->assertRedirect(route('public.library.show', 'tafsir-in-dhivehi-paper'));
});

it('runs from the command line, with a table as its evidence', function () {
    Storage::fake('local');
    r2Post();

    $this->artisan('library:import-website-research', ['--dry-run' => true])
        ->expectsOutputToContain('would import')
        ->assertSuccessful();
    expect(LibraryItem::query()->count())->toBe(0);

    $this->artisan('library:import-website-research', ['--force' => true])
        ->expectsOutputToContain('1 imported')
        ->assertSuccessful();
    expect(LibraryItem::query()->count())->toBe(1);
});

it('sends every old research and articles address to the library', function () {
    $get = fn (string $path) => $this->withoutLocalizationMiddleware()->get($path);

    $get('/research')->assertStatus(301)->assertRedirect(route('public.library.index', ['content_type' => 'research']));
    $get('/research?year=2025')->assertRedirect(route('public.library.index', ['content_type' => 'research', 'year' => '2025']));
    $get('/research/export')->assertStatus(301)->assertRedirect(route('public.library.export', ['content_type' => 'research']));
    $get('/research/never-was')->assertNotFound();
    $get('/articles')->assertStatus(301)->assertRedirect(route('public.library.index', ['content_type' => 'article']));
    $get('/articles/anything')->assertStatus(410);

    // A draft that was imported has no page to go to yet.
    Storage::fake('local');
    r2Post(['slug' => 'not-yet', 'is_published' => false]);
    app(ImportWebsiteResearchAction::class)->execute();
    $get('/research/not-yet')->assertNotFound();

    // The route names stay, so nothing that builds a URL breaks.
    expect(route('public.research.index'))->toEndWith('/research')
        ->and(route('public.articles.index'))->toEndWith('/articles');
});

it('retires the website research CMS', function () {
    foreach (['admin.research.index', 'admin.research.create', 'admin.research.store', 'admin.research.edit', 'admin.research.update', 'admin.research.export'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
    expect(file_exists(app_path('Domains/Website/Actions/SaveResearchPostAction.php')))->toBeFalse()
        ->and(file_exists(resource_path('js/Pages/Website/ResearchForm.jsx')))->toBeFalse();
});

it('lists a teacher\'s library works on their profile, and library items in the sitemap instead of posts', function () {
    Storage::fake('local');
    $teacher = Instructor::query()->create(['name' => 'Ustaadh Hassan', 'slug' => 'hassan', 'is_active' => true, 'sort_order' => 1]);
    r2Post(['authors' => [['instructor_id' => $teacher->id]]]);
    app(ImportWebsiteResearchAction::class)->execute();

    $this->withoutLocalizationMiddleware()->get(route('public.instructors.show', 'hassan'))
        ->assertOk()
        ->assertSee('href="'.route('public.library.show', 'tafsir-in-dhivehi').'"', false);

    $xml = app(BuildPublicSitemapAction::class)->execute();
    expect($xml)->toContain('/library/tafsir-in-dhivehi</loc>')
        ->and($xml)->not->toContain('/research/tafsir-in-dhivehi</loc>')
        ->and($xml)->not->toContain('/articles</loc>');
});
