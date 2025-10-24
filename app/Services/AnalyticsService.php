<?php

namespace App\Services;

use App\Models\{User, Course, Post, Event, GalleryAlbum, AdmissionApplication, ContactInquiry, UserActivity, DashboardAnalytics, SystemMetric, Report};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive analytics dashboard data
     */
    public function getDashboardAnalytics(int $days = 30)
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        return [
            'overview' => $this->getOverviewStats($startDate, $endDate),
            'user_analytics' => $this->getUserAnalytics($startDate, $endDate),
            'content_analytics' => $this->getContentAnalytics($startDate, $endDate),
            'academic_analytics' => $this->getAcademicAnalytics($startDate, $endDate),
            'financial_analytics' => $this->getFinancialAnalytics($startDate, $endDate),
            'system_health' => SystemMetric::getSystemHealth(),
            'performance_metrics' => SystemMetric::getPerformanceMetrics($days),
            'usage_analytics' => SystemMetric::getUsageAnalytics($days),
        ];
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(Carbon $startDate, Carbon $endDate)
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('last_login_at', '>=', $startDate)->count(),
            'new_users' => User::where('created_at', '>=', $startDate)->count(),
            'total_courses' => Course::count(),
            'active_courses' => Course::where('status', 'open')->count(),
            'total_posts' => Post::count(),
            'published_posts' => Post::where('is_published', true)->count(),
            'total_events' => Event::count(),
            'upcoming_events' => Event::where('start_date', '>=', now())->count(),
            'total_galleries' => GalleryAlbum::count(),
            'total_applications' => AdmissionApplication::count(),
            'pending_applications' => AdmissionApplication::whereIn('status', ['new', 'under_review'])->count(),
            'total_inquiries' => ContactInquiry::count(),
            'unread_inquiries' => ContactInquiry::where('is_read', false)->count(),
        ];
    }

    /**
     * Get user analytics
     */
    private function getUserAnalytics(Carbon $startDate, Carbon $endDate)
    {
        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $userActivity = UserActivity::selectRaw('DATE(performed_at) as date, COUNT(*) as count')
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $roleDistribution = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->selectRaw('roles.name, COUNT(*) as count')
            ->groupBy('roles.name')
            ->pluck('count', 'name');

        return [
            'user_growth' => $userGrowth,
            'user_activity' => $userActivity,
            'role_distribution' => $roleDistribution,
            'login_stats' => $this->getLoginStats($startDate, $endDate),
            'user_engagement' => $this->getUserEngagement($startDate, $endDate),
        ];
    }

    /**
     * Get content analytics
     */
    private function getContentAnalytics(Carbon $startDate, Carbon $endDate)
    {
        $postViews = Post::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $eventRegistrations = Event::withCount('registrations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $galleryViews = GalleryAlbum::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'post_views' => $postViews,
            'event_registrations' => $eventRegistrations,
            'gallery_views' => $galleryViews,
            'content_performance' => $this->getContentPerformance($startDate, $endDate),
        ];
    }

    /**
     * Get academic analytics
     */
    private function getAcademicAnalytics(Carbon $startDate, Carbon $endDate)
    {
        $courseEnrollments = Course::withCount('enrollments')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $applicationStats = AdmissionApplication::selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->pluck('count', 'status');

        $attendanceStats = $this->getAttendanceStats($startDate, $endDate);

        return [
            'course_enrollments' => $courseEnrollments,
            'application_stats' => $applicationStats,
            'attendance_stats' => $attendanceStats,
            'academic_progress' => $this->getAcademicProgress($startDate, $endDate),
        ];
    }

    /**
     * Get financial analytics
     */
    private function getFinancialAnalytics(Carbon $startDate, Carbon $endDate)
    {
        // This would integrate with your payment system
        return [
            'total_revenue' => 0, // Placeholder
            'monthly_revenue' => $this->getMonthlyRevenue($startDate, $endDate),
            'payment_methods' => $this->getPaymentMethodStats($startDate, $endDate),
            'revenue_trends' => $this->getRevenueTrends($startDate, $endDate),
        ];
    }

    /**
     * Generate a report
     */
    public function generateReport(int $userId, string $type, array $parameters = [], string $format = 'json')
    {
        $report = Report::createReport(
            $userId,
            $this->getReportName($type),
            $type,
            $this->getReportCategory($type),
            $parameters,
            $format
        );

        // Generate report data based on type
        $data = $this->generateReportData($type, $parameters);
        
        $report->update([
            'data' => $data,
            'status' => 'completed',
            'generated_at' => now(),
        ]);

        return $report;
    }

    /**
     * Get report data based on type
     */
    private function generateReportData(string $type, array $parameters)
    {
        switch ($type) {
            case 'user_analytics':
                return $this->generateUserAnalyticsReport($parameters);
            case 'course_performance':
                return $this->generateCoursePerformanceReport($parameters);
            case 'financial':
                return $this->generateFinancialReport($parameters);
            case 'system_health':
                return $this->generateSystemHealthReport($parameters);
            case 'academic_progress':
                return $this->generateAcademicProgressReport($parameters);
            case 'attendance':
                return $this->generateAttendanceReport($parameters);
            case 'enrollment':
                return $this->generateEnrollmentReport($parameters);
            case 'notification_analytics':
                return $this->generateNotificationAnalyticsReport($parameters);
            default:
                return [];
        }
    }

    /**
     * Generate user analytics report
     */
    private function generateUserAnalyticsReport(array $parameters)
    {
        $startDate = $parameters['start_date'] ?? now()->subDays(30);
        $endDate = $parameters['end_date'] ?? now();

        return [
            'summary' => $this->getOverviewStats($startDate, $endDate),
            'user_growth' => $this->getUserGrowthData($startDate, $endDate),
            'user_activity' => $this->getUserActivityData($startDate, $endDate),
            'role_distribution' => $this->getRoleDistributionData(),
            'engagement_metrics' => $this->getEngagementMetrics($startDate, $endDate),
        ];
    }

    /**
     * Generate course performance report
     */
    private function generateCoursePerformanceReport(array $parameters)
    {
        $courseId = $parameters['course_id'] ?? null;
        
        if ($courseId) {
            $course = Course::with(['enrollments', 'assignments'])->find($courseId);
            return [
                'course' => $course,
                'enrollment_stats' => $course->enrollments->count(),
                'completion_rate' => $this->getCourseCompletionRate($courseId),
                'student_feedback' => $this->getCourseFeedback($courseId),
            ];
        }

        return [
            'all_courses' => Course::withCount(['enrollments', 'assignments'])->get(),
            'performance_summary' => $this->getCoursePerformanceSummary(),
        ];
    }

    /**
     * Generate financial report
     */
    private function generateFinancialReport(array $parameters)
    {
        return [
            'revenue_summary' => $this->getRevenueSummary($parameters),
            'payment_analytics' => $this->getPaymentAnalytics($parameters),
            'financial_trends' => $this->getFinancialTrends($parameters),
        ];
    }

    /**
     * Generate system health report
     */
    private function generateSystemHealthReport(array $parameters)
    {
        return [
            'current_health' => SystemMetric::getSystemHealth(),
            'performance_metrics' => SystemMetric::getPerformanceMetrics(7),
            'usage_analytics' => SystemMetric::getUsageAnalytics(30),
            'system_alerts' => $this->getSystemAlerts(),
        ];
    }

    // Helper methods for specific analytics
    private function getLoginStats(Carbon $startDate, Carbon $endDate) { return []; }
    private function getUserEngagement(Carbon $startDate, Carbon $endDate) { return []; }
    private function getContentPerformance(Carbon $startDate, Carbon $endDate) { return []; }
    private function getAttendanceStats(Carbon $startDate, Carbon $endDate) { return []; }
    private function getAcademicProgress(Carbon $startDate, Carbon $endDate) { return []; }
    private function getMonthlyRevenue(Carbon $startDate, Carbon $endDate) { return []; }
    private function getPaymentMethodStats(Carbon $startDate, Carbon $endDate) { return []; }
    private function getRevenueTrends(Carbon $startDate, Carbon $endDate) { return []; }
    private function getReportName(string $type) { return ucwords(str_replace('_', ' ', $type)) . ' Report'; }
    private function getReportCategory(string $type) { return 'analytics'; }
    private function getUserGrowthData(Carbon $startDate, Carbon $endDate) { return []; }
    private function getUserActivityData(Carbon $startDate, Carbon $endDate) { return []; }
    private function getRoleDistributionData() { return []; }
    private function getEngagementMetrics(Carbon $startDate, Carbon $endDate) { return []; }
    private function getCourseCompletionRate(int $courseId) { return 0; }
    private function getCourseFeedback(int $courseId) { return []; }
    private function getCoursePerformanceSummary() { return []; }
    private function getRevenueSummary(array $parameters) { return []; }
    private function getPaymentAnalytics(array $parameters) { return []; }
    private function getFinancialTrends(array $parameters) { return []; }
    private function getSystemAlerts() { return []; }
    private function generateAcademicProgressReport(array $parameters) { return []; }
    private function generateAttendanceReport(array $parameters) { return []; }
    private function generateEnrollmentReport(array $parameters) { return []; }
    private function generateNotificationAnalyticsReport(array $parameters) { return []; }
}
