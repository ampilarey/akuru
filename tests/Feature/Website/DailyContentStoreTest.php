<?php

use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Models\DailyContent;
use App\Support\Contracts\QuranTextProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves an ayah draft through the Quran provider and lists meanings on the calendar', function () {
    $ayah = w22Ayah();
    w22ImportMeanings();
    $admin = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.daily-content.store'), [
            'content_type' => 'ayah',
            'publish_date' => '2026-08-27',
            'surah_number' => 1,
            'ayah_number' => 1,
        ])
        ->assertRedirect();

    $row = DailyContent::query()->sole();
    expect($row->quran_ayah_id)->toBe($ayah->id)
        ->and($row->status)->toBe(DailyContentStatus::Draft)
        ->and($row->created_by)->toBe($admin->id)
        ->and($row->approved_by)->toBeNull();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.daily-content.index', ['month' => '2026-08']))
        ->assertOk()
        // Inertia since C9 slice 9: the month's items are props.
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Website/DailyContent')
            ->where('month', '2026-08')->where('items.0.content_type', 'ayah')->where('items.0.publish_date', '2026-08-27')
            ->where('items.0.ayah.meanings.en', 'In the name of Allah, the Beneficent, the Merciful.'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.daily-content.ayah-preview', [
            'surah_number' => 1,
            'ayah_number' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('id', $ayah->id)
        ->assertJsonPath('meanings.en', 'In the name of Allah, the Beneficent, the Merciful.');
});

it('blocks publishing a hadith that is missing collection, number, grading, or source', function () {
    $maker = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);
    $checker = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->post(route('admin.daily-content.store'), [
            'content_type' => 'hadith',
            'publish_date' => '2026-08-28',
            'hadith_text_en' => 'Actions are by intentions.',
            'hadith_text_dv' => 'ޢަމަލުތައް ނިޔަތުގެ މައްޗަށް',
            'hadith_text_ar' => 'إنما الأعمال بالنيات',
        ])
        ->assertRedirect();

    $row = DailyContent::query()->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($checker)
        ->from(route('admin.daily-content.queue'))
        ->post(route('admin.daily-content.approve', $row), ['status' => 'published'])
        ->assertSessionHasErrors('hadith_collection');

    expect($row->fresh()->status)->toBe(DailyContentStatus::Draft);
});

