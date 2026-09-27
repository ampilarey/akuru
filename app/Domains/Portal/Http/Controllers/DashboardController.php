<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Hifz\Models\QuranProgress;
// People's own counting actions rather than People's models: asking the owning
// domain the question is what rule 3 is for, and it is also the only place the
// difference between "on the roll" and "ever enrolled" is written down.
use App\Domains\People\Actions\CountStudentsAction;
use App\Domains\People\Actions\CountTeachersAction;
use App\Domains\Portal\Actions\ComposeDashboardPrayerAction;
use App\Http\Controllers\Controller;
use App\Support\Navigation\ResolveWorkspacesAction;
use App\Support\Navigation\WorkspaceMap;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (! $user) {
            return redirect()->route('login');
        }

        // `/dashboard` sends a person to the home of their active workspace
        // (`WorkspaceMap`, STATUS §5id): the Institute, the School, a
        // teacher's day, the family portal, a shop, a writer's desk. Which
        // one is theirs when they hold several is the map's order — staff
        // first (E7) — or the one they last switched to. Only a person with
        // no role at all is shown a page here: the public course dashboard.
        $workspaces = app(ResolveWorkspacesAction::class)->execute($user);
        $active = collect($workspaces['list'])->firstWhere('key', $workspaces['active']);

        $response = ($active === null || $active['key'] === WorkspaceMap::ACCOUNT)
            ? $this->publicUserDashboard()
            : redirect($active['href']);

        // `/dashboard` is a pure router: for most people it does not render
        // anything, it works out where they belong and sends them on. A flash
        // message aimed at that destination would otherwise be consumed *here*
        // and never seen — which is exactly what happened to E7's "you are now
        // signed in as …" until a browser walk caught it. Found this way and
        // not by any test, because every test asserted the redirect rather
        // than what the person reads at the end of it.
        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            session()->reflash();
        }

        return $response;
    }

    /**
     * `/dashboard/numbers`: the super admin's full dashboard (route-gated to
     * the role). Inertia since C9 slice 13 (STATUS §5jo), every string keyed.
     */
    public function numbers(): Response
    {
        $prayer = app(ComposeDashboardPrayerAction::class)->execute();
        $current = $prayer['currentPrayer'];

        return Inertia::render('Portal/NumbersDashboard', [
            'stats' => $this->superAdminStats(),
            'health' => $this->getSystemHealth(),
            'recent' => $this->recentEnrollments(),
            'today' => now()->format('l, d F Y'),
            'islamic_date' => ['day' => $prayer['islamicDate']['day'] ?? '', 'month_name' => $prayer['islamicDate']['month_name'] ?? '', 'year' => $prayer['islamicDate']['year'] ?? ''],
            'prayer_times' => array_map(fn ($time) => is_object($time) ? $time->format('H:i') : $time, $prayer['prayerTimes']),
            'current_prayer' => ['prayer' => $current['prayer'] ?? null, 'time' => is_object($current['time'] ?? null) ? $current['time']->format('H:i') : ($current['time'] ?? null)],
            'home' => route('admin.index'),
            'links' => ['courses' => route('admin.courses.index'), 'enrollments' => route('admin.enrollments.index'), 'users' => route('admin.users.index'), 'settings' => route('admin.settings.index'), 'website' => route('public.home'), 'logout' => route('logout')],
            't' => trans('admin'),
        ]);
    }

    /**
     * `/dashboard/supervisor`: the supervisor's full dashboard (route-gated to
     * the role). Inertia since C9 slice 13 (STATUS §5jo).
     */
    public function supervisor(): Response
    {
        return Inertia::render('Portal/SupervisorDashboard', [
            'stats' => $this->supervisorStats(),
            'can_hifz' => (bool) auth()->user()?->can('view_hifz_programs'),
            'hifz_href' => route('hifz.supervisor.dashboard'),
            'home' => route('school.index'),
            't' => trans('admin'),
        ]);
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

        // `users.password` is NOT NULL and an OTP-only account is created with
        // a random 40-character hash (AccountResolverService), so
        // `! empty($user->password)` was **always true** and the "Set a
        // password for easier login" banner this feeds never rendered for
        // anybody. The feature was unreachable through its own entry point.
        //
        // `force_password_change` is the flag that actually records "there is
        // a password here but nobody knows it".
        $hasPassword = ! $user->force_password_change;

        return view('dashboard.public-user', compact(
            'user',
            'enrollments',
            'activeEnrollments',
            'pendingEnrollments',
            'openCourses',
            'hasPassword'
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function superAdminStats(): array
    {
        // Eleven values used to be computed here and thrown away: this page's
        // view reads twelve `$stats` keys and exactly one `$metrics` key
        // (`system_health`), and nothing else — no partial, no include, no
        // dynamic lookup. `total_students`, `total_teachers`,
        // `active_quran_students`, `total_assignments`, `total_announcements`,
        // `sms_usage_today`, `student_growth`, `quran_progress_stats`,
        // `attendance_rate`, `recent_activities` and `sms_gateway_status` were
        // queried on every load of the admin landing page and rendered nowhere.
        //
        // One of them was worse than wasted. `getOverallAttendanceRate()`
        // returned a hardcoded **85.5** with `// Placeholder` beside it: an
        // invented figure sitting in a variable called `attendance_rate`,
        // waiting for somebody to put it on a screen. It has never been
        // displayed — that is luck, not design — and it is gone rather than
        // left for the next person to wire up in good faith.
        //
        // `getStudentGrowthMetrics()` goes with it, and was wrong in its own
        // right: `whereMonth` with no `whereYear` counts that month in *every*
        // year, so "this month against last month" compared two multi-year
        // totals and, each January, compared January to a December that
        // included every December on record.
        $stats = [
            'total_users' => \App\Domains\Identity\Models\User::count(),
            'database_size' => $this->getDatabaseSize(),
            // Course & enrollment stats
            'total_courses' => \App\Domains\Courses\Models\Course::count(),
            'open_courses' => \App\Domains\Courses\Models\Course::where('status', 'open')->count(),
            'total_enrollments' => \App\Domains\Courses\Models\CourseEnrollment::count(),
            'pending_enrollments' => \App\Domains\Courses\Models\CourseEnrollment::whereIn('status', ['pending', 'pending_payment'])->count(),
            'active_enrollments' => \App\Domains\Courses\Models\CourseEnrollment::where('status', 'active')->count(),
            'enrollments_today' => \App\Domains\Courses\Models\CourseEnrollment::whereDate('created_at', today())->count(),
            'revenue_total' => number_format((float) \App\Domains\Finance\Models\Payment::where('status', 'paid')->sum('amount'), 0),
            'revenue_today' => number_format((float) \App\Domains\Finance\Models\Payment::where('status', 'paid')->whereDate('created_at', today())->sum('amount'), 0),
            'new_users_today' => \App\Domains\Identity\Models\User::whereDate('created_at', today())->count(),
            'new_users_this_month' => \App\Domains\Identity\Models\User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        return $stats;
    }

    /**
     * The last ten enrolments, as the dashboard lists them.
     *
     * @return list<array<string, mixed>>
     */
    private function recentEnrollments(): array
    {
        return \App\Domains\Courses\Models\CourseEnrollment::with(['student', 'course', 'payment'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($enrollment) => [
                'id' => $enrollment->id,
                'student' => $enrollment->student?->full_name,
                'course' => $enrollment->course?->title,
                'status' => (string) $enrollment->status,
                'fee' => $enrollment->payment?->amount ? number_format($enrollment->payment->amount, 0) : null,
                'date' => $enrollment->created_at?->format('d M'),
            ])
            ->values()
            ->all();
    }

    /**
     * E1b: a teacher now has a home rather than being dropped straight into the
     * register list. The list is a task queue; it answered "what do I owe" and
     * nothing else, so a teacher had no glanceable view of their day, their
     * unread messages, or a notice aimed at them. The home's first tile still
     * links to it.
     */
    private function teacherDashboard()
    {
        return redirect()->route('portal.teacher');
    }

    /**
     * KNOWN_ISSUES #22 was filed against this screen as a cosmetic complaint —
     * the counters are institute-wide rather than "Grade 5 A". Auditing it
     * turned up something worse than cosmetics: the two numbers were wrong.
     *
     * `Student::count()` and `Teacher::count()` count every row, so a tile
     * headed **Students** included pupils who had graduated, transferred or
     * withdrawn, and one headed **Teachers** included staff whose employment
     * had ended. A supervisor reading "28 students" was reading the number of
     * student records, which is not a fact about the school.
     *
     * Whether this screen should instead be scoped to a class remains the IA
     * decision it was filed as; a wrong number is not.
     */
    /**
     * @return array{students_on_roll: int, teachers_teaching: int, quran_progress_today: int}
     */
    private function supervisorStats(): array
    {
        return [
            'students_on_roll' => app(CountStudentsAction::class)->onTheRoll(),
            'teachers_teaching' => app(CountTeachersAction::class)->teaching(),
            'quran_progress_today' => QuranProgress::whereDate('created_at', today())->count(),
        ];
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

        // Both return false when the call fails — an unreadable mount, or an
        // open_basedir restriction. `false` is 0, and 0/0 is a
        // DivisionByZeroError, which is a 500 on the super-admin dashboard
        // rather than the health line it was asked for. `getDatabaseSize()`
        // right above already wraps its call; this one did not.
        if ($total === false || $free === false || $total <= 0) {
            return 'unknown';
        }

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
