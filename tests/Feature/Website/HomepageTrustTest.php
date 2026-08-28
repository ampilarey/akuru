<?php

use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Settings\Models\Setting;
use App\Domains\Website\Actions\ComposeHomepageTrustAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function writeTrust(string $key, mixed $value): void
{
    Setting::set($key, $value);
    Cache::flush();
}

it('computes years from founded_year and students from unified Student rows', function () {
    $this->travelTo('2026-08-27 12:00:00');
    writeTrust('trust.years_operating', '');
    writeTrust('trust.founded_year', '2020');
    writeTrust('trust.students_taught', '');
    writeTrust('trust.students_min_display', '2');
    writeTrust('trust.accreditation', ['en' => '', 'dv' => '', 'ar' => '']);
    makeStudent(['first_name' => 'Trust', 'last_name' => 'One']);
    makeStudent(['first_name' => 'Trust', 'last_name' => 'Two']);

    $trust = app(ComposeHomepageTrustAction::class)->execute('en');

    expect($trust['years_operating'])->toBe(6)
        ->and($trust['students_taught'])->toBe(2)
        ->and($trust['students_source'])->toBe('computed')
        ->and($trust['accreditation'])->toBeNull()
        ->and($trust['has_signals'])->toBeTrue();
});

it('holds back a small computed student count until the display floor', function () {
    writeTrust('trust.students_taught', '');
    writeTrust('trust.students_min_display', '');
    makeStudent(['first_name' => 'Floor', 'last_name' => 'One']);
    makeStudent(['first_name' => 'Floor', 'last_name' => 'Two']);

    // Below the default floor the computed number is omitted, not shown small.
    $trust = app(ComposeHomepageTrustAction::class)->execute('en');
    expect(ComposeHomepageTrustAction::DEFAULT_STUDENTS_MIN_DISPLAY)->toBeGreaterThan(2)
        ->and($trust['students_taught'])->toBeNull()
        ->and($trust['students_source'])->toBeNull();

    // A manual override is the operator's explicit choice and always shows.
    writeTrust('trust.students_taught', '9');
    $manual = app(ComposeHomepageTrustAction::class)->execute('en');
    expect($manual['students_taught'])->toBe(9)
        ->and($manual['students_source'])->toBe('manual');
});

it('uses manual years and students overrides instead of computing', function () {
    $this->travelTo('2026-08-27 12:00:00');
    writeTrust('trust.founded_year', '2020');
    writeTrust('trust.years_operating', '12');
    writeTrust('trust.students_taught', '1500');
    makeStudent();

    $trust = app(ComposeHomepageTrustAction::class)->execute('en');

    expect($trust['years_operating'])->toBe(12)
        ->and($trust['students_taught'])->toBe(1500)
        ->and($trust['students_source'])->toBe('manual');
});

it('picks the locale accreditation line and falls back to English', function () {
    writeTrust('trust.accreditation', [
        'en' => 'Ministry of Education Reg. MOE-EN-1',
        'dv' => 'މުވައްޒަފު MOE-DV-1',
        'ar' => 'تسجيل AR-1',
    ]);

    expect(app(ComposeHomepageTrustAction::class)->execute('dv')['accreditation'])->toBe('މުވައްޒަފު MOE-DV-1')
        ->and(app(ComposeHomepageTrustAction::class)->execute('ar')['accreditation'])->toBe('تسجيل AR-1')
        ->and(app(ComposeHomepageTrustAction::class)->execute('en')['accreditation'])->toBe('Ministry of Education Reg. MOE-EN-1');

    writeTrust('trust.accreditation', ['en' => 'Ministry of Education Reg. MOE-EN-1', 'dv' => '', 'ar' => '']);

    expect(app(ComposeHomepageTrustAction::class)->execute('dv')['accreditation'])->toBe('Ministry of Education Reg. MOE-EN-1');
});

it('omits empty trust signals and does not invent 500 students or 5+ years', function () {
    writeTrust('trust.founded_year', '');
    writeTrust('trust.years_operating', '');
    writeTrust('trust.students_taught', '');
    writeTrust('trust.accreditation', ['en' => '', 'dv' => '', 'ar' => '']);
    writeTrust('trust.partner_logo_ids', []);

    $trust = app(ComposeHomepageTrustAction::class)->execute('en');
    expect($trust['has_signals'])->toBeFalse()
        ->and($trust['years_operating'])->toBeNull()
        ->and($trust['students_taught'])->toBeNull()
        ->and($trust['logos'])->toBe([]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertDontSee('id="hero-trust"', false)
        ->assertDontSee('5+', false)
        ->assertDontSee('Years of service', false)
        ->assertDontSee('Students enrolled', false);
});

it('renders accreditation, counters, and public partner logos on the homepage hero', function () {
    $this->travelTo('2026-08-27 12:00:00');
    Storage::fake('public');
    Storage::fake('local');
    Queue::fake();

    $logo = app(StorePublicMediaAction::class)->execute(
        UploadedFile::fake()->image('moe.png', 48, 24),
        null,
        [],
        ['alt' => 'W12 Partner Ministry'],
    );
    $private = app(StorePrivateMediaAction::class)->execute(UploadedFile::fake()->image('secret.jpg', 10, 10));

    writeTrust('trust.founded_year', '2020');
    writeTrust('trust.years_operating', '');
    writeTrust('trust.students_taught', '1500');
    writeTrust('trust.accreditation', [
        'en' => 'Registered with the Ministry of Education, Reg. MOE/W12',
        'dv' => '',
        'ar' => '',
    ]);
    writeTrust('trust.partner_logo_ids', [$logo['id'], $private['id']]);

    $html = $this->withoutLocalizationMiddleware()
        ->get(route('public.home'))
        ->assertOk()
        ->assertSee('id="hero-trust"', false)
        ->assertDontSee('data-years=', false)
        ->assertSee('data-students="1500"', false)
        ->assertSee('data-students-source="manual"', false)
        ->assertSee('Registered with the Ministry of Education, Reg. MOE/W12', false)
        ->assertDontSee('Years operating', false)
        ->assertSee('Students taught', false)
        ->assertSee('W12 Partner Ministry', false)
        ->assertSee($logo['url'], false)
        ->getContent();

    expect($html)->not->toContain('secret.jpg');
});

it('does not import other-domain models from new W1.2 files', function () {
    $files = [
        app_path('Domains/Website/Actions/ComposeHomepageTrustAction.php'),
        app_path('Domains/Media/Actions/ListPublicMediaFilesAction.php'),
        app_path('Domains/Media/Actions/StorePublicMediaAction.php'),
        app_path('Domains/People/Actions/CountStudentsAction.php'),
    ];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        expect($src)->not->toContain('App\\Domains\\Hifz\\');
    }

    $website = file_get_contents(app_path('Domains/Website/Actions/ComposeHomepageTrustAction.php'));
    expect($website)->not->toMatch('/App\\\\Domains\\\\(Media|People|Settings)\\\\Models\\\\/')
        ->and($website)->toContain('App\\Domains\\Settings\\Actions\\GetSettingAction')
        ->and($website)->toContain('App\\Domains\\Media\\Actions\\ListPublicMediaFilesAction')
        ->and($website)->toContain('App\\Domains\\People\\Actions\\CountStudentsAction');
});
