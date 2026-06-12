<?php

namespace Tests\Feature\Hifz;

use App\Enums\Hifz\HifzMistakeType;
use App\Domains\Hifz\Models\HifzMistake;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Identity\Models\User;
use App\Domains\Hifz\Services\HifzMistakeCounterService;
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

    public function test_mistake_saving_updates_haraka_counts(): void
    {
        $record = HifzSessionRecord::first();
        $teacher = User::where('email', 'teacher@akuru.edu.mv')->first();
        $teacher->markEmailAsVerified();

        $this->actingAs($teacher)->postJson(route('hifz.mistakes.store'), [
            'hifz_session_record_id' => $record->id,
            'mistake_type' => 'haraka',
            'severity' => 'minor',
            'surah_number' => 1,
            'ayah_number' => 1,
            'word_number' => 1,
        ])->assertOk();

        $record->refresh();
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
