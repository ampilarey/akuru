<?php

use App\Domains\Settings\Models\Setting;
use App\Domains\Website\Actions\GenerateShareCardAction;
use App\Domains\Website\Actions\PublishDueDailyContentsAction;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shows today\'s published ayah meanings on the homepage widget', function () {
    $this->travelTo('2026-08-27 12:00:00');
    w23PublishedAyah('2026-08-27');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertSee('id="daily-widget"', false)
        ->assertSee('data-layout="stacked"', false)
        ->assertSee('data-daily-type="ayah"', false)
        ->assertSee('In the name of Allah, the Beneficent, the Merciful.', false)
        ->assertDontSee('data-fallback="1"', false);
});

it('falls back to the most recent published item when today is empty', function () {
    $this->travelTo('2026-08-30 12:00:00');
    w23PublishedAyah('2026-08-27');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertSee('data-fallback="1"', false)
        ->assertSee('data-daily-type="ayah"', false)
        ->assertSee('In the name of Allah, the Beneficent, the Merciful.', false);
});

it('rotates to a single type per day when daily.homepage_layout is rotate', function () {
    $this->travelTo('2026-01-01 12:00:00');
    Setting::set('daily.homepage_layout', 'rotate');
    w23PublishedAyah('2026-01-01');
    w23PublishedHadith('2026-01-01');

    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertSee('data-layout="rotate"', false)
        ->assertSee('data-daily-type="ayah"', false)
        ->getContent();

    expect(substr_count($html, 'data-daily-type="hadith"'))->toBe(0);
});

