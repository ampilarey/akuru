<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\QuranProgress;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Timetable;
use App\Models\Attendance;
use App\Models\RecitationPractice;
use App\Models\Message;
use App\Models\MediaGallery;
use App\Services\IslamicCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Check if user is authenticated
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Get dashboard data based on user role
        if ($user->hasRole('super_admin')) {
            return $this->superAdminDashboard();
        } elseif ($user->isAdmin() || $user->isHeadmaster()) {
            return $this->adminDashboard();
        } elseif ($user->isTeacher()) {
            return $this->teacherDashboard();
        } elseif ($user->isStudent()) {
            return $this->studentDashboard();
        } elseif ($user->isParent()) {
            return $this->parentDashboard();
        }
        
        // Public users (registered via OTP for course enrollment)
        return $this->publicUserDashboard();
    }
    
    private function publicUserDashboard()
    {
        $user = auth()->user();

        $enrollments = \App\Models\CourseEnrollment::with(['course', 'student', 'payment'])
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->get();

        $activeEnrollments   = $enrollments->whereIn('status', ['active']);
        $pendingEnrollments  = $enrollments->whereIn('status', ['pending', 'pending_payment']);
        $openCourses = \App\Models\Course::where('status', 'open')->latest()->take(4)->get();

        $hasPassword = !empty($user->password);

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
        // Super Admin sees everything + system stats
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'total_users' => \App\Models\User::count(),
            'active_quran_students' => Student::whereHas('quranProgress')->count(),
            'total_assignments' => Assignment::count(),
            'total_announcements' => Announcement::count(),
            'database_size' => $this->getDatabaseSize(),
            'sms_usage_today' => $this->getSmsUsageToday(),
        ];
        
        // System metrics
        $metrics = [
            'student_growth' => $this->getStudentGrowthMetrics(),
            'quran_progress_stats' => $this->getQuranProgressStats(),
            'attendance_rate' => $this->getOverallAttendanceRate(),
            'recent_activities' => $this->getRecentActivities(),
            'system_health' => $this->getSystemHealth(),
            'sms_gateway_status' => $this->getSmsGatewayStatus(),
        ];
        
        // Islamic calendar data
        $islamicDate = IslamicCalendarService::getCurrentIslamicDate();
        $prayerTimes = IslamicCalendarService::getPrayerTimes();
        $currentPrayer = IslamicCalendarService::getCurrentPrayerTime();
        $specialDays = IslamicCalendarService::getSpecialIslamicDays();
        
        return view('dashboard.super-admin', compact('stats', 'metrics', 'islamicDate', 'prayerTimes', 'currentPrayer', 'specialDays'));
    }
    
    private function adminDashboard()
    {
        // Basic statistics
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'active_quran_students' => Student::whereHas('quranProgress')->count(),
            'total_assignments' => Assignment::count(),
            'pending_assignments' => Assignment::where('due_date', '>', now())->count(),
            'completed_assignments' => AssignmentSubmission::whereNotNull('marks_obtained')->count(),
            'total_announcements' => Announcement::count(),
            'recent_announcements' => Announcement::latest()->take(5)->get(),
        ];
        
        // Advanced metrics
        $metrics = [
            'student_growth' => $this->getStudentGrowthMetrics(),
            'quran_progress_stats' => $this->getQuranProgressStats(),
            'assignment_completion_rate' => $this->getAssignmentCompletionRate(),
            'attendance_rate' => $this->getOverallAttendanceRate(),
            'recent_activities' => $this->getRecentActivities(),
            'upcoming_events' => $this->getUpcomingEvents(),
        ];
        
        // Islamic calendar data
        $islamicDate = IslamicCalendarService::getCurrentIslamicDate();
        $prayerTimes = IslamicCalendarService::getPrayerTimes();
        $currentPrayer = IslamicCalendarService::getCurrentPrayerTime();
        $specialDays = IslamicCalendarService::getSpecialIslamicDays();
        
        return view('dashboard.admin', compact('stats', 'metrics', 'islamicDate', 'prayerTimes', 'currentPrayer', 'specialDays'));
    }
    
    private function teacherDashboard()
    {
        try {
            $teacher = auth()->user()->teacher;
            
            if (!$teacher) {
                return $this->adminDashboard();
            }
            
            // Basic statistics
            $stats = [
                'my_students' => $teacher->students()->count(),
                'pending_grades' => $this->getTeacherPendingGrades($teacher),
                'quran_progress_updates' => $this->getTeacherQuranUpdates($teacher),
                'todays_classes' => $this->getTodaysClasses($teacher),
                'total_assignments' => Assignment::where('teacher_id', $teacher->id)->count(),
                'pending_submissions' => $this->getPendingSubmissions($teacher),
            ];
            
            // Advanced metrics
            $metrics = [
                'student_performance' => $this->getTeacherStudentPerformance($teacher),
                'quran_progress_summary' => $this->getTeacherQuranProgressSummary($teacher),
                'recent_submissions' => $this->getRecentSubmissions($teacher),
                'upcoming_deadlines' => $this->getUpcomingDeadlines($teacher),
                'class_schedule' => $this->getTeacherClassSchedule($teacher),
            ];
            
            return view('dashboard.teacher', compact('stats', 'metrics'));
        } catch (\Exception $e) {
            // Fallback to admin dashboard if there's an error
            return $this->adminDashboard();
        }
    }
    
    private function studentDashboard()
    {
        try {
            $student = auth()->user()->student;
            
            if (!$student) {
                return $this->adminDashboard();
            }
            
            // Basic statistics
            $stats = [
                'quran_progress' => $student->quranProgress()->latest()->take(5)->get(),
                'recent_grades' => $this->getStudentRecentGrades($student),
                'attendance_rate' => $this->getStudentAttendanceRate($student),
                'total_assignments' => $this->getStudentTotalAssignments($student),
                'completed_assignments' => $this->getStudentCompletedAssignments($student),
                'pending_assignments' => $this->getStudentPendingAssignments($student),
            ];
            
            // Advanced metrics
            $metrics = [
                'quran_achievements' => $this->getStudentQuranAchievements($student),
                'grade_trends' => $this->getStudentGradeTrends($student),
                'attendance_trends' => $this->getStudentAttendanceTrends($student),
                'upcoming_deadlines' => $this->getStudentUpcomingDeadlines($student),
                'recent_activities' => $this->getStudentRecentActivities($student),
                'class_schedule' => $this->getStudentClassSchedule($student),
            ];
            
            return view('dashboard.student', compact('stats', 'metrics'));
        } catch (\Exception $e) {
            // Fallback to admin dashboard if there's an error
            return $this->adminDashboard();
        }
    }
    
    private function parentDashboard()
    {
        try {
            $parent = auth()->user()->parentGuardian;
            
            if (!$parent) {
                return $this->adminDashboard();
            }
            
            $children = $parent->students;
            
            // Basic statistics
            $stats = [
                'total_children' => $children->count(),
                'children_with_quran_progress' => $children->whereHas('quranProgress')->count(),
                'children_attendance_rate' => $this->getChildrenAttendanceRate($children),
                'pending_assignments' => $this->getChildrenPendingAssignments($children),
                'recent_grades' => $this->getChildrenRecentGrades($children),
            ];
            
            // Advanced metrics
            $metrics = [
                'children_progress_summary' => $this->getChildrenProgressSummary($children),
                'quran_progress_overview' => $this->getChildrenQuranProgressOverview($children),
                'attendance_overview' => $this->getChildrenAttendanceOverview($children),
                'recent_activities' => $this->getChildrenRecentActivities($children),
                'upcoming_events' => $this->getChildrenUpcomingEvents($children),
            ];
            
            return view('dashboard.parent', compact('children', 'stats', 'metrics'));
        } catch (\Exception $e) {
            // Fallback to admin dashboard if there's an error
            return $this->adminDashboard();
        }
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
    
    private function getAssignmentCompletionRate()
    {
        $totalAssignments = Assignment::count();
        $completedAssignments = AssignmentSubmission::whereNotNull('marks_obtained')->count();
        
        return $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0;
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
    
    private function getUpcomingEvents()
    {
        return [
            'upcoming_assignments' => Assignment::where('due_date', '>=', now())
                ->where('due_date', '<=', Carbon::now()->addDays(7))
                ->count(),
            'upcoming_classes' => Timetable::where('start_date', '>=', now())
                ->where('start_date', '<=', Carbon::now()->addDays(7))
                ->count(),
        ];
    }
    
    // Teacher-specific helper methods
    
    private function getTeacherPendingGrades($teacher)
    {
        return AssignmentSubmission::whereHas('assignment', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })->whereNull('marks_obtained')->count();
    }
    
    private function getTeacherQuranUpdates($teacher)
    {
        return RecitationPractice::where('evaluated_by', $teacher->id)
            ->where('status', 'pending')
            ->count();
    }
    
    private function getTodaysClasses($teacher)
    {
        return Timetable::where('teacher_id', $teacher->id)
            ->whereDate('start_date', Carbon::today())
            ->count();
    }
    
    private function getPendingSubmissions($teacher)
    {
        return AssignmentSubmission::whereHas('assignment', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })->whereNull('marks_obtained')->count();
    }
    
    private function getTeacherStudentPerformance($teacher)
    {
        return [
            'average_grade' => AssignmentSubmission::whereHas('assignment', function($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })->avg('marks_obtained') ?? 0,
            'total_submissions' => AssignmentSubmission::whereHas('assignment', function($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })->count(),
        ];
    }
    
    private function getTeacherQuranProgressSummary($teacher)
    {
        return [
            'total_practices' => RecitationPractice::where('evaluated_by', $teacher->id)->count(),
            'pending_feedback' => RecitationPractice::where('evaluated_by', $teacher->id)
                ->where('status', 'pending')->count(),
            'average_grade' => RecitationPractice::where('evaluated_by', $teacher->id)
                ->avg('accuracy_score') ?? 0,
        ];
    }
    
    private function getRecentSubmissions($teacher)
    {
        return AssignmentSubmission::whereHas('assignment', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })->latest()->take(5)->get();
    }
    
    private function getUpcomingDeadlines($teacher)
    {
        return Assignment::where('teacher_id', $teacher->id)
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', Carbon::now()->addDays(7))
            ->latest('due_date')
            ->take(5)
            ->get();
    }
    
    private function getTeacherClassSchedule($teacher)
    {
        return Timetable::where('teacher_id', $teacher->id)
            ->where('start_date', '>=', Carbon::today())
            ->where('start_date', '<=', Carbon::now()->addDays(7))
            ->orderBy('start_date')
            ->take(10)
            ->get();
    }
    
    // Student-specific helper methods
    
    private function getStudentRecentGrades($student)
    {
        return AssignmentSubmission::where('student_id', $student->id)
            ->whereNotNull('marks_obtained')
            ->latest()
            ->take(5)
            ->get();
    }
    
    private function getStudentAttendanceRate($student)
    {
        // This would need to be implemented based on your attendance system
        return 92.5; // Placeholder
    }
    
    private function getStudentTotalAssignments($student)
    {
        return Assignment::whereHas('classRoom', function($query) use ($student) {
            $query->where('id', $student->class_id);
        })->count();
    }
    
    private function getStudentCompletedAssignments($student)
    {
        return AssignmentSubmission::where('student_id', $student->id)
            ->whereNotNull('marks_obtained')
            ->count();
    }
    
    private function getStudentPendingAssignments($student)
    {
        $totalAssignments = $this->getStudentTotalAssignments($student);
        $completedAssignments = $this->getStudentCompletedAssignments($student);
        return $totalAssignments - $completedAssignments;
    }
    
    private function getStudentQuranAchievements($student)
    {
        return [
            'completed_surahs' => QuranProgress::where('student_id', $student->id)
                ->where('status', 'completed')->count(),
            'total_practices' => RecitationPractice::where('student_id', $student->id)->count(),
            'average_accuracy' => QuranProgress::where('student_id', $student->id)
                ->avg('accuracy_percentage') ?? 0,
        ];
    }
    
    private function getStudentGradeTrends($student)
    {
        return AssignmentSubmission::where('student_id', $student->id)
            ->whereNotNull('marks_obtained')
            ->orderBy('created_at')
            ->take(10)
            ->get()
            ->pluck('marks_obtained');
    }
    
    private function getStudentAttendanceTrends($student)
    {
        // This would need to be implemented based on your attendance system
        return [95, 92, 88, 94, 96, 90, 93]; // Placeholder data
    }
    
    private function getStudentUpcomingDeadlines($student)
    {
        return Assignment::whereHas('classRoom', function($query) use ($student) {
            $query->where('id', $student->class_id);
        })
        ->where('due_date', '>=', now())
        ->where('due_date', '<=', Carbon::now()->addDays(7))
        ->latest('due_date')
        ->take(5)
        ->get();
    }
    
    private function getStudentRecentActivities($student)
    {
        return [
            'recent_submissions' => AssignmentSubmission::where('student_id', $student->id)
                ->latest()->take(3)->get(),
            'quran_practices' => RecitationPractice::where('student_id', $student->id)
                ->latest()->take(3)->get(),
        ];
    }
    
    private function getStudentClassSchedule($student)
    {
        return Timetable::whereHas('classRoom', function($query) use ($student) {
            $query->where('id', $student->class_id);
        })
        ->where('start_date', '>=', Carbon::today())
        ->where('start_date', '<=', Carbon::now()->addDays(7))
        ->orderBy('start_date')
        ->take(10)
        ->get();
    }
    
    // Parent-specific helper methods
    
    private function getChildrenAttendanceRate($children)
    {
        // This would need to be implemented based on your attendance system
        return 89.2; // Placeholder
    }
    
    private function getChildrenPendingAssignments($children)
    {
        $totalPending = 0;
        foreach ($children as $child) {
            $totalPending += $this->getStudentPendingAssignments($child);
        }
        return $totalPending;
    }
    
    private function getChildrenRecentGrades($children)
    {
        $recentGrades = collect();
        foreach ($children as $child) {
            $recentGrades = $recentGrades->merge($this->getStudentRecentGrades($child));
        }
        return $recentGrades->sortByDesc('created_at')->take(5);
    }
    
    private function getChildrenProgressSummary($children)
    {
        return [
            'total_children' => $children->count(),
            'children_with_progress' => $children->whereHas('quranProgress')->count(),
            'average_attendance' => $this->getChildrenAttendanceRate($children),
        ];
    }
    
    private function getChildrenQuranProgressOverview($children)
    {
        $totalProgress = 0;
        $completedSurahs = 0;
        
        foreach ($children as $child) {
            $totalProgress += $child->quranProgress()->count();
            $completedSurahs += $child->quranProgress()->where('status', 'completed')->count();
        }
        
        return [
            'total_progress_records' => $totalProgress,
            'completed_surahs' => $completedSurahs,
            'completion_rate' => $totalProgress > 0 ? round(($completedSurahs / $totalProgress) * 100, 2) : 0,
        ];
    }
    
    private function getChildrenAttendanceOverview($children)
    {
        return [
            'overall_rate' => $this->getChildrenAttendanceRate($children),
            'children_count' => $children->count(),
        ];
    }
    
    private function getChildrenRecentActivities($children)
    {
        $activities = collect();
        foreach ($children as $child) {
            $activities = $activities->merge($this->getStudentRecentActivities($child)['recent_submissions']);
        }
        return $activities->sortByDesc('created_at')->take(5);
    }
    
    private function getChildrenUpcomingEvents($children)
    {
        $events = collect();
        foreach ($children as $child) {
            $events = $events->merge($this->getStudentUpcomingDeadlines($child));
        }
        return $events->sortBy('due_date')->take(5);
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
            $size = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [$dbName]);
            
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
            $smsService = app(\App\Services\SmsGatewayService::class);
            return $smsService->checkHealth() ? 'online' : 'offline';
        } catch (\Exception $e) {
            return 'offline';
        }
    }
}
