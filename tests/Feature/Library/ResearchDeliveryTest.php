<?php

use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\ListLibraryInsightsAction;
use App\Domains\Library\Actions\PresentLibraryItemAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Enums\LibraryDelivery;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * RESEARCH_ARTICLES_PLAN R1 (STATUS §5ko): the Digital Library can hold the
 * institute's research — an author may be one of Akuru's teachers, linked to
 * their profile; the author chooses whether readers read online, download the
 * PDF, or both (the owner's decision D1); and the research shelf filters by
 * year.
 */
function r1Pdf(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, (string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'));

    return new UploadedFile($path, 'paper.pdf', 'application/pdf', null, true);
}

function r1Item(array $data = [], bool $withPdf = true, ?string $publishedAt = null): LibraryItem
{
    Storage::fake('local');
    $item = app(SaveLibraryItemAction::class)->execute($data + [
        'title' => 'Tides of the Atolls',
        'content_type' => 'research',
        'access_type' => 'free_public',
        'language' => 'en',
        'abstract' => 'A study.',
    ], null, $withPdf ? r1Pdf() : null);
    // R3: research is published only after peer review accepts it.
    if ($item->content_type->value === 'research') {
        peerAccept($item);
    }
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
    if ($publishedAt !== null) {
        $item->forceFill(['published_at' => $publishedAt])->save();
    }

    return $item->refresh();
}

function r1Writer(User $user, string $name): WriterProfile
{
    $application = app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => $name, 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return WriterProfile::query()->where('user_id', $user->id)->firstOrFail();
}

it('saves an author as a name, a writer account or a teacher, and links each the right way', function () {
    $teacher = Instructor::query()->create(['name' => 'Ustaadha Aminath', 'slug' => 'aminath', 'is_active' => true, 'sort_order' => 1]);
    $writerUser = User::factory()->create();
    $writer = r1Writer($writerUser, 'Ali Writer');
    $writer->forceFill(['slug' => 'ali-writer'])->save();

    $item = r1Item(['authors' => [
        ['instructor_profile_id' => $teacher->id],
        ['name' => 'Ali Writer', 'user_id' => $writerUser->id],
        'Guest Scholar',
    ]]);

    expect($item->authors->pluck('name')->all())->toBe(['Ustaadha Aminath', 'Ali Writer', 'Guest Scholar'])
        ->and((int) $item->authors[0]->instructor_profile_id)->toBe($teacher->id);

    $links = app(PresentLibraryItemAction::class)->execute($item->slug)['author_links'];
    expect($links)->toBe([
        ['name' => 'Ustaadha Aminath', 'kind' => 'teacher', 'url' => route('public.instructors.show', 'aminath')],
        ['name' => 'Ali Writer', 'kind' => 'writer', 'url' => route('public.library.author', 'ali-writer')],
        ['name' => 'Guest Scholar', 'kind' => 'name', 'url' => null],
    ]);

    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee('href="'.route('public.instructors.show', 'aminath').'"', false)
        ->assertSee('href="'.route('public.library.author', 'ali-writer').'"', false)
        ->assertSee('data-testid="author-name">Guest Scholar', false);
});

it('refuses a teacher who is not on the website, and saves nothing', function () {
    expect(fn () => app(SaveLibraryItemAction::class)->execute([
        'title' => 'Orphan Paper', 'content_type' => 'research', 'access_type' => 'free_public',
        'authors' => [['instructor_profile_id' => 999999]],
    ]))->toThrow(ValidationException::class);

    expect(LibraryItem::query()->where('title', 'Orphan Paper')->exists())->toBeFalse();
});

it('lets a writer name a teacher as co-author, with themself first as their own account', function () {
    $teacher = Instructor::query()->create(['name' => 'Ustaadh Hassan', 'slug' => 'hassan', 'is_active' => true, 'sort_order' => 1]);
    $writerUser = User::factory()->create();
    r1Writer($writerUser, 'Mariyam Writer');

    $this->withoutLocalizationMiddleware()->actingAs($writerUser)
        ->post(route('write.items.store'), [
            'title' => 'Reef Survey',
            'content_type' => 'research',
            'access_type' => 'free_login',
            'body' => '<p>One.</p>',
            'co_authors' => ['Guest Diver'],
            'co_author_teachers' => [$teacher->id],
            'delivery' => 'download',
        ])->assertSessionHasNoErrors();

    $item = LibraryItem::query()->where('title', 'Reef Survey')->firstOrFail();
    expect($item->authors->map(fn ($a) => [$a->name, $a->user_id !== null, $a->instructor_profile_id !== null])->all())
        ->toBe([['Mariyam Writer', true, false], ['Ustaadh Hassan', false, true], ['Guest Diver', false, false]])
        ->and($item->delivery)->toBe(LibraryDelivery::Download);

    // The editor gets the teachers to choose from, and what is chosen.
    $this->withoutLocalizationMiddleware()->actingAs($writerUser)->get(route('write.index'))
        ->assertInertia(fn ($page) => $page
            ->where('options.teachers.0.slug', 'hassan')
            ->where('dashboard.items.0.co_author_teachers', [$teacher->id])
            ->where('dashboard.items.0.co_authors', ['Guest Diver'])
            ->where('dashboard.items.0.delivery', 'download'));
});

it('defaults how readers get it by access type, lets the author change it, and keeps books in the reader', function () {
    $open = app(SaveLibraryItemAction::class)->execute(['title' => 'Open Paper', 'content_type' => 'research', 'access_type' => 'free_public']);
    $paid = app(SaveLibraryItemAction::class)->execute(['title' => 'Paid Paper', 'content_type' => 'article', 'access_type' => 'paid', 'price' => 20]);
    $book = app(SaveLibraryItemAction::class)->execute(['title' => 'A Book', 'content_type' => 'book', 'access_type' => 'free_public', 'delivery' => 'both']);

    expect($open->delivery)->toBe(LibraryDelivery::Both)
        ->and($paid->delivery)->toBe(LibraryDelivery::Reader)
        ->and($book->delivery)->toBe(LibraryDelivery::Reader);

    // The author's choice stands, and an edit that does not carry it keeps it.
    $paid = app(SaveLibraryItemAction::class)->execute(['title' => 'Paid Paper', 'content_type' => 'article', 'access_type' => 'paid', 'delivery' => 'both'], $paid);
    $paid = app(SaveLibraryItemAction::class)->execute(['title' => 'Paid Paper', 'content_type' => 'article', 'access_type' => 'paid'], $paid);
    expect($paid->delivery)->toBe(LibraryDelivery::Both);

    expect(fn () => app(SaveLibraryItemAction::class)->execute(['title' => 'Odd', 'content_type' => 'research', 'delivery' => 'fax']))
        ->toThrow(ValidationException::class);
});

it('serves a free public item\'s PDF to anyone, as a download', function () {
    $item = r1Item();

    $response = $this->withoutLocalizationMiddleware()->get(route('public.library.download', $item->slug));
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$item->slug.'.pdf"');
    expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();

    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertSee('data-testid="library-download-link"', false);
});

it('refuses a paid item\'s PDF without a grant, serves it with one, and logs the download', function () {
    $item = r1Item(['title' => 'Paid Findings', 'access_type' => 'paid', 'price' => 30, 'delivery' => 'both']);
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->get(route('public.library.download', $item->slug))
        ->assertRedirect(route('login'));
    $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.download', $item->slug))
        ->assertRedirect(route('public.library.show', $item->slug));
    $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.show', $item->slug))
        ->assertDontSee('data-testid="library-download-link"', false)
        ->assertSee('data-testid="library-download-later"', false);

    LibraryAccessGrant::query()->create(['user_id' => $reader->id, 'library_item_id' => $item->id, 'source_type' => 'admin', 'status' => 'active']);

    $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.download', $item->slug))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$item->slug.'.pdf"');

    $event = LibraryReadingEvent::query()->where('library_item_id', $item->id)->sole();
    expect($event->kind)->toBe('download')
        ->and((int) $event->user_id)->toBe($reader->id);
});

it('never hands over a file the author kept in the reader, or a book\'s', function () {
    $reader = r1Item(['title' => 'Reader Only', 'delivery' => 'reader']);
    $book = r1Item(['title' => 'Bound Book', 'content_type' => 'book']);
    $noPdf = r1Item(['title' => 'Text Only', 'body' => '<p>Words.</p>'], false);

    foreach ([$reader, $book, $noPdf] as $item) {
        $this->withoutLocalizationMiddleware()->get(route('public.library.download', $item->slug))->assertNotFound();
        expect(app(PresentLibraryItemAction::class)->execute($item->slug)['can_download'])->toBeFalse();
    }
});

it('keeps a download out of the page-rate count, and counts it apart in insights', function () {
    $item = r1Item();
    $reader = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.download', $item->slug))->assertOk();

    $insights = app(ListLibraryInsightsAction::class)->execute('all');
    expect($insights['headline']['downloads'])->toBe(1)
        ->and($insights['headline']['pages_opened'])->toBe(0)
        ->and($insights['headline']['active_readers'])->toBe(1);
});

it('filters the research shelf by year, and offers the years there are', function () {
    r1Item(['title' => 'Old Tides'], false, '2024-03-01 10:00:00');
    r1Item(['title' => 'New Tides'], false, '2026-05-01 10:00:00');

    $this->withoutLocalizationMiddleware()->get(route('public.library.index', ['content_type' => 'research', 'year' => 2024]))
        ->assertOk()
        ->assertSee('Old Tides')
        ->assertDontSee('New Tides')
        ->assertSee('data-testid="library-year"', false)
        ->assertSee('<option value="2026"', false)
        ->assertSee('<option value="2024" selected', false);

    // Only the research shelf asks for a year.
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertDontSee('data-testid="library-year"', false);
});

it('keeps the Library clear of HR\'s models', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Domains/Library'), FilesystemIterator::SKIP_DOTS));
    $offenders = [];
    foreach ($files as $file) {
        if ($file->getExtension() === 'php' && str_contains((string) file_get_contents($file->getPathname()), 'Domains\\HR\\Models')) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});
