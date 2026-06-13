<?php

namespace Tests\Feature\Admin;

use App\Domains\Academics\Models\Announcement;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Subject;
use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Hifz\Models\QuranProgress;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Domains\Settings\Models\School;
use Database\Seeders\ClassSeeder;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\PeriodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\SurahSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminResourcePagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SchoolSeeder::class);
        $this->seed(ClassSeeder::class);
        $this->seed(SubjectSeeder::class);
        $this->seed(PeriodSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(SurahSeeder::class);
        $this->seed(HifzDemoSeeder::class);

        $this->superAdmin = User::where('email', 'admin@akuru.edu.mv')->first();
        $this->superAdmin->assignRole('super_admin');
        $this->superAdmin->markEmailAsVerified();
    }

    /**
     * @param  array<string, mixed>|object  $params
     */
    protected function assertRouteLoads(string $routeName, array|object $params = []): void
    {
        $this->assertTrue(Route::has($routeName), "Route [{$routeName}] is not registered.");

        $response = $this
            ->withoutLocalizationMiddleware()
            ->actingAs($this->superAdmin)
            ->followingRedirects()
            ->get(route($routeName, $params));

        $response->assertOk("Route [{$routeName}] failed with HTTP {$response->status()}.");

        $body = $response->getContent();

        $this->assertStringNotContainsString('Server Error', $body);
        $this->assertDoesNotMatchRegularExpression(
            '/(Class|Interface|Trait) [\\\\\w]+ not found/',
            $body
        );
    }

    public function test_student_detail_pages_load(): void
    {
        $student = Student::first();
        $this->assertNotNull($student, 'Expected seeded student for smoke test.');

        $this->assertRouteLoads('students.show', $student);
        $this->assertRouteLoads('students.edit', $student);
        $this->assertRouteLoads('students.quran-progress', $student);
    }

    public function test_teacher_detail_pages_load(): void
    {
        $teacher = Teacher::first();
        $this->assertNotNull($teacher, 'Expected seeded teacher for smoke test.');

        $this->assertRouteLoads('teachers.show', $teacher);
        $this->assertRouteLoads('teachers.edit', $teacher);
    }

    public function test_quran_progress_detail_pages_load(): void
    {
        $student = Student::first();
        $teacher = Teacher::first();

        $record = QuranProgress::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'surah_name' => 'Al-Fatiha',
            'surah_name_arabic' => 'الفاتحة',
            'surah_number' => 1,
            'type' => 'memorization',
            'status' => 'in_progress',
        ]);

        $this->assertRouteLoads('quran-progress.show', $record);
        $this->assertRouteLoads('quran-progress.edit', $record);
    }

    public function test_announcement_pages_load_with_data(): void
    {
        $announcement = Announcement::create([
            'school_id' => School::first()->id,
            'created_by' => $this->superAdmin->id,
            'title' => 'Test Announcement',
            'content' => 'Smoke test body',
            'type' => 'general',
            'priority' => 'medium',
            'publish_date' => now()->subDay(),
            'is_published' => true,
        ]);

        $this->assertRouteLoads('announcements.index');
        $this->assertRouteLoads('announcements.show', $announcement);
    }

    public function test_teacher_absence_pages_load(): void
    {
        $this->assertRouteLoads('absences.index');
        $this->assertRouteLoads('absences.create');
    }

    public function test_substitution_request_show_loads_when_assigned(): void
    {
        $teacher = Teacher::first();
        $classroom = ClassRoom::first();
        $subject = Subject::first();
        $period = Period::first();

        $substitutionRequest = SubstitutionRequest::create([
            'date' => now()->addDay()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'period_id' => $period->id,
            'status' => 'assigned',
        ]);

        SubstitutionAssignment::create([
            'substitution_request_id' => $substitutionRequest->id,
            'substitute_teacher_id' => $teacher->id,
            'assigned_by' => $this->superAdmin->id,
            'assigned_at' => now(),
        ]);

        $this->assertRouteLoads('substitutions.requests.show', $substitutionRequest);
    }

    public function test_students_index_tolerates_missing_user_account(): void
    {
        $student = Student::first();
        $this->assertNotNull($student?->user_id);

        $userId = $student->user_id;
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        User::where('id', $userId)->delete();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->assertRouteLoads('students.index');
    }
}
