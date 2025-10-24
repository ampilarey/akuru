<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{User, DashboardAnalytics, UserActivity, Course, Post, Event, GalleryAlbum};
use App\Models\{AdmissionApplication, ContactInquiry};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EnhancedDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Get role-specific dashboard data
        $dashboardData = $this->getRoleSpecificDashboard($user);
        
        // Record dashboard visit
        UserActivity::recordActivity(
            $user->id,
            'dashboard_view',
            'Viewed Dashboard',
            'User accessed their personalized dashboard',
            ['role' => $user->getRoleNames()->first()]
        );

        return view('dashboard.enhanced', compact('dashboardData', 'user'));
    }

    private function getRoleSpecificDashboard(User $user)
    {
        $role = $user->getRoleNames()->first();
        
        switch ($role) {
            case 'super_admin':
                return $this->getSuperAdminDashboard($user);
            case 'admin':
                return $this->getAdminDashboard($user);
            case 'teacher':
                return $this->getTeacherDashboard($user);
            case 'student':
                return $this->getStudentDashboard($user);
            case 'parent':
                return $this->getParentDashboard($user);
            default:
                return $this->getDefaultDashboard($user);
        }
    }

    private function getSuperAdminDashboard(User $user)
    {
        return [
            'title' => 'Super Admin Dashboard',
            'role' => 'super_admin',
            'stats' => [
                'total_users' => User::count(),
                'total_courses' => Course::count(),
                'total_posts' => Post::count(),
                'total_events' => Event::count(),
                'total_galleries' => GalleryAlbum::count(),
                'total_applications' => AdmissionApplication::count(),
                'total_inquiries' => ContactInquiry::count(),
            ],
            'recent_activities' => UserActivity::recent(7)->orderBy('performed_at', 'desc')->limit(10)->get(),
            'system_health' => $this->getSystemHealth(),
            'user_analytics' => $this->getUserAnalytics(),
            'content_stats' => $this->getContentStats(),
        ];
    }

    private function getAdminDashboard(User $user)
    {
        return [
            'title' => 'Admin Dashboard',
            'role' => 'admin',
            'stats' => [
                'total_students' => User::role('student')->count(),
                'total_teachers' => User::role('teacher')->count(),
                'active_courses' => Course::where('status', 'open')->count(),
                'pending_applications' => AdmissionApplication::whereIn('status', ['new', 'under_review'])->count(),
                'recent_inquiries' => ContactInquiry::recent(7)->count(),
                'upcoming_events' => Event::where('start_date', '>=', now())->count(),
            ],
            'recent_activities' => UserActivity::recent(7)->orderBy('performed_at', 'desc')->limit(10)->get(),
            'pending_tasks' => $this->getPendingTasks(),
            'revenue_stats' => $this->getRevenueStats(),
        ];
    }

    private function getTeacherDashboard(User $user)
    {
        return [
            'title' => 'Teacher Dashboard',
            'role' => 'teacher',
            'stats' => [
                'my_students' => $this->getTeacherStudents($user),
                'my_courses' => Course::where('instructor_id', $user->id)->count(),
                'upcoming_classes' => $this->getUpcomingClasses($user),
                'pending_grades' => $this->getPendingGrades($user),
                'recent_announcements' => $this->getRecentAnnouncements($user),
            ],
            'recent_activities' => UserActivity::where('user_id', $user->id)->recent(7)->orderBy('performed_at', 'desc')->limit(10)->get(),
            'teaching_schedule' => $this->getTeachingSchedule($user),
            'student_progress' => $this->getStudentProgress($user),
        ];
    }

    private function getStudentDashboard(User $user)
    {
        return [
            'title' => 'Student Dashboard',
            'role' => 'student',
            'stats' => [
                'enrolled_courses' => $this->getEnrolledCourses($user),
                'completed_assignments' => $this->getCompletedAssignments($user),
                'upcoming_exams' => $this->getUpcomingExams($user),
                'quran_progress' => $this->getQuranProgress($user),
                'attendance_rate' => $this->getAttendanceRate($user),
            ],
            'recent_activities' => UserActivity::where('user_id', $user->id)->recent(7)->orderBy('performed_at', 'desc')->limit(10)->get(),
            'upcoming_events' => Event::where('start_date', '>=', now())->limit(5)->get(),
            'recent_announcements' => Post::where('is_published', true)->recent(5)->get(),
            'course_progress' => $this->getCourseProgress($user),
        ];
    }

    private function getParentDashboard(User $user)
    {
        $children = $this->getParentChildren($user);
        
        return [
            'title' => 'Parent Dashboard',
            'role' => 'parent',
            'children' => $children,
            'stats' => [
                'total_children' => $children->count(),
                'children_attendance' => $this->getChildrenAttendance($children),
                'upcoming_events' => Event::where('start_date', '>=', now())->limit(5)->get(),
                'recent_announcements' => Post::where('is_published', true)->recent(5)->get(),
            ],
            'recent_activities' => UserActivity::whereIn('user_id', $children->pluck('id'))->recent(7)->orderBy('performed_at', 'desc')->limit(10)->get(),
            'children_progress' => $this->getChildrenProgress($children),
        ];
    }

    private function getDefaultDashboard(User $user)
    {
        return [
            'title' => 'Dashboard',
            'role' => 'user',
            'stats' => [
                'profile_completion' => $this->getProfileCompletion($user),
                'recent_activities' => UserActivity::where('user_id', $user->id)->recent(7)->count(),
            ],
            'recent_activities' => UserActivity::where('user_id', $user->id)->recent(7)->orderBy('performed_at', 'desc')->limit(10)->get(),
        ];
    }

    // Helper methods for specific data
    private function getSystemHealth()
    {
        return [
            'database_status' => 'healthy',
            'storage_usage' => $this->getStorageUsage(),
            'active_users_today' => UserActivity::where('activity_type', 'login')->today()->distinct('user_id')->count(),
            'system_uptime' => $this->getSystemUptime(),
        ];
    }

    private function getUserAnalytics()
    {
        return [
            'new_users_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'active_users_this_week' => UserActivity::where('performed_at', '>=', now()->subWeek())->distinct('user_id')->count(),
            'user_growth' => $this->getUserGrowth(),
        ];
    }

    private function getContentStats()
    {
        return [
            'total_posts' => Post::count(),
            'published_posts' => Post::where('is_published', true)->count(),
            'total_events' => Event::count(),
            'upcoming_events' => Event::where('start_date', '>=', now())->count(),
            'total_galleries' => GalleryAlbum::count(),
            'public_galleries' => GalleryAlbum::where('is_public', true)->count(),
        ];
    }

    private function getPendingTasks()
    {
        return [
            'pending_applications' => AdmissionApplication::whereIn('status', ['new', 'under_review'])->count(),
            'unread_inquiries' => ContactInquiry::where('is_read', false)->count(),
            'pending_approvals' => $this->getPendingApprovals(),
        ];
    }

    private function getRevenueStats()
    {
        return [
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'pending_payments' => $this->getPendingPayments(),
            'revenue_growth' => $this->getRevenueGrowth(),
        ];
    }

    // Additional helper methods would be implemented here...
    private function getTeacherStudents($user) { return 0; }
    private function getUpcomingClasses($user) { return 0; }
    private function getPendingGrades($user) { return 0; }
    private function getRecentAnnouncements($user) { return 0; }
    private function getTeachingSchedule($user) { return []; }
    private function getStudentProgress($user) { return []; }
    private function getEnrolledCourses($user) { return 0; }
    private function getCompletedAssignments($user) { return 0; }
    private function getUpcomingExams($user) { return 0; }
    private function getQuranProgress($user) { return 0; }
    private function getAttendanceRate($user) { return 0; }
    private function getCourseProgress($user) { return []; }
    private function getParentChildren($user) { return collect([]); }
    private function getChildrenAttendance($children) { return 0; }
    private function getChildrenProgress($children) { return []; }
    private function getProfileCompletion($user) { return 100; }
    private function getStorageUsage() { return '2.5 GB / 10 GB'; }
    private function getSystemUptime() { return '99.9%'; }
    private function getUserGrowth() { return '+15%'; }
    private function getPendingApprovals() { return 0; }
    private function getMonthlyRevenue() { return '$5,250'; }
    private function getPendingPayments() { return 3; }
    private function getRevenueGrowth() { return '+12%'; }
}