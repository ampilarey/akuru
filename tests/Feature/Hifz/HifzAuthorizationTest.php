<?php

namespace Tests\Feature\Hifz;

use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Identity\Models\User;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What survives F5: program scoping. The session-edit, session-review and
 * per-student-history cases moved out with the Blade screens they asserted on
 * — the engine covers the same ground in `QuranSessionRecordTest` and
 * `QuranMilestoneWorkflowTest`, against `teach.quran-sessions.*`.
 */
class HifzAuthorizationTest extends TestCase
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

    protected function verifiedUser(string $email): User
    {
        $user = User::where('email', $email)->first();
        $user->markEmailAsVerified();

        return $user;
    }

    public function test_teacher_cannot_access_unassigned_program(): void
    {
        $teacher = $this->verifiedUser('teacher@akuru.edu.mv');
        $otherProgram = HifzProgram::create([
            'name' => 'Other Program',
            'status' => 'active',
        ]);

        $response = $this->withoutLocalizationMiddleware()
            ->actingAs($teacher)
            ->get(route('hifz.programs.show', $otherProgram));

        $response->assertForbidden();
    }

    public function test_supervisor_can_view_assigned_program(): void
    {
        $supervisor = $this->verifiedUser('supervisor@akuru.edu.mv');
        $program = HifzProgram::first();

        $response = $this->actingAs($supervisor)->followingRedirects()->get(route('hifz.programs.show', $program));

        $response->assertOk();
    }

    public function test_supervisor_cannot_view_unassigned_program(): void
    {
        $supervisor = $this->verifiedUser('supervisor@akuru.edu.mv');
        $other = HifzProgram::create(['name' => 'Unassigned', 'status' => 'active']);

        $response = $this->withoutLocalizationMiddleware()
            ->actingAs($supervisor)
            ->get(route('hifz.programs.show', $other));

        $response->assertForbidden();
    }

    protected function withoutLocalizationMiddleware(): static
    {
        return $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
        ]);
    }

    public function test_dean_can_view_all_programs(): void
    {
        $dean = $this->verifiedUser('headmaster@akuru.edu.mv');

        $response = $this->actingAs($dean)->followingRedirects()->get(route('hifz.programs.index'));

        $response->assertOk();
    }
}
