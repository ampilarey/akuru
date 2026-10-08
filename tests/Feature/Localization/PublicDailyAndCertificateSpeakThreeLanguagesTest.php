<?php

use App\Domains\Courses\Actions\VerifyIssuedCertificateAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The daily reminders and the certificate check in Dhivehi and Arabic
 * (BACKLOG C20, slice LT5c, STATUS §5pj).
 *
 * The daily archive, a day's page, the subscription page and the
 * unsubscribed page were English throughout, a kind of content was printed
 * as a code (`ayah`, `Ayah archive`), the subscription's saved, paused and
 * resumed messages were English, and so were a day's headline and
 * description — which its share links and Open Graph carry. The
 * certificate check, on its stable unlocalized address, was English for
 * every scanner.
 */
uses(RefreshDatabase::class);

function dailyPageViews(): array
{
    return [
        'public/daily/index.blade.php',
        'public/daily/show.blade.php',
        'public/daily/subscribe.blade.php',
        'public/daily/unsubscribed.blade.php',
        'public/daily/_card.blade.php',
        'public/certificates/verify.blade.php',
    ];
}

function dailyServerFiles(): array
{
    return [
        'app/Domains/Website/Http/Controllers/PublicSite/DailySubscriptionController.php',
        'app/Domains/Website/Actions/ComposeDailyContentSeoAction.php',
        'app/Domains/Courses/Http/Controllers/PublicCertificateVerifyController.php',
    ];
}

function dailyBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/public.php");
}

function lt5cCertificateFace(): array
{
    return [
        'revoked' => false, 'certificate_number' => 'AK-LT5C-0001', 'student_name' => 'LT5c Student',
        'course_name' => 'LT5c Tajweed', 'offering_name' => '', 'completion_date' => '2026-10-01',
        'grade' => 'A', 'institute' => 'Akuru Institute',
    ];
}

it('prints no English of its own on these pages', function () {
    $found = [];
    foreach (dailyPageViews() as $view) {
        $path = resource_path('views/'.$view);
        // Brands, SMS, and English named in itself beside ދިވެހި.
        foreach ([...bladeBareEnglish($path, ['WhatsApp', 'Twitter', 'SMS', 'English']), ...bladeEnglishLiterals($path, ['Akuru Institute'])] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('says every phrase of these pages in Dhivehi and Arabic, each kind of content too', function () {
    $en = dailyBook('en');
    $keys = [];
    foreach ([...array_map(fn ($view) => 'resources/views/'.$view, dailyPageViews()), ...dailyServerFiles()] as $file) {
        preg_match_all("/(?:__|trans_choice)\\(\\s*'public\\.((?:[^'\\\\]|\\\\.)+)'/", file_get_contents(base_path($file)), $matches);
        $keys = [...$keys, ...array_map('stripslashes', $matches[1])];
    }
    // The saying's and the reminder's headlines are chosen before they are said.
    $keys = [...$keys, 'Daily saying · :date', 'Daily reminder · :date'];
    foreach (['ayah', 'hadith', 'saying', 'reminder'] as $type) {
        foreach (['daily_type_', 'daily_title_', 'daily_archive_', 'daily_description_', 'daily_empty_'] as $prefix) {
            $keys[] = $prefix.$type;
        }
    }

    $gaps = [];
    foreach (array_unique($keys) as $key) {
        if (str_ends_with($key, '_')) {
            continue;
        }
        foreach (['dv', 'ar'] as $locale) {
            $book = dailyBook($locale);
            if (! array_key_exists($key, $en) || ! array_key_exists($key, $book) || $book[$key] === $en[$key]) {
                $gaps[] = "{$locale} {$key}";
            }
        }
    }

    expect($gaps)->toBe([]);
});

it('serves the archive, a day and the subscription page in Dhivehi and Arabic', function (string $locale) {
    $this->travelTo('2026-08-27 12:00:00');
    w23PublishedAyah('2026-08-27');
    app()->setLocale($locale);
    $book = dailyBook($locale);

    $archive = $this->withoutLocalizationMiddleware()->get(route('public.daily.index', ['type' => 'ayah']))->assertOk()->getContent();
    $day = $this->withoutLocalizationMiddleware()->get(route('public.daily.show', ['type' => 'ayah', 'date' => '2026-08-27']))->assertOk()->getContent();
    $subscribe = $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get(route('public.daily.subscribe'))->assertOk()->getContent();

    expect($archive)->toContain(e($book['daily_archive_ayah']))->not->toContain('Ayah archive')
        ->and($day)->toContain(e($book['Back to archive']))->not->toContain('Daily ayah ·')
        ->and($subscribe)->toContain(e($book['Save subscription']))->not->toContain('Opt-in only.');
})->with(['dv', 'ar']);

it('answers a subscription in the page\'s language', function () {
    $this->actingAs(User::factory()->create(['email' => 'lt5c@example.test']))
        ->withHeader('Referer', url('/dv/daily/subscribe'))
        ->post(route('public.daily.subscribe.store'), ['channel' => 'email', 'content_types' => ['ayah'], 'language' => 'dv', 'send_time' => '06:00'])
        ->assertRedirect()
        ->assertSessionHas('success', dailyBook('dv')['Subscription saved. You will only receive messages you opted into.']);
});

it('checks a certificate in the scanning browser\'s language, English by default', function (?string $header, string $said) {
    $this->mock(VerifyIssuedCertificateAction::class, fn ($mock) => $mock->shouldReceive('execute')->andReturn(lt5cCertificateFace()));

    $request = $header === null ? $this : $this->withHeader('Accept-Language', $header);
    $html = $request->get(route('public.certificates.verify', 'lt5c-public-id'))->assertOk()->getContent();

    expect($html)->toContain($said)->toContain('AK-LT5C-0001');
})->with([
    'no language' => [null, 'This certificate is authentic.'],
    'Dhivehi' => ['dv-MV,dv;q=0.9,en;q=0.5', 'މި ސެޓްފިކެޓަކީ ޞައްޙަ ސެޓްފިކެޓެއް.'],
    'Arabic' => ['ar,en;q=0.5', 'هذه الشهادة أصلية.'],
    'a language we do not speak' => ['fr-FR,fr', 'This certificate is authentic.'],
]);

it('writes the certificate check right to left in Dhivehi and Arabic', function () {
    $this->mock(VerifyIssuedCertificateAction::class, fn ($mock) => $mock->shouldReceive('execute')->andReturn(lt5cCertificateFace()));

    $html = $this->withHeader('Accept-Language', 'dv')->get(route('public.certificates.verify', 'lt5c-public-id'))->getContent();

    expect($html)->toContain('<html lang="dv" dir="rtl">')->toContain(e(dailyBook('dv')['Akuru Institute']));
});
