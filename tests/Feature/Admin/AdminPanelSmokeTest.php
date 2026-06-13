<?php

namespace Tests\Feature\Admin;

use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(SchoolSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@akuru.edu.mv',
        ]);
        $this->superAdmin->assignRole('super_admin');
        $this->superAdmin->markEmailAsVerified();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminPanelRoutesProvider(): array
    {
        $routes = [
            'dashboard',
            'admin.enrollments.index',
            'admin.enrollments.payments',
            'students.index',
            'students.create',
            'teachers.index',
            'teachers.create',
            'hifz.hub',
            'hifz.dean.dashboard',
            'hifz.programs.index',
            'hifz.sessions.index',
            'hifz.milestones.index',
            'hifz.reports.index',
            'hifz.quran.mushafs.index',
            'quran-progress.index',
            'quran-progress.create',
            'announcements.index',
            'announcements.create',
            'substitutions.requests.index',
            'absences.index',
            'admin.instructors.index',
            'admin.instructors.create',
            'admin.pages.index',
            'admin.pages.create',
            'admin.courses.index',
            'admin.courses.create',
            'admin.users.index',
            'admin.settings.index',
            'enhanced.dashboard',
            'e-learning.index',
            'e-learning.quran',
            'e-learning.arabic',
            'e-learning.islamic-studies',
            'profile.edit',
            'analytics.index',
            'analytics.reports',
            'notifications.index',
        ];

        $cases = [];
        foreach ($routes as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * @dataProvider adminPanelRoutesProvider
     */
    public function test_super_admin_panel_route_loads(string $routeName): void
    {
        $this->assertTrue(Route::has($routeName), "Route [{$routeName}] is not registered.");

        $response = $this
            ->withoutLocalizationMiddleware()
            ->actingAs($this->superAdmin)
            ->followingRedirects()
            ->get(route($routeName));

        $response->assertOk(
            "Route [{$routeName}] failed with HTTP {$response->status()}."
        );

        $body = $response->getContent();

        $this->assertStringNotContainsString(
            'Server Error',
            $body,
            "Route [{$routeName}] returned a server error page."
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(Class|Interface|Trait) [\\\\\w]+ not found/',
            $body,
            "Route [{$routeName}] appears to have a missing class error."
        );
    }
}