it('returns 404 for unpublished permalinks and 200 with Article schema for published ones', function () {
    $this->travelTo('2026-08-27 12:00:00');
    $ayah = w23PublishedAyah('2026-08-27');
    $draft = w23Published([
        'content_type' => DailyContentType::Hadith->value,
        'publish_date' => '2026-08-28',
        'status' => DailyContentStatus::Draft->value,
        'approved_by' => null,
        'hadith_text_en' => 'Hidden hadith',
        'hadith_collection' => 'Bukhari',
        'hadith_number' => '2',
        'hadith_grading' => 'sahih',
        'grading_source' => 'Sahih al-Bukhari',
        'text_en' => null,
        'text_dv' => null,
        'attribution' => null,
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.show', ['type' => 'hadith', 'date' => $draft->publish_date->toDateString()]))
        ->assertNotFound();

    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.show', ['type' => 'ayah', 'date' => '2026-08-27']))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('data-share="whatsapp"', false)
        ->assertSee('data-share="twitter"', false)
        ->assertSee('wa.me', false)
        ->assertSee('twitter.com/intent/tweet', false)
        ->assertSee('In the name of Allah, the Beneficent, the Merciful.', false)
        ->assertSee('property="og:type" content="article"', false)
        ->getContent();

    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    $article = null;
    foreach ($matches[1] as $json) {
        $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_array($decoded) && ($decoded['@type'] ?? null) === 'Article') {
            $article = $decoded;
        }
    }

    expect($article)->not->toBeNull()
        ->and($article['headline'])->toContain('Daily ayah')
        ->and(json_encode($article))->not->toContain('fixture')
        ->and(json_encode($article))->not->toContain('Akuru teaching gloss')
        ->and($ayah->status)->toBe(DailyContentStatus::Published);
});

it('filters the public archive by type and theme', function () {
    w23PublishedAyah('2026-08-27');
    w23Published([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-08-27',
        'theme_tag' => 'knowledge',
        'text_en' => 'Seek knowledge.',
        'text_dv' => 'އިލްމު ހޯދާ',
        'attribution' => 'Teaching note',
    ]);
    w23Published([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-08-29',
        'theme_tag' => 'parenting',
        'text_en' => 'Be kind to children.',
        'text_dv' => 'ކުދިންނަށް ރައްކާތެރިވާ',
        'attribution' => 'Teaching note',
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.index', ['type' => 'reminder', 'theme_tag' => 'knowledge']))
        ->assertOk()
        ->assertSee('Seek knowledge.', false)
        ->assertSee('data-theme="knowledge"', false)
        ->assertDontSee('Be kind to children.', false);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.index', ['type' => 'ayah']))
        ->assertOk()
        ->assertSee('data-daily-type="ayah"', false)
        ->assertDontSee('Seek knowledge.', false);
});

it('publishes due scheduled rows and leaves future scheduled items alone', function () {
    $this->travelTo('2026-08-27 00:10:00');
    $maker = actingPeopleAdmin(['daily_content.manage']);
    $checker = actingPeopleAdmin(['daily_content.approve']);

    $due = DailyContent::query()->create([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-08-27',
        'status' => DailyContentStatus::Scheduled->value,
        'text_en' => 'Due today.',
        'text_dv' => 'މިއަދު',
        'attribution' => 'Teaching note',
        'created_by' => $maker->id,
        'approved_by' => $checker->id,
    ]);
    $past = DailyContent::query()->create([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-08-26',
        'status' => DailyContentStatus::Scheduled->value,
        'text_en' => 'Due yesterday.',
        'text_dv' => 'އިއްޔެ',
        'attribution' => 'Teaching note',
        'created_by' => $maker->id,
        'approved_by' => $checker->id,
    ]);
    $future = DailyContent::query()->create([
        'content_type' => DailyContentType::Reminder->value,
        'publish_date' => '2026-08-29',
        'status' => DailyContentStatus::Scheduled->value,
        'text_en' => 'Still waiting.',
        'text_dv' => 'އަދި މުސްކުޅި',
        'attribution' => 'Teaching note',
        'created_by' => $maker->id,
        'approved_by' => $checker->id,
    ]);

    $this->artisan('daily-content:publish-due')->assertSuccessful();

    expect($due->fresh()->status)->toBe(DailyContentStatus::Published)
        ->and($past->fresh()->status)->toBe(DailyContentStatus::Published)
        ->and($future->fresh()->status)->toBe(DailyContentStatus::Scheduled)
        ->and(app(PublishDueDailyContentsAction::class)->execute())->toBe(0);
});

it('renders a 1080 square hadith share card with collection number grading and source on the line list', function () {
    Storage::fake('public');
    $row = w23PublishedHadith('2026-08-28');

    expect(config('media.card_fonts.thaana'))->toBe(resource_path('fonts/share-cards/NotoSansThaana-Regular.ttf'))
        ->and(file_exists((string) config('media.card_fonts.thaana')))->toBeTrue()
        ->and(file_exists((string) config('media.card_fonts.arabic')))->toBeTrue();

    $result = app(GenerateShareCardAction::class)->execute($row);
    $joined = implode(' ', $result['lines']);

    expect($joined)->toContain('Bukhari')
        ->and($joined)->toContain('1')
        ->and($joined)->toContain('sahih')
        ->and($joined)->toContain('Sahih al-Bukhari')
        ->and($result['path'])->toBe('daily-cards/'.$row->id.'.png');

    $png = Storage::disk('public')->get($result['path']);
    $info = getimagesizefromstring($png);
    expect($info[0])->toBe(1080)
        ->and($info[1])->toBe(1080)
        ->and($info['mime'])->toBe('image/png')
        ->and($row->fresh()->share_card_path)->toBe($result['path']);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.daily.card', ['type' => 'hadith', 'date' => '2026-08-28']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('includes published daily permalinks in the sitemap and omits drafts', function () {
    w23PublishedAyah('2026-08-27');
    w23Published([
        'content_type' => DailyContentType::Hadith->value,
        'publish_date' => '2026-08-28',
        'status' => DailyContentStatus::Draft->value,
        'approved_by' => null,
        'hadith_text_en' => 'Hidden',
        'hadith_collection' => 'Bukhari',
        'hadith_number' => '9',
        'hadith_grading' => 'sahih',
        'grading_source' => 'Sahih al-Bukhari',
        'text_en' => null,
        'text_dv' => null,
        'attribution' => null,
    ]);

    $xml = $this->withoutLocalizationMiddleware()
        ->get(route('public.sitemap'))
        ->assertOk()
        ->getContent();

    expect($xml)
        ->toContain('/en/daily/ayah')
        ->toContain('/en/daily/ayah/2026-08-27')
        ->toContain('/dv/daily/ayah/2026-08-27')
        ->not->toContain('/en/daily/hadith/2026-08-28');
});

it('does not import Hifz or Courses models from new W2.3 Website files', function () {
    $files = [
        app_path('Domains/Website/Actions/ListPublicDailyContentsAction.php'),
        app_path('Domains/Website/Actions/ComposeHomepageDailyAction.php'),
        app_path('Domains/Website/Actions/ComposeDailyContentSeoAction.php'),
        app_path('Domains/Website/Actions/GenerateShareCardAction.php'),
        app_path('Domains/Website/Actions/PublishDueDailyContentsAction.php'),
        app_path('Domains/Website/Actions/StreamDailyShareCardAction.php'),
        app_path('Domains/Website/Jobs/RenderDailyShareCardJob.php'),
        app_path('Domains/Website/Http/Controllers/PublicSite/DailyContentController.php'),
        app_path('Domains/Website/Console/PublishDueDailyContentsCommand.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)
            ->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toContain('App\\Domains\\Courses\\Models\\')
            ->and($src)->not->toContain('WebPImageService');
    }

    expect(file_get_contents(app_path('Domains/Website/Actions/GenerateShareCardAction.php')))
        ->toContain('ImageProcessorInterface')
        ->and(file_get_contents(app_path('Domains/Website/Actions/GenerateShareCardAction.php')))
        ->toContain('StoreGeneratedPublicImageAction');
    expect(file_get_contents(app_path('Domains/Website/Actions/ListDailyContentsAction.php')))
        ->toContain('QuranTextProviderInterface');
    expect(file_get_contents(app_path('Domains/Website/Actions/SaveDailyContentAction.php')))
        ->toContain('QuranTextProviderInterface');
});
