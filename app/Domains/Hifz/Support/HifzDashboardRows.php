<?php

namespace App\Domains\Hifz\Support;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSessionRecord;
use Illuminate\Support\Collection;

/**
 * The rows the five Hifz dashboards hand their Inertia pages (the Hifz
 * port's second slice, STATUS §5jw): plain arrays with the names already
 * resolved, so no page reaches into a model. Kept beside the controllers
 * that share it; it presents, it decides nothing.
 */
final class HifzDashboardRows
{
    /**
     * @param  Collection<int, object>  $rows  a report's haraka leaders or weak students
     * @return list<array{student: string, total_haraka?: int, weak_count?: int}>
     */
    public static function harakaLeaders(Collection $rows): array
    {
        return $rows->map(fn (object $row): array => [
            'student' => $row->student->full_name ?? 'Student',
            'total_haraka' => (int) ($row->total_haraka ?? 0),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{student: string, weak_count: int}>
     */
    public static function weakStudents(Collection $rows): array
    {
        return $rows->map(fn (object $row): array => [
            'student' => $row->student->full_name ?? 'Student',
            'weak_count' => (int) ($row->weak_count ?? 0),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, HifzMilestone>  $milestones
     * @return list<array{id: int, student: string, type: string, title: ?string, approved_at: ?string}>
     */
    public static function milestones(Collection $milestones): array
    {
        return $milestones->map(fn (HifzMilestone $milestone): array => [
            'id' => (int) $milestone->id,
            'student' => $milestone->student?->full_name ?? 'Student',
            // "surah completed", as the Blade always read it.
            'type' => str_replace('_', ' ', $milestone->type?->value ?? 'milestone'),
            'title' => $milestone->title,
            'approved_at' => $milestone->approved_at?->format('d M Y'),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, HifzSessionRecord>  $records
     * @return list<array<string, mixed>>
     */
    public static function records(Collection $records): array
    {
        return $records->map(fn (HifzSessionRecord $record): array => [
            'id' => (int) $record->id,
            'date' => $record->session?->session_date?->format('d M Y'),
            'day' => $record->session?->session_date?->format('D d M'),
            'attendance' => $record->attendance_status?->value,
            'overall' => $record->overall_status?->value,
            'teacher_note' => $record->teacher_note,
            'parent_visible_note' => $record->parent_visible_note,
            'next_target' => $record->next_target,
            'requires_parent_attention' => (bool) $record->requires_parent_attention,
        ])->values()->all();
    }
}
