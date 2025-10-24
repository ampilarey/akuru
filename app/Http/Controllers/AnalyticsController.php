<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use App\Models\{Report, SystemMetric};
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Show analytics dashboard
     */
    public function index(Request $request)
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getDashboardAnalytics($days);

        return view('analytics.dashboard', compact('analytics', 'days'));
    }

    /**
     * Get analytics data via API
     */
    public function getAnalytics(Request $request)
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getDashboardAnalytics($days);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Show reports page
     */
    public function reports(Request $request)
    {
        $reports = Report::forUser(Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $availableTypes = Report::getAvailableTypes();
        $availableCategories = Report::getAvailableCategories();
        $availableFormats = Report::getAvailableFormats();

        return view('analytics.reports', compact('reports', 'availableTypes', 'availableCategories', 'availableFormats'));
    }

    /**
     * Generate a new report
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(Report::getAvailableTypes())),
            'category' => 'required|string|in:' . implode(',', array_keys(Report::getAvailableCategories())),
            'format' => 'required|string|in:' . implode(',', array_keys(Report::getAvailableFormats())),
            'parameters' => 'array',
        ]);

        $report = $this->analyticsService->generateReport(
            Auth::id(),
            $request->type,
            $request->parameters ?? [],
            $request->format
        );

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => $report,
        ]);
    }

    /**
     * Download a report
     */
    public function downloadReport($id)
    {
        $report = Report::forUser(Auth::id())->findOrFail($id);

        if ($report->status !== 'completed' || !$report->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Report not ready for download',
            ], 400);
        }

        if (!file_exists($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report file not found',
            ], 404);
        }

        return response()->download($report->file_path, $report->name . '.' . $report->format);
    }

    /**
     * Delete a report
     */
    public function deleteReport($id)
    {
        $report = Report::forUser(Auth::id())->findOrFail($id);

        // Delete file if exists
        if ($report->file_path && file_exists($report->file_path)) {
            unlink($report->file_path);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * Get system metrics
     */
    public function getSystemMetrics(Request $request)
    {
        $hours = $request->get('hours', 24);
        $category = $request->get('category');

        $query = SystemMetric::recent($hours);

        if ($category) {
            $query->category($category);
        }

        $metrics = $query->orderBy('recorded_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get real-time analytics
     */
    public function getRealTimeAnalytics()
    {
        $analytics = [
            'active_users' => SystemMetric::getMetricStats('active_users', 1),
            'system_health' => SystemMetric::getSystemHealth(),
            'recent_activities' => $this->getRecentActivities(),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get user analytics
     */
    public function getUserAnalytics(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        $analytics = [
            'user_growth' => $this->getUserGrowthData($startDate, $endDate),
            'user_activity' => $this->getUserActivityData($startDate, $endDate),
            'role_distribution' => $this->getRoleDistributionData(),
            'engagement_metrics' => $this->getEngagementMetrics($startDate, $endDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get content analytics
     */
    public function getContentAnalytics(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        $analytics = [
            'post_analytics' => $this->getPostAnalytics($startDate, $endDate),
            'event_analytics' => $this->getEventAnalytics($startDate, $endDate),
            'gallery_analytics' => $this->getGalleryAnalytics($startDate, $endDate),
            'course_analytics' => $this->getCourseAnalytics($startDate, $endDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get financial analytics
     */
    public function getFinancialAnalytics(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        $analytics = [
            'revenue_summary' => $this->getRevenueSummary($startDate, $endDate),
            'payment_analytics' => $this->getPaymentAnalytics($startDate, $endDate),
            'financial_trends' => $this->getFinancialTrends($startDate, $endDate),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    // Helper methods
    private function getRecentActivities()
    {
        return \App\Models\UserActivity::recent(24)
            ->orderBy('performed_at', 'desc')
            ->limit(10)
            ->get();
    }

    private function getUserGrowthData($startDate, $endDate) { return []; }
    private function getUserActivityData($startDate, $endDate) { return []; }
    private function getRoleDistributionData() { return []; }
    private function getEngagementMetrics($startDate, $endDate) { return []; }
    private function getPostAnalytics($startDate, $endDate) { return []; }
    private function getEventAnalytics($startDate, $endDate) { return []; }
    private function getGalleryAnalytics($startDate, $endDate) { return []; }
    private function getCourseAnalytics($startDate, $endDate) { return []; }
    private function getRevenueSummary($startDate, $endDate) { return []; }
    private function getPaymentAnalytics($startDate, $endDate) { return []; }
    private function getFinancialTrends($startDate, $endDate) { return []; }
}