<?php

use App\Domains\Courses\Components\Arabic\Actions\ListArabicReferenceAction;
use App\Domains\Courses\Components\Arabic\Actions\SaveArabicLetterAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('seeds arabic letters and harakas and lists them through an action', function () {
    $payload = app(ListArabicReferenceAction::class)->execute();

    expect($payload['letters'])->toHaveCount(28)
        ->and($payload['harakas'])->toHaveCount(4)
        ->and($payload['letters']->first()['key_name'])->toBe('alif')
        ->and($payload['letters']->first()['arabic_character'])->toBe('ا')
        ->and($payload['harakas']->pluck('key_name')->all())->toBe(['fatha', 'kasra', 'damma', 'sukoon']);
});

it('lets catalog staff manage letters and export csv', function () {
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.arabic.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/ArabicReference')
            ->has('letters', 28)
            ->has('harakas', 4)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.arabic.letters.store'), [
            'key_name' => 'hamza',
            'arabic_character' => 'ء',
            'display_name' => 'Hamza',
            'sort_order' => 29,
        ])
        ->assertRedirect(route('catalog.arabic.index'));

    expect(ArabicLetter::query()->where('key_name', 'hamza')->exists())->toBeTrue();

    $letter = ArabicLetter::query()->where('key_name', 'alif')->firstOrFail();
    app(SaveArabicLetterAction::class)->execute([
        'key_name' => 'alif',
        'arabic_character' => 'ا',
        'display_name' => 'Alif (updated)',
        'sort_order' => 1,
        'is_active' => true,
    ], $letter);
    expect($letter->fresh()->display_name)->toBe('Alif (updated)');

    expect(ArabicHarakah::query()->count())->toBe(4);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.arabic.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $other = actingPeopleAdmin(['hr.manage']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.arabic.index'))
        ->assertForbidden();
});
