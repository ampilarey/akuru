<?php

namespace Tests\Feature\Hifz;

use App\Domains\Hifz\Models\QuranMushaf;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuranMushafAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    public function test_dean_can_lock_mushaf(): void
    {
        $dean = User::where('email', 'headmaster@akuru.edu.mv')->first();
        $mushaf = QuranMushaf::create(['name' => 'Test Mushaf', 'source_type' => 'manual']);

        $this->actingAs($dean)->post(route('hifz.quran.mushafs.lock', $mushaf));

        $this->assertTrue($mushaf->fresh()->locked);
    }

    public function test_teacher_cannot_lock_mushaf(): void
    {
        $teacher = User::where('email', 'teacher@akuru.edu.mv')->first();
        $mushaf = QuranMushaf::create(['name' => 'Test Mushaf', 'source_type' => 'manual']);

        $this->actingAs($teacher)->post(route('hifz.quran.mushafs.lock', $mushaf));

        $response = $this->actingAs($teacher)->post(route('hifz.quran.mushafs.lock', $mushaf));
        $response->assertForbidden();
    }
}
