<?php

use App\Domains\Website\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The public site's events, news, about, careers, achievements, gallery,
 * search and CMS pages in Dhivehi and Arabic (BACKLOG C20, slice LT5b,
 * STATUS §5pi).
 *
 * The about page was English from top to bottom (its mission, vision,
 * values and the four counters), achievements and careers' closing dates
 * too, an event's *Add to Calendar*, every date on these pages had English
 * month names, a search result printed a course's status as a code, and
 * registering for an event was refused in English — eleven refusals of
 * `RegisterForEventAction`, which the parent portal's event page shares.
 */
uses(RefreshDatabase::class);

function sitePageViews(): array
{
    return [
        'public/events/index.blade.php',
        'public/events/show.blade.php',
        'public/news/index.blade.php',
        'public/news/show.blade.php',
        'public/gallery/index.blade.php',
        'public/gallery/show.blade.php',
        'public/about/index.blade.php',
        'public/careers/index.blade.php',
        'public/achievements/index.blade.php',
        'public/page/show.blade.php',
        'public/search.blade.php',
    ];
}

function sitePageServerFiles(): array
{
    return [
        'app/Domains/Website/Http/Controllers/PublicSite/EventController.php',
        'app/Domains/Website/Actions/RegisterForEventAction.php',
    ];
}

function sitePageBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/public.php");
}

function lt5bEvent(array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'LT5b Open Day', 'slug' => 'lt5b-open-day-'.uniqueFixtureSuffix(), 'description' => 'LT5b',
        'location' => 'Malé', 'status' => 'published', 'is_public' => true, 'registration_type' => 'required',
        'start_date' => now()->addMonths(2)->setDay(3), 'end_date' => now()->addMonths(2)->setDay(3),
    ], $overrides));
}

it('prints no English of its own on these pages', function () {
    $found = [];
    foreach (sitePageViews() as $view) {
        $path = resource_path('views/'.$view);
        // Brands are their own names; the CMS page's fallbacks are the app's name.
        foreach ([...bladeBareEnglish($path, ['Facebook', 'Twitter', 'Viber', 'WhatsApp', 'MVR']), ...bladeEnglishLiterals($path, ['Akuru Institute'])] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('says every phrase of these pages in Dhivehi and Arabic', function () {
    $en = sitePageBook('en');
    $gaps = [];
    foreach ([...array_map(fn ($view) => 'resources/views/'.$view, sitePageViews()), ...sitePageServerFiles()] as $file) {
        preg_match_all("/(?:__|trans_choice)\\(\\s*'public\\.((?:[^'\\\\]|\\\\.)+)'/", file_get_contents(base_path($file)), $matches);
        foreach (array_unique(array_map('stripslashes', $matches[1])) as $key) {
            foreach (['dv', 'ar'] as $locale) {
                $book = sitePageBook($locale);
                if (! array_key_exists($key, $en) || ! array_key_exists($key, $book) || $book[$key] === $en[$key]) {
                    $gaps[] = "{$locale} {$key} ({$file})";
                }
            }
        }
    }

    expect($gaps)->toBe([]);
});

it('leaves no English refusal in the event registration', function () {
    $found = [];
    foreach (sitePageServerFiles() as $file) {
        foreach (refusalEnglishIn($file) as $line) {
            // The `public` book is keyed by its English, so a key reads as a sentence.
            if (! preg_match('/:\d+ public\./', $line)) {
                $found[] = $line;
            }
        }
    }

    expect($found)->toBe([]);
});

it('serves the about page, achievements and careers in Dhivehi and Arabic', function (string $locale, string $route, string $said) {
    app()->setLocale($locale);

    $html = $this->withoutLocalizationMiddleware()->get(route($route))->assertOk()->getContent();

    expect($html)->toContain(e(sitePageBook($locale)[$said]));
    foreach (['About Akuru Institute', 'Our Mission', 'Students enrolled', 'Join Our Community', 'Achievements', 'No published school awards yet.'] as $english) {
        expect($html)->not->toContain('>'.e($english).'<');
    }
})->with([
    ['dv', 'public.about', 'About Akuru Institute'],
    ['ar', 'public.about', 'Our Mission'],
    ['dv', 'public.achievements', 'No published school awards yet.'],
    ['ar', 'public.careers', 'Careers'],
]);

it('writes an event in the page\'s language, its date and its calendar button', function () {
    $event = lt5bEvent(['start_date' => now()->addYear()->setMonth(12)->setDay(3), 'end_date' => now()->addYear()->setMonth(12)->setDay(3)]);
    app()->setLocale('dv');

    $html = $this->withoutLocalizationMiddleware()->get(route('public.events.show', $event->slug))->assertOk()->getContent();

    expect($html)->toContain(e(sitePageBook('dv')['Add to Calendar']))
        ->toContain('ޑިސެންބަރު')
        ->not->toContain('Download .ics')
        ->not->toContain('December');
});

it('refuses a closed registration in the page\'s language', function () {
    $event = lt5bEvent(['registration_deadline' => now()->subDay()]);

    $this->withHeader('Referer', url('/dv/events/'.$event->slug))
        ->post(route('public.events.register', $event), ['name' => 'LT5b Guest', 'email' => 'lt5b@example.test'])
        ->assertRedirect()
        ->assertSessionHasErrors(['event_id' => sitePageBook('dv')['Registration has closed.']]);
});

it('registers in the page\'s language, and refuses the same email twice', function () {
    $event = lt5bEvent();
    $form = ['name' => 'LT5b Guest', 'email' => 'lt5b-twice@example.test'];

    $this->withHeader('Referer', url('/ar/events/'.$event->slug))
        ->post(route('public.events.register', $event), $form)
        ->assertSessionHas('success', sitePageBook('ar')['Registration submitted successfully!']);

    $this->withHeader('Referer', url('/ar/events/'.$event->slug))
        ->post(route('public.events.register', $event), $form)
        ->assertSessionHasErrors(['email' => sitePageBook('ar')['You are already registered for this event.']]);
});

it('says the registration refusals in English as before', function () {
    $event = lt5bEvent(['registration_deadline' => now()->subDay()]);

    $this->post(route('public.events.register', $event), ['name' => 'LT5b Guest', 'email' => 'lt5b-en@example.test'])
        ->assertSessionHasErrors(['event_id' => 'Registration has closed.']);
});
