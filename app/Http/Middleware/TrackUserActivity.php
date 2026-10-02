<?php

namespace App\Http\Middleware;

use App\Domains\Settings\Models\DashboardAnalytics;
use App\Domains\Settings\Models\UserActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Nothing here: the two writes this middleware makes happen in
        // `terminate()`, after the response has left (ADMIN_PANEL.md §7 P3,
        // STATUS §5nn). They used to run between building the response and
        // sending it, so a slow insert — 700 ms twice in the audit's probe —
        // held every page for its own bookkeeping.
        return $next($request);
    }

    /**
     * Record the visit once the response is on its way. Laravel calls this
     * after the response is sent (FPM and LiteSpeed flush first); the
     * page's own queries are no longer waiting on it.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Only track authenticated users
        if (auth()->check()) {
            $this->trackActivity($request);
        }
    }

    private function trackActivity(Request $request)
    {
        $user = auth()->user();
        $route = $request->route();

        if (! $route) {
            return;
        }

        $routeName = $route->getName();
        $path = $request->path();
        $method = $request->method();

        // Skip tracking for certain routes
        if ($this->shouldSkipTracking($routeName, $path)) {
            return;
        }

        // Determine activity type and name
        $activityData = $this->getActivityData($routeName, $path, $method);

        if ($activityData) {
            // Record user activity
            UserActivity::recordActivity(
                $user->id,
                $activityData['type'],
                $activityData['name'],
                $activityData['description'],
                [
                    'route' => $routeName,
                    'path' => $path,
                    'method' => $method,
                    'ip' => $request->ip(),
                ]
            );

            // Record analytics metrics
            $this->recordAnalytics($user, $activityData, $path);
        }
    }

    private function shouldSkipTracking($routeName, $path)
    {
        $skipRoutes = [
            'api.*',
            'livewire.*',
            'telescope.*',
        ];

        $skipPaths = [
            'api/',
            'livewire/',
            'telescope/',
            '_debugbar/',
        ];

        // Check route names
        foreach ($skipRoutes as $skipRoute) {
            if (str_contains($routeName, str_replace('*', '', $skipRoute))) {
                return true;
            }
        }

        // Check paths
        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    private function getActivityData($routeName, $path, $method)
    {
        // Dashboard activities
        if (str_contains($routeName, 'dashboard')) {
            return [
                'type' => 'dashboard_view',
                'name' => 'Viewed Dashboard',
                'description' => 'User accessed dashboard',
            ];
        }

        // Student activities
        if (str_contains($routeName, 'student')) {
            return [
                'type' => 'student_action',
                'name' => 'Student Action',
                'description' => 'Student performed an action',
            ];
        }

        // Teacher activities
        if (str_contains($routeName, 'teacher')) {
            return [
                'type' => 'teacher_action',
                'name' => 'Teacher Action',
                'description' => 'Teacher performed an action',
            ];
        }

        // Admin activities
        if (str_contains($routeName, 'admin')) {
            return [
                'type' => 'admin_action',
                'name' => 'Admin Action',
                'description' => 'Admin performed an action',
            ];
        }

        // Quran progress activities
        if (str_contains($routeName, 'quran')) {
            return [
                'type' => 'quran_progress',
                'name' => 'Quran Progress Update',
                'description' => 'User updated Quran progress',
            ];
        }

        // Course activities
        if (str_contains($routeName, 'course')) {
            return [
                'type' => 'course_action',
                'name' => 'Course Action',
                'description' => 'User interacted with course',
            ];
        }

        // Profile activities
        if (str_contains($routeName, 'profile')) {
            return [
                'type' => 'profile_update',
                'name' => 'Profile Update',
                'description' => 'User updated profile',
            ];
        }

        // Default page view
        if ($method === 'GET') {
            return [
                'type' => 'page_view',
                'name' => 'Page View',
                'description' => "User viewed {$path}",
            ];
        }

        return null;
    }

    private function recordAnalytics($user, $activityData, $path)
    {
        $today = now()->toDateString();

        // Record page views
        if ($activityData['type'] === 'page_view') {
            DashboardAnalytics::recordMetric(
                $user->id,
                'page_views',
                'Page Views',
                1,
                ['path' => $path]
            );
        }

        // Record login count
        if ($activityData['type'] === 'login') {
            DashboardAnalytics::recordMetric(
                $user->id,
                'login_count',
                'Login Count',
                1
            );
        }

        // Record dashboard visits
        if ($activityData['type'] === 'dashboard_view') {
            DashboardAnalytics::recordMetric(
                $user->id,
                'dashboard_visits',
                'Dashboard Visits',
                1
            );
        }
    }
}
