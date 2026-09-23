<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/reader.mjs` has the student read the seeded `SMOKE-Primer`,
 * bookmark a page and finish it. `SmokeMarkerSeeder::readerCycle()` plants the
 * book published with three pages and clears the reader's progress, bookmarks
 * and reading events — so the walk can run twice, and the seeder must too.
 */
it('plants a published three-page book, clears a run\'s progress and bookmarks, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $item = DB::table('library_items')->where('slug', 'smoke-primer')->first();
    $userId = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');

    expect($item)->not->toBeNull()
        ->and($item->status)->toBe('published')
        ->and($item->access_type)->toBe('free_login')
        ->and((int) $item->page_count)->toBe(3)
        ->and(DB::table('library_item_pages')->where('library_item_id', $item->id)->count())->toBe(3);

    // What a run leaves behind.
    DB::table('library_reading_progress')->insert([
        'user_id' => $userId, 'library_item_id' => $item->id, 'current_page' => 3, 'progress_percent' => 100,
        'last_read_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('library_bookmarks')->insert([
        'user_id' => $userId, 'library_item_id' => $item->id, 'page_number' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    $fresh = DB::table('library_items')->where('slug', 'smoke-primer')->first();

    expect(DB::table('library_items')->where('slug', 'smoke-primer')->count())->toBe(1)
        ->and(DB::table('library_reading_progress')->where('library_item_id', $item->id)->exists())->toBeFalse()
        ->and(DB::table('library_bookmarks')->where('library_item_id', $item->id)->exists())->toBeFalse()
        ->and(DB::table('library_item_pages')->where('library_item_id', $fresh->id)->count())->toBe(3);
});
