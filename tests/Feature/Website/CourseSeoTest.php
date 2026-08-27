<?php

use App\Domains\Courses\Actions\ComposeCourseSeoAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\Website\Actions\BuildPublicSitemapAction;
use App\Domains\Website\Actions\ComposeFaqPageJsonLdAction;
use App\Domains\Website\Actions\ComposeHreflangLinksAction;
use App\Domains\Website\Actions\ComposeOrganizationJsonLdAction;
use App\Domains\Website\Actions\ListCoursePageFaqsAction;
use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seoCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'title' => 'W15 Arabic Beginners',
        'slug' => 'w15-arabic-beginners-'.fake()->unique()->numerify('###'),
        'short_desc' => 'Learn the Arabic alphabet with tajweed.',
        'status' => 'open',
        'fee' => 300,
        'cover_image' => 'courses/w15-cover.jpg',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-15',
        'meta' => [
            'early_bird_active' => true,
            'early_bird_amount' => 180,
            'early_bird_ends_at' => '2026-12-31',
        ],
    ], $overrides));
}

/**
 * @return list<array<string, mixed>>
 */
function jsonLdBlocks(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    $blocks = [];
    foreach ($matches[1] as $json) {
        $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_array($decoded)) {
            $blocks[] = $decoded;
        }
    }

    return $blocks;
}

function jsonLdOfType(array $blocks, string $type): ?array
{
    foreach ($blocks as $block) {
        if (($block['@type'] ?? null) === $type) {
            return $block;
        }
    }

    return null;
}

