<?php

use App\Domains\Website\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The public site's front door in Dhivehi and Arabic (BACKLOG C20, slice LT4,
 * STATUS §5pg).
 *
 * A visitor on `/dv` or `/ar` read English in the header (Translate, Log out,
 * Login, Signed in as), on the home page (its badge, the Viber button, the
 * gallery, testimonials, news and events headings, the daily strip, the hero
 * slides the office has not replaced, English month names), on both error
 * pages, on the quick apply page (all of it), the admissions and thank-you
 * pages and the contact page: 52 of the phrase book's keys had no Dhivehi or
 * Arabic, and about 60 more strings were typed into the views.
 */
uses(RefreshDatabase::class);

/** The front door: the shell every public page wears, and the pages of this slice. */
function frontDoorViews(): array
{
    return [
        'components/public/nav.blade.php',
        'components/public/footer.blade.php',
        'public/layouts/public.blade.php',
        'public/partials/prayer-banner.blade.php',
        'public/partials/prayer-banner-assets.blade.php',
        'errors/404.blade.php',
        'errors/500.blade.php',
        'public/home.blade.php',
        'public/home/_daily.blade.php',
        'public/home/_trust.blade.php',
        'public/daily/_card.blade.php',
        'public/admissions/create.blade.php',
        'public/admissions/apply.blade.php',
        'public/admissions/thanks.blade.php',
        'public/admissions/_custom-fields.blade.php',
        'public/contact/create.blade.php',
    ];
}

function frontDoorServerFiles(): array
{
    return [
        'app/Domains/Website/Http/Controllers/PublicSite/HomeController.php',
        'app/Domains/Website/Http/Controllers/PublicSite/AdmissionController.php',
        'app/Domains/Website/Http/Controllers/PublicSite/ContactController.php',
    ];
}

/**
 * What stays as written: brand names, the currency code, the Translate menu's
 * own name for English beside العربية and ދިވެހި, sample input, and the island
 * the prayer strip starts on until the visitor's island loads (the islands'
 * names come from the prayer-times data, which is English — BACKLOG C20).
 */
function frontDoorAllowed(): array
{
    return ['Viber', 'MVR', '🇬🇧 English', 'K. Malé', 'Malé', '7xxxxxxx', '7XXXXXX'];
}

function frontDoorBook(string $book, string $locale): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

/** Every `__('book.key')` and `trans_choice('book.key')` the files name, by book. */
function frontDoorKeys(array $files): array
{
    $keys = [];
    foreach ($files as $file) {
        preg_match_all("/(?:__|trans_choice)\\(\\s*'(public|site|nav)\\.((?:[^'\\\\]|\\\\.)+)'/", file_get_contents(base_path($file)), $matches, PREG_SET_ORDER);
        foreach ($matches as [, $book, $key]) {
            $keys[$book][stripslashes($key)] = $file;
        }
    }

    return $keys;
}

