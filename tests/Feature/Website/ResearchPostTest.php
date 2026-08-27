<?php

use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Models\User;
use App\Domains\Website\Actions\BuildPublicSitemapAction;
use App\Domains\Website\Actions\ListResearchPostsAction;
use App\Domains\Website\Actions\PresentResearchPostAction;
use App\Domains\Website\Enums\PostType;
use App\Domains\Website\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function w25Instructor(array $overrides = []): Instructor
{
    return Instructor::query()->create(array_merge([
        'name' => 'Ustadha W25 Shifa',
        'slug' => 'ustadha-w25-shifa-'.fake()->unique()->numerify('###'),
        'bio' => 'Teaches Arabic and research methods.',
        'qualification' => 'Ijazah in Quran',
        'specialization' => 'Tajweed',
        'is_active' => true,
        'sort_order' => 1,
    ], $overrides));
}

function w25Research(array $overrides = []): Post
{
    $authorId = $overrides['author_id'] ?? User::factory()->create()->id;
    unset($overrides['author_id']);

    return Post::query()->create(array_merge([
        'type' => PostType::Research->value,
        'title' => 'W25 Research Paper',
        'slug' => 'w25-research-paper-'.fake()->unique()->numerify('###'),
        'summary' => 'Abstract summary',
        'abstract' => 'This paper examines dhivehi tafsir methods.',
        'body' => '<p>Research body</p>',
        'citation_note' => 'Akuru Working Paper 2026',
        'authors' => [],
        'is_published' => true,
        'published_at' => now()->subDay(),
        'author_id' => $authorId,
    ], $overrides));
}

