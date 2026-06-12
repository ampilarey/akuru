<?php

namespace Tests\Feature\Hifz;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\People\Models\Student;
use App\Models\User;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HifzMilestoneWorkflowTest extends TestCase
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

    public function test_teacher_can_recommend_milestone(): void
    {
        $teacher = User::where('email', 'teacher@akuru.edu.mv')->first();
        $program = HifzProgram::first();
        $student = Student::first();

        $response = $this->actingAs($teacher)->post(route('hifz.milestones.store'), [
            'hifz_program_id' => $program->id,
            'student_id' => $student->id,
            'type' => 'page_completed',
            'page_number' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('hifz_milestones', [
            'student_id' => $student->id,
            'type' => 'page_completed',
            'status' => 'pending',
        ]);
    }

    public function test_supervisor_reviews_milestone(): void
    {
        $supervisor = User::where('email', 'supervisor@akuru.edu.mv')->first();
        $milestone = HifzMilestone::where('status', 'pending')->first();

        $this->actingAs($supervisor)->post(route('hifz.milestones.supervisor-review', $milestone));

        $this->assertEquals('supervisor_reviewed', $milestone->fresh()->status->value);
    }

    public function test_dean_approves_milestone(): void
    {
        $dean = User::where('email', 'headmaster@akuru.edu.mv')->first();
        $milestone = HifzMilestone::where('status', 'pending')->first();
        $milestone->update(['status' => 'supervisor_reviewed']);

        $this->actingAs($dean)->post(route('hifz.milestones.approve', $milestone));

        $this->assertEquals('approved', $milestone->fresh()->status->value);
    }
}