it('enforces maker-checker: creator cannot approve; a second reviewer can publish a complete hadith', function () {
    $maker = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);
    $checker = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->post(route('admin.daily-content.store'), [
            'content_type' => 'hadith',
            'publish_date' => '2026-08-29',
            'hadith_text_en' => 'Actions are by intentions.',
            'hadith_text_dv' => 'ޢަމަލުތައް ނިޔަތުގެ މައްޗަށް',
            'hadith_text_ar' => 'إنما الأعمال بالنيات',
            'hadith_collection' => 'Bukhari',
            'hadith_number' => '1',
            'hadith_grading' => 'sahih',
            'grading_source' => 'Sahih al-Bukhari',
        ])
        ->assertRedirect();

    $row = DailyContent::query()->sole();

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->get(route('admin.daily-content.queue'))
        ->assertOk()
        // The queue is props too; the page shows "Waiting for another reviewer" where created_by is the viewer.
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Website/DailyContentQueue')
            ->where('items.0.hadith_collection', 'Bukhari')->where('items.0.created_by', $maker->id)->where('t.daily_waiting_reviewer', 'Waiting for another reviewer'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->post(route('admin.daily-content.approve', $row), ['status' => 'published'])
        ->assertSessionHasErrors('approved_by');

    $this->withoutLocalizationMiddleware()
        ->actingAs($checker)
        ->post(route('admin.daily-content.approve', $row), ['status' => 'published'])
        ->assertRedirect(route('admin.daily-content.queue'));

    expect($row->fresh()->status)->toBe(DailyContentStatus::Published)
        ->and($row->fresh()->approved_by)->toBe($checker->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->put(route('admin.daily-content.update', $row), [
            'content_type' => 'hadith',
            'publish_date' => '2026-08-29',
            'hadith_text_en' => 'Actions are by intentions (edited).',
            'hadith_text_dv' => 'ޢަމަލުތައް ނިޔަތުގެ މައްޗަށް',
            'hadith_text_ar' => 'إنما الأعمال بالنيات',
            'hadith_collection' => 'Bukhari',
            'hadith_number' => '1',
            'hadith_grading' => 'sahih',
            'grading_source' => 'Sahih al-Bukhari',
        ])
        ->assertRedirect();

    expect($row->fresh()->status)->toBe(DailyContentStatus::Draft)
        ->and($row->fresh()->approved_by)->toBeNull();
});

it('rejects save attempts that skip approval and create a reminder theme batch', function () {
    $admin = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.daily-content.store'), [
            'content_type' => 'reminder',
            'publish_date' => '2026-09-01',
            'status' => 'published',
            'text_en' => 'Seek knowledge.',
            'text_dv' => 'އިލްމު ހޯދާ',
            'attribution' => 'Teaching note',
        ])
        ->assertSessionHasErrors('status');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.daily-content.batch'), [
            'publish_date' => '2026-09-01',
            'days' => 3,
            'theme_tag' => 'knowledge',
            'text_en' => 'Seek knowledge.',
            'text_dv' => 'އިލްމު ހޯދާ',
            'attribution' => 'Teaching note',
        ])
        ->assertRedirect();

    expect(DailyContent::query()->count())->toBe(3)
        ->and(DailyContent::query()->where('theme_tag', 'knowledge')->where('status', 'draft')->count())->toBe(3);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.daily-content.export', ['month' => '2026-09']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('knowledge')->and($csv)->toContain('reminder');
});

it('forbids staff without daily_content permissions and approvers without daily_content.approve', function () {
    // The website is the system admin's alone (ADR-040 slice 2), and the W22
    // migration grants that role both abilities — so the maker-checker split
    // is asserted on system admins whose role has been stripped of them.
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web')->revokePermissionTo(['daily_content.manage', 'daily_content.approve']);
    $viewer = actingSystemAdmin([]);
    $maker = actingSystemAdmin(['daily_content.manage']);
    $row = DailyContent::query()->create([
        'content_type' => 'reminder',
        'publish_date' => '2026-09-10',
        'status' => 'draft',
        'text_en' => 'Seek knowledge.',
        'text_dv' => 'އިލްމު ހޯދާ',
        'attribution' => 'Teaching note',
        'created_by' => $maker->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('admin.daily-content.index'))
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($viewer)
        ->get(route('admin.daily-content.index'))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->get(route('admin.daily-content.queue'))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($maker)
        ->post(route('admin.daily-content.approve', $row), ['status' => 'published'])
        ->assertForbidden();
});

it('does not import Hifz or Courses models from new W2.2 Website files', function () {
    $files = [
        app_path('Domains/Website/Actions/SaveDailyContentAction.php'),
        app_path('Domains/Website/Actions/ApproveDailyContentAction.php'),
        app_path('Domains/Website/Actions/ListDailyContentsAction.php'),
        app_path('Domains/Website/Actions/CreateDailyContentBatchAction.php'),
        app_path('Domains/Website/Http/Controllers/Admin/PublicSite/DailyContentController.php'),
        app_path('Domains/Website/Models/DailyContent.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)
            ->not->toContain('App\\Domains\\Hifz\\')
            ->and($src)->not->toContain('App\\Domains\\Courses\\Models\\');
    }

    expect(file_get_contents(app_path('Domains/Website/Actions/SaveDailyContentAction.php')))
        ->toContain('QuranTextProviderInterface');
    expect(file_get_contents(app_path('Domains/Website/Actions/ListDailyContentsAction.php')))
        ->toContain('QuranTextProviderInterface');

    expect(config('morph-map.daily_content'))->toBe(DailyContent::class);
    expect(app(QuranTextProviderInterface::class)->findAyahById(0))->toBeNull();
});
