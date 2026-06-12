<?php

namespace App\Services\Hifz;

use App\Enums\Hifz\HifzOverallStatus;
use App\Models\HifzEnrollment;
use App\Models\HifzMilestone;
use App\Models\HifzSession;
use App\Models\HifzSessionRecord;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class HifzReportService
{
    public function weakStudents(?Collection $studentIds = null): Collection
    {
        $query = HifzSessionRecord::query()
            ->where('overall_status', HifzOverallStatus::Weak)
            ->where('created_at', '>=', now()->subDays(30));

        if ($studentIds?->isNotEmpty()) {
            $query->whereIn('student_id', $studentIds);
        }

        return $query->with('student.user')
            ->selectRaw('student_id, count(*) as weak_count')
            ->groupBy('student_id')
            ->orderByDesc('weak_count')
            ->get();
    }

    public function harakaMistakeLeaders(?Collection $studentIds = null, int $days = 30): Collection
    {
        $query = HifzSessionRecord::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('haraka_mistakes', '>', 0);

        if ($studentIds?->isNotEmpty()) {
            $query->whereIn('student_id', $studentIds);
        }

        return $query->with('student.user')
            ->selectRaw('student_id, sum(haraka_mistakes) as total_haraka')
            ->groupBy('student_id')
            ->orderByDesc('total_haraka')
            ->get();
    }

    public function teachersMissingTodayRecords(?Collection $programIds = null): Collection
    {
        $teacherIds = HifzEnrollment::query()
            ->when($programIds?->isNotEmpty(), fn ($q) => $q->whereIn('hifz_program_id', $programIds))
            ->where('status', 'active')
            ->distinct()
            ->pluck('teacher_id')
            ->filter();

        $recorded = HifzSession::whereDate('session_date', today())
            ->when($programIds?->isNotEmpty(), fn ($q) => $q->whereIn('hifz_program_id', $programIds))
            ->pluck('teacher_id');

        return Teacher::whereIn('id', $teacherIds->diff($recorded))->with('user')->get();
    }

    public function pendingSupervisorReviews(?Collection $programIds = null): int
    {
        return HifzSessionRecord::where('requires_supervisor_review', true)
            ->whereNull('reviewed_at')
            ->when($programIds?->isNotEmpty(), fn ($q) => $q->whereIn('hifz_program_id', $programIds))
            ->count();
    }

    public function parentAttentionCases(?Collection $studentIds = null): Collection
    {
        $query = HifzSessionRecord::where('requires_parent_attention', true)
            ->where('created_at', '>=', now()->subDays(14));

        if ($studentIds?->isNotEmpty()) {
            $query->whereIn('student_id', $studentIds);
        }

        return $query->with(['student.user', 'session'])->latest()->get();
    }

    public function monthlyJuzCompletions(): int
    {
        return HifzMilestone::where('type', 'juz_completed')
            ->where('status', 'approved')
            ->whereMonth('approved_at', now()->month)
            ->whereYear('approved_at', now()->year)
            ->count();
    }
}