it('lets a guest list published research and filter by year and author', function () {
    $this->travelTo('2026-08-27 12:00:00');
    $shifa = w25Instructor(['name' => 'Ustadha W25 Shifa', 'slug' => 'ustadha-w25-shifa']);
    $other = w25Instructor(['name' => 'Ustadh W25 Other', 'slug' => 'ustadh-w25-other']);

    $match = w25Research([
        'title' => 'W25 Dhivehi Tafsir',
        'slug' => 'w25-dhivehi-tafsir',
        'authors' => [['instructor_id' => $shifa->id], ['name' => 'External Scholar']],
        'published_at' => '2026-03-01 09:00:00',
    ]);
    w25Research([
        'title' => 'W25 Other Paper',
        'slug' => 'w25-other-paper',
        'authors' => [['instructor_id' => $other->id]],
        'published_at' => '2025-06-01 09:00:00',
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.index'))
        ->assertOk()
        ->assertSee('W25 Dhivehi Tafsir', false)
        ->assertSee('W25 Other Paper', false)
        ->assertSee('Ustadha W25 Shifa', false)
        ->assertSee('External Scholar', false);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.index', ['year' => 2026, 'instructor_id' => $shifa->id]))
        ->assertOk()
        ->assertSee('W25 Dhivehi Tafsir', false)
        ->assertDontSee('W25 Other Paper', false);

    expect(app(ListResearchPostsAction::class)->execute(['year' => 2026, 'instructor_id' => $shifa->id], true))
        ->toHaveCount(1)
        ->and($match->fresh()->type)->toBe(PostType::Research->value);
});

it('returns 404 for unpublished research', function () {
    $draft = w25Research([
        'title' => 'W25 Draft Paper',
        'slug' => 'w25-draft-paper',
        'is_published' => false,
        'published_at' => now()->subDay(),
    ]);
    $future = w25Research([
        'title' => 'W25 Future Paper',
        'slug' => 'w25-future-paper',
        'is_published' => true,
        'published_at' => now()->addWeek(),
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.show', $draft->slug))
        ->assertNotFound();
    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.show', $future->slug))
        ->assertNotFound();
    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.index'))
        ->assertOk()
        ->assertDontSee('W25 Draft Paper', false)
        ->assertDontSee('W25 Future Paper', false);
});

it('omits research from article and news listings and 404s the wrong permalink', function () {
    $research = w25Research([
        'title' => 'W25 Secret Research',
        'slug' => 'w25-secret-research',
    ]);
    $article = Post::query()->create([
        'type' => PostType::Article->value,
        'title' => 'W25 Article Only',
        'slug' => 'w25-article-only',
        'summary' => 'Article',
        'body' => 'Body',
        'is_published' => true,
        'published_at' => now()->subDay(),
        'author_id' => User::factory()->create()->id,
    ]);
    $news = Post::query()->create([
        'type' => PostType::News->value,
        'title' => 'W25 News Only',
        'slug' => 'w25-news-only',
        'summary' => 'News',
        'body' => 'Body',
        'is_published' => true,
        'published_at' => now()->subDay(),
        'author_id' => User::factory()->create()->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.articles.index'))
        ->assertOk()
        ->assertSee('W25 Article Only', false)
        ->assertDontSee('W25 Secret Research', false);
    $this->withoutLocalizationMiddleware()
        ->get(route('public.news.index'))
        ->assertOk()
        ->assertSee('W25 News Only', false)
        ->assertDontSee('W25 Secret Research', false);
    $this->withoutLocalizationMiddleware()
        ->get(route('public.articles.show', $research->slug))
        ->assertNotFound();
    $this->withoutLocalizationMiddleware()
        ->get(route('public.news.show', $research->slug))
        ->assertNotFound();
    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.show', $research->slug))
        ->assertOk()
        ->assertSee('W25 Secret Research', false);
    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.show', $article->slug))
        ->assertNotFound();
    $this->withoutLocalizationMiddleware()
        ->get(route('public.news.show', $article->slug))
        ->assertNotFound();
});

it('shows an instructor profile with their research', function () {
    $shifa = w25Instructor([
        'name' => 'Ustadha W25 Profile',
        'slug' => 'ustadha-w25-profile',
        'qualification' => 'Ijazah in Quran',
        'bio' => 'Has taught at Akuru for several years.',
    ]);
    w25Research([
        'title' => 'W25 Profile Paper',
        'slug' => 'w25-profile-paper',
        'authors' => [['instructor_id' => $shifa->id]],
    ]);
    w25Research([
        'title' => 'W25 Unrelated Paper',
        'slug' => 'w25-unrelated-paper',
        'authors' => [['name' => 'Someone Else']],
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.instructors.show', $shifa->slug))
        ->assertOk()
        ->assertSee('Ustadha W25 Profile', false)
        ->assertSee('Ijazah in Quran', false)
        ->assertSee('Has taught at Akuru for several years.', false)
        ->assertSee('W25 Profile Paper', false)
        ->assertDontSee('W25 Unrelated Paper', false);

    $inactive = w25Instructor([
        'name' => 'Inactive W25',
        'slug' => 'inactive-w25',
        'is_active' => false,
    ]);
    $this->withoutLocalizationMiddleware()
        ->get(route('public.instructors.show', $inactive->slug))
        ->assertNotFound();
});

it('links a stored research PDF on the public show page', function () {
    Storage::fake('public');
    $admin = actingPeopleAdmin();
    $pdf = UploadedFile::fake()->create('w25-paper.pdf', 40, 'application/pdf');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.research.store'), [
            'title' => 'W25 PDF Paper',
            'slug' => 'w25-pdf-paper',
            'abstract' => 'Has a PDF.',
            'body' => '<p>PDF body</p>',
            'citation_note' => 'Working paper',
            'is_published' => '1',
            'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'pdf' => $pdf,
        ])
        ->assertRedirect();

    $row = Post::query()->where('slug', 'w25-pdf-paper')->sole();
    expect($row->type)->toBe(PostType::Research->value)
        ->and($row->pdf_document_id)->not->toBeNull();

    $presented = app(PresentResearchPostAction::class)->execute($row);
    expect($presented['pdf']['url'])->toContain('research-pdfs/');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.research.show', 'w25-pdf-paper'))
        ->assertOk()
        ->assertSee('Download PDF', false)
        ->assertSee('research-pdfs/', false);
});

it('lets an admin save research and export CSV', function () {
    $admin = actingPeopleAdmin();
    $shifa = w25Instructor(['name' => 'Ustadha W25 CSV', 'slug' => 'ustadha-w25-csv']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.research.store'), [
            'title' => 'W25 Admin Saved',
            'slug' => 'w25-admin-saved',
            'abstract' => 'Admin abstract',
            'body' => '<p>Admin body</p>',
            'citation_note' => 'Cite me',
            'instructor_ids' => [$shifa->id],
            'external_names' => "Dr External\n",
            'is_published' => '1',
            'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    $row = Post::query()->where('slug', 'w25-admin-saved')->sole();
    expect($row->authors)->toBe([
        ['instructor_id' => $shifa->id],
        ['name' => 'Dr External'],
    ])->and($row->abstract)->toBe('Admin abstract');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.research.index'))
        ->assertOk()
        ->assertSee('W25 Admin Saved', false);

    $adminCsv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.research.export'))
        ->assertOk()
        ->streamedContent();
    expect($adminCsv)->toContain('W25 Admin Saved')->toContain('Ustadha W25 CSV');

    $publicCsv = $this->withoutLocalizationMiddleware()
        ->get(route('public.research.export'))
        ->assertOk()
        ->streamedContent();
    expect($publicCsv)->toContain('W25 Admin Saved');
});

it('includes research listing and permalinks in the sitemap', function () {
    w25Research([
        'title' => 'W25 Sitemap Paper',
        'slug' => 'w25-sitemap-paper',
    ]);
    w25Research([
        'title' => 'W25 Draft Sitemap',
        'slug' => 'w25-draft-sitemap',
        'is_published' => false,
    ]);

    $xml = $this->withoutLocalizationMiddleware()
        ->get(route('public.sitemap'))
        ->assertOk()
        ->getContent();

    expect($xml)
        ->toContain('/en/research')
        ->toContain('/en/research/w25-sitemap-paper')
        ->toContain('/dv/research/w25-sitemap-paper')
        ->not->toContain('w25-draft-sitemap')
        ->and(app(BuildPublicSitemapAction::class)->execute())->toContain('/en/research/w25-sitemap-paper');
});

it('does not add a parallel post_type column or paywall fields', function () {
    expect(Schema::hasColumn('posts', 'authors'))->toBeTrue()
        ->and(Schema::hasColumn('posts', 'pdf_document_id'))->toBeTrue()
        ->and(Schema::hasColumn('posts', 'abstract'))->toBeTrue()
        ->and(Schema::hasColumn('posts', 'citation_note'))->toBeTrue()
        ->and(Schema::hasColumn('posts', 'post_type'))->toBeFalse()
        ->and(Schema::hasColumn('posts', 'price'))->toBeFalse()
        ->and(Schema::hasColumn('posts', 'is_paid'))->toBeFalse()
        ->and(Schema::hasColumn('posts', 'paywall'))->toBeFalse();

    $fillable = (new Post)->getFillable();
    expect($fillable)->not->toContain('price')
        ->and($fillable)->not->toContain('is_paid')
        ->and($fillable)->toContain('authors');

    $save = file_get_contents(app_path('Domains/Website/Actions/SaveResearchPostAction.php'));
    expect($save)->not->toContain('paywall')
        ->and($save)->not->toContain('price')
        ->and($save)->toContain('PostType::Research');
});

it('keeps new W2.5 website files off other-domain models', function () {
    $files = [
        app_path('Domains/Website/Enums/PostType.php'),
        app_path('Domains/Website/Actions/PresentResearchPostAction.php'),
        app_path('Domains/Website/Actions/ListResearchPostsAction.php'),
        app_path('Domains/Website/Actions/SaveResearchPostAction.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/ResearchPostController.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/InstructorProfileController.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/ResearchPostController.php'),
        app_path('Domains/HR/Actions/ReadPublicInstructorProfileAction.php'),
        app_path('Domains/HR/Actions/ListPublicInstructorProfilesAction.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)
            ->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toContain('App\\Domains\\Courses\\Models\\')
            ->and($src)->not->toContain('App\\Domains\\Media\\Models\\')
            ->and($src)->not->toContain('App\\Domains\\People\\Models\\');
    }

    $website = [
        app_path('Domains/Website/Actions/PresentResearchPostAction.php'),
        app_path('Domains/Website/Actions/ListResearchPostsAction.php'),
        app_path('Domains/Website/Actions/SaveResearchPostAction.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/ResearchPostController.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/InstructorProfileController.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/ResearchPostController.php'),
    ];
    foreach ($website as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\HR\\Models\\');
    }

    $present = file_get_contents(app_path('Domains/Website/Actions/PresentResearchPostAction.php'));
    expect($present)->toContain('ReadPublicInstructorProfileAction')
        ->and($present)->toContain('ListPublicMediaFilesAction');

    $save = file_get_contents(app_path('Domains/Website/Actions/SaveResearchPostAction.php'));
    expect($save)->toContain('StorePublicMediaAction');
});