it('prints no English of its own on the front door', function () {
    $found = [];
    foreach (frontDoorViews() as $view) {
        foreach (bladeBareEnglish(resource_path('views/'.$view), frontDoorAllowed()) as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('writes no English sentence from the front door\'s PHP', function () {
    // The layout's fallbacks are the app's name and the search engines' keywords.
    $allowed = ['Akuru Institute', 'Quran, Arabic, Islamic Studies, Education, Maldives, Akuru Institute'];
    $found = [];
    foreach (frontDoorViews() as $view) {
        foreach (bladeEnglishLiterals(resource_path('views/'.$view), $allowed) as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('says every phrase of the front door in Dhivehi and Arabic', function () {
    $files = array_merge(array_map(fn ($view) => 'resources/views/'.$view, frontDoorViews()), frontDoorServerFiles());
    $gaps = [];
    foreach (frontDoorKeys($files) as $book => $keys) {
        $en = frontDoorBook($book, 'en');
        foreach (['dv', 'ar'] as $locale) {
            $translated = frontDoorBook($book, $locale);
            foreach ($keys as $key => $file) {
                if (str_ends_with($key, '_')) {
                    continue; // a prefix, completed below
                }
                if (! array_key_exists($key, $en)) {
                    $gaps[] = "en {$book}.{$key} ({$file})";
                } elseif (! array_key_exists($key, $translated) || $translated[$key] === $en[$key]) {
                    $gaps[] = "{$locale} {$book}.{$key} ({$file})";
                }
            }
        }
    }

    expect($gaps)->toBe([]);
});

it('names the daily strip\'s kinds in Dhivehi and Arabic', function () {
    foreach (['ayah', 'hadith', 'saying', 'reminder'] as $type) {
        foreach (['dv', 'ar'] as $locale) {
            expect(frontDoorBook('public', $locale))->toHaveKey('daily_type_'.$type)
                ->and(frontDoorBook('public', $locale)['daily_type_'.$type])->not->toBe(frontDoorBook('public', 'en')['daily_type_'.$type]);
        }
    }
});

it('serves the front door in Dhivehi and Arabic, with no English on it', function (string $locale, string $route, string $said) {
    app()->setLocale($locale);

    $html = $this->withoutLocalizationMiddleware()->get(route($route))->assertOk()->getContent();

    expect($html)->toContain(e(frontDoorBook('public', $locale)[$said]));
    foreach (['Translate', 'Log out', 'Islamic Education in the Maldives', 'Chat on Viber', 'Latest News', 'Upcoming Events',
        'Apply to Akuru Institute', 'Your Details', 'What happens next?', 'Office Hours', '8:00 AM', 'Thank You!', 'Admission Process'] as $english) {
        expect($html)->not->toContain('>'.e($english).'<')->not->toContain('> '.e($english).' <');
    }
})->with([
    ['dv', 'public.home', 'Islamic Education in the Maldives'],
    ['ar', 'public.home', 'Chat on Viber'],
    ['dv', 'public.apply', 'Apply to Akuru Institute'],
    ['ar', 'public.apply', 'Our admissions team is happy to help.'],
    ['dv', 'public.admissions.create', 'Admission Process'],
    ['ar', 'public.admissions.thanks', 'admission_submitted_successfully'],
    ['dv', 'public.contact.create', '8:00 AM - 4:00 PM'],
    ['ar', 'public.contact.create', 'Office Hours'],
]);

it('writes the home page\'s dates in the page\'s language', function () {
    app()->setLocale('dv');
    Event::create([
        'title' => 'LT4 Open Day', 'slug' => 'lt4-open-day', 'location' => 'Malé', 'description' => 'LT4',
        'start_date' => now()->addYear()->setMonth(12)->setDay(3), 'end_date' => now()->addYear()->setMonth(12)->setDay(3),
        'status' => 'published', 'is_public' => true,
    ]);

    $html = $this->withoutLocalizationMiddleware()->get(route('public.home'))->assertOk()->getContent();

    expect($html)->toContain('LT4 Open Day')->toContain('ޑިސެންބަރު')->not->toContain('>Dec<');
});

it('says the error pages in the page\'s language', function (string $locale, string $view, string $said) {
    app()->setLocale($locale);

    $html = view($view)->render();

    expect($html)->toContain(e(frontDoorBook('public', $locale)[$said]))
        ->not->toContain('?'.$locale.'"');
})->with([
    ['dv', 'errors.404', 'Page Not Found'],
    ['ar', 'errors.404', 'Popular Pages'],
    ['dv', 'errors.500', 'Something went wrong on our end. Please try again later.'],
    ['ar', 'errors.500', 'Get Help'],
]);

it('thanks an applicant in their language, at an address without the language tacked on', function () {
    $response = $this->withHeader('Referer', url('/dv/admissions'))
        ->post(route('public.admissions.store'), ['full_name' => 'LT4 Applicant', 'phone' => '7771234', 'source' => 'web'])
        ->assertRedirect()
        ->assertSessionHas('success', frontDoorBook('public', 'dv')['admission_submitted']);

    expect($response->headers->get('Location'))->toContain('thanks')->not->toContain('?');
});
