<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Legacy\Models\Assignment;
use App\Domains\Academics\Models\Announcement;
use App\Domains\Hifz\Models\QuranProgress;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Domains\Portal\Actions\ComposeDashboardPrayerAction;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (! $user) {
            return redirect()->route('login');
        }

        // Get dashboard data based on user role
        if ($user->hasRole('super_admin')) {
            return $this->superAdminDashboard();
        } elseif ($user->isAdmin() || $user->isHeadmaster()) {
            return redirect()->route('portal.overview');
        } elseif ($user->isSupervisor()) {
            return $this->supervisorDashboard();
        } elseif ($user->isTeacher()) {
            return $this->teacherDashboard();
        } elseif ($user->isStudent()) {
            return redirect()->route('portal.home');
        } elseif ($user->isParent()) {
            return redirect()->route('portal.home');
        }

        // Public users (registered via OTP for course enrollment)
        return $this->publicUserDashboard();
    }

    private function publicUserDashboard()
    {
        $user = auth()->user();

        $enrollments = \App\Domains\Courses\Models\CourseEnrollment::with(['course', 'student', 'payment'])
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->get();

        $activeEnrollments = $enrollments->whereIn('status', ['active']);
        $pendingEnrollments = $enrollments->whereIn('status', ['pending', 'pending_payment']);
        $openCourses = \App\Domains\Courses\Models\Course::where('status', 'open')->latest()->take(4)->get();

        $hasPassword = ! empty($user->password);

        return view('dashboard.public-user', compact(
            'user',
            'enrollments',
            'activeEnrollments',
            'pendingEnrollments',
            'openCourses',
            'hasPassword'
        ));
    }

    private function superAdminDashboard()
    {
        $stats = [
            'total_users' => \App\Domains\Identity\Models\User::count(),
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'active_quran_students' => Student::whereHas('quranProgress')->count(),
            'total_assignments' => Assignment::count(),
            'total_announcements' => Announcement::count(),
            'database_size' => $this->getDatabaseSize(),
            'sms_usage_today' => $this->getSmsUsageToday(),
            // Course & enrollment stats
            'total_courses' => \App\Domains\Courses\Models\Course::count(),
            'open_courses' => \App\Domains\Courses\Models\Course::where('status', 'open')->count(),
            'total_enrollments' => \App\Domains\Courses\Models\CourseEnrollment::count(),
            'pending_enrollments' => \App\Domains\Courses\Models\CourseEnrollment::whereIn('status', ['pending', 'pending_payment'])->count(),
            'active_enrollments' => \App\Domains\Courses\Models\CourseEnrollment::where('status', 'active')->count(),
            'enrollments_today' => \App\Domains\Courses\Models\CourseEnrollment::whereDate('created_at', today())->count(),
            'revenue_total' => \App\Domains\Finance\Models\Payment::where('status', 'paid')->sum('amount'),
            'revenue_today' => \App\Domains\Finance\Models\Payment::where('status', 'paid')->whereDate('created_at', today())->sum('amount'),
            'new_users_today' => \App\Domains\Identity\Models\User::whereDate('created_at', today())->count(),
            'new_users_this_month' => \App\Domains\Identity\Models\User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        $metrics = [
            'student_growth' => $this->getStudentGrowthMetrics(),
            'quran_progress_stats' => $this->getQuranProgressStats(),
            'attendance_rate' => $this->getOverallAttendanceRate(),
            'recent_activities' => $this->getRecentActivities(),
            'system_health' => $this->getSystemHealth(),
            'sms_gateway_status' => $this->getSmsGatewayStatus(),
        ];

        // Recent enrollments (last 10)
        $recentEnrollments = \App\Domains\Courses\Models\CourseEnrollment::with(['student', 'course', 'payment'])
            ->latest()
            ->take(10)
            ->get();

        $prayer = app(ComposeDashboardPrayerAction::class)->execute();
        $islamicDate = $prayer['islamicDate'];
        $prayerTimes = $prayer['prayerTimes'];
        $currentPrayer = $prayer['currentPrayer'];
        $specialDays = $prayer['specialDays'];

        return view('dashboard.super-admin', compact(
            'stats', 'metrics',
            'recentEnrollments',
            'islamicDate', 'prayerTimes', 'currentPrayer', 'specialDays'
        ));
    }

    private function teacherDashboard()
    {
        return redirect()->route('academics.registers.today');
    }

    private function supervisorDashboard()
    {
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'quran_progress_today' => QuranProgress::whereDate('created_at', today())->count(),
        ];

        return view('dashboard.supervisor', compact('stats'));
    }

    // Helper methods for advanced metrics

    private function getStudentGrowthMetrics()
    {
        $currentMonth = Carbon::now()->month;
        $lastMonth = Carbon::now()->subMonth()->month;

        return [
            'current_month' => Student::whereMonth('created_at', $currentMonth)->count(),
            'last_month' => Student::whereMonth('created_at', $lastMonth)->count(),
            'growth_rate' => $this->calculateGrowthRate(
                Student::whereMonth('created_at', $lastMonth)->count(),
                Student::whereMonth('created_at', $currentMonth)->count()
            ),
        ];
    }

    private function getQuranProgressStats()
    {
        return [
            'total_progress_records' => QuranProgress::count(),
            'completed_surahs' => QuranProgress::where('status', 'completed')->count(),
            'in_progress_surahs' => QuranProgress::where('status', 'in_progress')->count(),
            'average_accuracy' => QuranProgress::avg('accuracy_percentage') ?? 0,
        ];
    }

    private function getOverallAttendanceRate()
    {
        // This would need to be implemented based on your attendance system
        return 85.5; // Placeholder
    }

    private function getRecentActivities()
    {
        return [
            'new_students' => Student::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'new_assignments' => Assignment::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'quran_progress_updates' => QuranProgress::where('updated_at', '>=', Carbon::now()->subDays(7))->count(),
        ];
    }

    // Utility methods

    private function calculateGrowthRate($oldValue, $newValue)
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    // Super Admin specific methods

    private function getDatabaseSize()
    {
        try {
            $dbName = config('database.connections.mysql.database');
            $size = DB::select('
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ', [$dbName]);

            return $size[0]->size_mb ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getSmsUsageToday()
    {
        try {
            // This would connect to SMS Gateway API to get usage
            // For now, return placeholder
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getSystemHealth()
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'storage' => $this->checkStorageHealth(),
            'sms_gateway' => $this->getSmsGatewayStatus(),
        ];
    }

    private function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();

            return 'healthy';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function checkStorageHealth()
    {
        $path = storage_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used_percentage = 100 - (($free / $total) * 100);

        if ($used_percentage > 90) {
            return 'critical';
        } elseif ($used_percentage > 75) {
            return 'warning';
        }

        return 'healthy';
    }

    private function getSmsGatewayStatus()
    {
        try {
            $smsService = app(\App\Domains\Notifications\Contracts\SmsSenderInterface::class);

            return $smsService->checkHealth() ? 'online' : 'offline';
        } catch (\Exception $e) {
            return 'offline';
        }
    }
}