it('emits Course + CourseInstance JSON-LD with dates and the displayed price', function () {
    $this->travelTo('2026-08-27 12:00:00');
    $course = seoCourse();

    $seo = app(ComposeCourseSeoAction::class)->execute($course->id);
    expect($seo['json_ld']['@type'])->toBe('Course')
        ->and($seo['json_ld']['hasCourseInstance']['@type'])->toBe('CourseInstance')
        ->and($seo['json_ld']['hasCourseInstance']['startDate'])->toBe('2026-09-01')
        ->and($seo['json_ld']['hasCourseInstance']['endDate'])->toBe('2026-12-15')
        ->and($seo['json_ld']['hasCourseInstance']['offers']['price'])->toBe('180.00')
        ->and($seo['json_ld']['hasCourseInstance']['offers']['priceCurrency'])->toBe('MVR')
        ->and($seo['og']['title'])->toBe('W15 Arabic Beginners')
        ->and($seo['og']['image'])->toContain('storage/courses/w15-cover.jpg')
        ->and($seo['og']['price_amount'])->toBe('180.00')
        ->and($seo['og']['price_currency'])->toBe('MVR');

    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertSee('property="og:title"', false)
        ->assertSee('W15 Arabic Beginners', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('storage/courses/w15-cover.jpg', false)
        ->assertSee('product:price:amount', false)
        ->assertSee('180.00', false)
        ->assertSee('product:price:currency', false)
        ->assertSee('MVR', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('hreflang="dv"', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="x-default"', false)
        ->assertSee('html lang="en"', false)
        ->getContent();

    $blocks = jsonLdBlocks($html);
    $courseLd = jsonLdOfType($blocks, 'Course');
    $orgLd = jsonLdOfType($blocks, 'Organization');
    $faqLd = jsonLdOfType($blocks, 'FAQPage');

    expect($courseLd)->not->toBeNull()
        ->and($courseLd['@context'])->toBe('https://schema.org')
        ->and($courseLd['hasCourseInstance']['startDate'])->toBe('2026-09-01')
        ->and($courseLd['offers']['price'])->toBe('180.00')
        ->and($orgLd)->not->toBeNull()
        ->and($orgLd['name'])->not->toBe('')
        ->and($faqLd)->not->toBeNull()
        ->and($faqLd['mainEntity'])->toHaveCount(6)
        ->and($faqLd['mainEntity'][0]['name'])->toBe('Who is this course for?')
        ->and($html)->toContain('Who is this course for?');
});

it('omits product price tags and Offer when the course fee is unknown', function () {
    $course = seoCourse([
        'fee' => null,
        'meta' => null,
        'start_date' => null,
        'end_date' => null,
    ]);

    $seo = app(ComposeCourseSeoAction::class)->execute($course->id);
    expect($seo['og']['price_amount'])->toBeNull()
        ->and($seo['json_ld']['hasCourseInstance'])->not->toHaveKey('startDate')
        ->and($seo['json_ld'])->not->toHaveKey('offers')
        ->and($seo['json_ld']['hasCourseInstance'])->not->toHaveKey('offers');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))
        ->assertOk()
        ->assertDontSee('product:price:amount', false);
});

it('builds hreflang triplets from the current URL', function () {
    $links = app(ComposeHreflangLinksAction::class)->execute('http://example.test/en/courses/w15-arabic-beginners');
    $byLang = collect($links)->keyBy('hreflang');

    expect($byLang->keys()->all())->toBe(['en', 'ar', 'dv', 'x-default'])
        ->and($byLang['en']['href'])->toBe('http://example.test/en/courses/w15-arabic-beginners')
        ->and($byLang['dv']['href'])->toBe('http://example.test/dv/courses/w15-arabic-beginners')
        ->and($byLang['ar']['href'])->toBe('http://example.test/ar/courses/w15-arabic-beginners')
        ->and($byLang['x-default']['href'])->toBe('http://example.test/en/courses/w15-arabic-beginners');
});

it('includes courses, articles, and events with hreflang in the XML sitemap', function () {
    $this->travelTo('2026-08-27 12:00:00');
    $course = seoCourse(['slug' => 'w15-sitemap-course', 'status' => 'open']);
    Course::factory()->create(['slug' => 'w15-closed-course', 'status' => 'closed', 'cover_image' => 'x.jpg']);

    $author = User::factory()->create();
    Post::query()->create([
        'type' => 'article',
        'title' => 'W15 Article Guide',
        'slug' => 'w15-article-guide',
        'summary' => 'Article summary',
        'body' => 'Article body',
        'is_published' => true,
        'published_at' => now()->subDay(),
        'author_id' => $author->id,
    ]);
    Post::query()->create([
        'type' => 'news',
        'title' => 'W15 News Item',
        'slug' => 'w15-news-item',
        'summary' => 'News summary',
        'body' => 'News body',
        'is_published' => true,
        'published_at' => now()->subDay(),
        'author_id' => $author->id,
    ]);

    $event = app(SaveEventAction::class)->execute([
        'title' => 'W15 Public Event',
        'location' => 'Malé',
        'start_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'end_date' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
        'status' => 'published',
        'is_public' => true,
        'registration_type' => 'none',
    ]);

    $xml = $this->withoutLocalizationMiddleware()
        ->get(route('public.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)
        ->toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
        ->toContain('/en/courses/w15-sitemap-course')
        ->toContain('/dv/courses/w15-sitemap-course')
        ->toContain('/ar/courses/w15-sitemap-course')
        ->toContain('/en/articles/w15-article-guide')
        ->toContain('/en/news/w15-news-item')
        ->toContain('/en/events/'.$event->id)
        ->toContain('hreflang="en"')
        ->toContain('hreflang="dv"')
        ->toContain('hreflang="ar"')
        ->toContain('hreflang="x-default"')
        ->not->toContain('w15-closed-course')
        ->and(app(BuildPublicSitemapAction::class)->execute())->toContain('/en/courses/w15-sitemap-course');
});

it('matches FAQPage JSON-LD to the rendered course FAQs', function () {
    $faqs = app(ListCoursePageFaqsAction::class)->execute();
    $json = app(ComposeFaqPageJsonLdAction::class)->execute($faqs);

    expect($faqs)->toHaveCount(6)
        ->and($json['@type'])->toBe('FAQPage')
        ->and($json['mainEntity'][2]['name'])->toBe($faqs[2]['q'])
        ->and($json['mainEntity'][2]['acceptedAnswer']['text'])->toBe($faqs[2]['a'])
        ->and(app(ComposeFaqPageJsonLdAction::class)->execute([]))->toBe([]);
});

it('builds a sitewide Organization JSON-LD graph', function () {
    $json = app(ComposeOrganizationJsonLdAction::class)->execute();
    expect($json['@type'])->toBe('Organization')
        ->and($json['@context'])->toBe('https://schema.org')
        ->and($json['url'])->not->toBe('');

    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->getContent();

    expect(jsonLdOfType(jsonLdBlocks($html), 'Organization'))->not->toBeNull();
});

it('does not import other-domain models from new W1.5 website files', function () {
    $files = [
        app_path('Domains/Website/Actions/ComposeOrganizationJsonLdAction.php'),
        app_path('Domains/Website/Actions/ListCoursePageFaqsAction.php'),
        app_path('Domains/Website/Actions/ComposeFaqPageJsonLdAction.php'),
        app_path('Domains/Website/Actions/ComposeHreflangLinksAction.php'),
        app_path('Domains/Website/Actions/BuildPublicSitemapAction.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/SitemapController.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toMatch('/App\\\\Domains\\\\Courses\\\\Models\\\\/');
    }

    $sitemap = file_get_contents(app_path('Domains/Website/Actions/BuildPublicSitemapAction.php'));
    expect($sitemap)->toContain('App\\Domains\\Courses\\Actions\\ListPublicCourseSitemapEntriesAction');
});
