<?php

namespace Tests\Feature\Hifz;

use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\People\Models\Student;
use App\Models\User;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_teacher_can_access_assigned_hifz_session(): void
    {
        $teacher = $this->verifiedUser('teacher@akuru.edu.mv');
        $session = HifzSession::first();

        $response = $this->actingAs($teacher)->followingRedirects()->get(route('hifz.sessions.edit', $session));

        $response->assertOk();
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

    public function test_parent_can_view_own_child_history(): void
    {
        $parent = $this->verifiedUser('parent@akuru.edu.mv');
        $student = Student::first();

        $response = $this->actingAs($parent)->followingRedirects()->get(route('hifz.students.history', $student));

        $response->assertOk();
    }

    public function test_parent_cannot_view_other_student(): void
    {
        $parent = $this->verifiedUser('parent@akuru.edu.mv');
        $school = \App\Models\School::first();
        $class = \App\Domains\Academics\Models\ClassRoom::first();
        $otherStudent = Student::create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
            'student_id' => 'S-OTHER-001',
            'first_name' => 'Other',
            'last_name' => 'Student',
            'date_of_birth' => '2011-01-01',
            'gender' => 'male',
            'admission_date' => now(),
            'status' => 'active',
        ]);

        $response = $this->withoutLocalizationMiddleware()
            ->actingAs($parent)
            ->get(route('hifz.students.history', $otherStudent));

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

    public function test_supervisor_can_mark_session_reviewed(): void
    {
        $supervisor = $this->verifiedUser('supervisor@akuru.edu.mv');
        $session = HifzSession::first();

        $response = $this->actingAs($supervisor)->post(route('hifz.sessions.review', $session));

        $response->assertRedirect();
        $this->assertEquals('reviewed', $session->fresh()->status->value);
    }
}
