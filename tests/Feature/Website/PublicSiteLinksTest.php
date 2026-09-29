<?php

use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\EventRegistration;
use App\Domains\Website\Models\GalleryAlbum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * STATUS §5ku: the events and gallery pages built their links as
 * `route('x.show', [locale, id])`. The language is already a URL default,
 * so that became `/en/events/en?5` — the events list and the gallery sent
 * every visitor to an error page, and nobody could register for an event.
 * The events page also turned every error into a 500 that printed the
 * exception, and the home page, the search and the .ics file linked events
 * by a slug the route could not read.
 */
function publicEvent(array $overrides = []): Event
{
    return app(SaveEventAction::class)->execute(array_merge([
        'title' => 'Quran Evening', 'location' => 'Malé', 'status' => 'published', 'is_public' => true,
        'registration_type' => 'required', 'max_attendees' => 50,
        'start_date' => now()->addDays(3)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(3)->addHours(2)->format('Y-m-d H:i:s'),
    ], $overrides));
}

function publicPage(string $url): TestResponse
{
    return test()->withoutLocalizationMiddleware()->get($url);
}

it('links each event on the events page to its own page, which opens', function () {
    $event = publicEvent();

    $html = publicPage(route('public.events.index'))->assertOk()->getContent();

    $href = route('public.events.show', $event->slug);
    expect($html)->toContain('href="'.$href.'"')
        ->and($html)->not->toContain(route('public.events.show', 'en'));
    publicPage($href)->assertOk()->assertSee('Quran Evening');
});

it('opens an event by its slug or its id, and 404s — not 500s — one that is missing, a draft or private', function () {
    $event = publicEvent();
    $draft = publicEvent(['title' => 'Draft Evening', 'status' => 'draft']);
    $private = publicEvent(['title' => 'Staff Evening', 'is_public' => false]);

    publicPage(route('public.events.show', $event->slug))->assertOk();
    publicPage(route('public.events.show', $event->id))->assertOk();
    publicPage(route('public.events.show', 'no-such-event'))->assertNotFound()->assertDontSee('No query results');
    publicPage(route('public.events.show', 999999))->assertNotFound();
    publicPage(route('public.events.show', $draft->slug))->assertNotFound();
    publicPage(route('public.events.show', $private->id))->assertNotFound();
});

it('lets a visitor register from the event page\'s form', function () {
    $event = publicEvent();

    $html = publicPage(route('public.events.show', $event->slug))->assertOk()->getContent();
    preg_match('#<form method="POST" action="([^"]+)"#', $html, $form);
    expect($form[1])->toBe(route('public.events.register', $event->slug));

    test()->withoutLocalizationMiddleware()->post($form[1], ['name' => 'Aminath Visitor', 'email' => 'aminath@example.com'])
        ->assertRedirect(route('public.events.show', $event))
        ->assertSessionHasNoErrors();
    expect(EventRegistration::query()->where('event_id', $event->id)->where('email', 'aminath@example.com')->exists())->toBeTrue();
});

it('serves the calendar file at the address the event page links to', function () {
    $event = publicEvent();

    publicPage(route('public.events.calendar', $event->slug))->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee(route('public.events.show', $event->slug), false);
});

it('links each album on the gallery page to its own page, which opens', function () {
    $album = GalleryAlbum::query()->create(['title' => 'Graduation Day', 'slug' => 'graduation-day', 'status' => 'published', 'is_public' => true, 'type' => 'photos']);

    $html = publicPage(route('public.gallery.index'))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('public.gallery.show', $album->id).'"')
        ->and($html)->not->toContain(route('public.gallery.show', 'en'));
    publicPage(route('public.gallery.show', $album->id))->assertOk()->assertSee('Graduation Day')
        ->assertSee('href="'.route('public.gallery.index').'"', false);
});

it('points the contact and admissions forms at their own addresses', function () {
    expect(publicPage(route('public.contact.create'))->assertOk()->getContent())
        ->toContain('action="'.route('public.contact.store').'"')
        ->not->toContain(route('public.contact.store').'?');
    expect(publicPage(route('public.admissions.create'))->assertOk()->getContent())
        ->toContain('action="'.route('public.admissions.store').'"');
});
