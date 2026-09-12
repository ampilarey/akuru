<?php

namespace Tests\Feature\Hifz;

use App\Domains\Hifz\Models\HifzMistake;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzMistakeCounterService;
use App\Enums\Hifz\HifzMistakeType;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HifzMistakeCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\SchoolSeeder::class);
        $this->seed(\Database\Seeders\ClassSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->seed(\Database\Seeders\SurahSeeder::class);
        $this->seed(HifzDemoSeeder::class);
    }

    /**
     * F5 (ADR-025): this asserted through `hifz.mistakes.store`, whose only
     * caller was the Blade session editor. The editor and its route are gone —
     * the engine records mistakes through `QuranMistakeMark` /
     * `DeriveHarakaMistakeAction` — so the counter is now exercised where it
     * still runs: over the legacy `hifz_mistakes` rows the reports read.
     */
    public function test_mistake_saving_updates_haraka_counts(): void
    {
        $record = HifzSessionRecord::first();

        $mistake = HifzMistake::create([
            'hifz_session_record_id' => $record->id,
            'student_id' => $record->student_id,
            'teacher_id' => $record->teacher_id,
            'mistake_type' => HifzMistakeType::Haraka,
            'severity' => 'minor',
            'surah_number' => 1,
            'ayah_number' => 1,
            'word_number' => 1,
        ]);

        $record = app(HifzMistakeCounterService::class)->afterMistakeChange($mistake);

        $this->assertGreaterThanOrEqual(1, $record->haraka_mistakes);
        $this->assertGreaterThanOrEqual(1, $record->mistake_count);
    }

    public function test_counter_service_counts_word_mistakes(): void
    {
        $record = HifzSessionRecord::first();
        HifzMistake::create([
            'hifz_session_record_id' => $record->id,
            'student_id' => $record->student_id,
            'teacher_id' => $record->teacher_id,
            'mistake_type' => HifzMistakeType::WrongWord,
            'severity' => 'medium',
            'surah_number' => 1,
            'ayah_number' => 1,
            'word_number' => 2,
        ]);

        app(HifzMistakeCounterService::class)->syncRecord($record);
        $record->refresh();

        $this->assertEquals(1, $record->word_mistakes);
    }
}
