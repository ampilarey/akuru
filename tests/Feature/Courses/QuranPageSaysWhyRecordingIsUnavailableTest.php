<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * On a host with no surah reference the Qur'an page simply left the recorder
 * out, so a student saw no way to record and no reason why — found on
 * staging, where `SurahSeeder` had never run (STATUS §5fz). The page now says
 * so, in the student's language.
 */
it('tells the student why recording is unavailable when the host has no surahs', function () {
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.quran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Quran')
            ->has('surahs', 0));

    $source = file_get_contents(resource_path('js/Pages/Courses/Learn/Quran.jsx'));
    expect($source)->toContain('surahs.length === 0')
        ->and($source)->toContain('t.quran_no_surahs');

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(trans('learn.quran_no_surahs', [], $locale))->not->toBe('learn.quran_no_surahs')->not->toBe('');
    }
});
