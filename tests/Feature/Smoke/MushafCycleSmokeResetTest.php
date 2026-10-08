<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/course-screens-language.mjs` uploads a `SMOKE-Mushaf-…`
 * through the Dhivehi form each run (STATUS §5ot). The seeder takes them
 * away with their pages, and leaves a mushaf the office uploaded alone — and
 * still runs twice.
 */
it('takes away the mushafs a walk uploaded, and leaves the office’s own', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);

    $walked = DB::table('quran_mushafs')->insertGetId([
        'name' => 'SMOKE-Mushaf-1700000000000', 'source_type' => 'manual', 'page_count' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('quran_pages')->insert([
        ['quran_mushaf_id' => $walked, 'page_number' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['quran_mushaf_id' => $walked, 'page_number' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $office = DB::table('quran_mushafs')->insertGetId([
        'name' => 'Madinah mushaf', 'source_type' => 'manual', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('quran_mushafs')->where('id', $walked)->exists())->toBeFalse()
        ->and(DB::table('quran_pages')->where('quran_mushaf_id', $walked)->exists())->toBeFalse()
        ->and(DB::table('quran_mushafs')->where('id', $office)->exists())->toBeTrue();
});
