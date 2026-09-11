<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A fresh install can record the new-memorization lane.
 *
 * `DatabaseSeeder` did not run `SurahSeeder`, so `surahs` was empty and the
 * halaqa sheet's surah pickers rendered with **no options** — the lane could not
 * be filled in at all until somebody found the seeder and ran it by hand. The
 * empty dropdown was found by walking the F5 gate, where it first read as a
 * broken screen rather than a missing seed.
 *
 * This pins the seeding, not the dataset: 14 surahs is the development subset,
 * and the authoritative Qur'an data arrives through the mushaf import.
 */
it('seeds surahs so the memorization pickers have options', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(DB::table('surahs')->count())->toBeGreaterThan(0)
        ->and(DB::table('surahs')->where('index', 1)->value('english_name'))->toBe('Al-Fatihah');
});
