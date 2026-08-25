<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\Academics\Models\Timetable;
use Carbon\Carbon;

class RecordApprovedTeacherLeaveAction
{
    public function execute(
        int $teacherId,
        string $from,
        string $to,
        ?string $reason,
        int $createdBy,
        int $approvedBy,
        ?int $requestId = null,
    ): TeacherAbsence {
        $absence = TeacherAbsence::query()->create([
            'teacher_id' => $teacherId,
            'from_date' => $from,
            'to_date' => $to,
            'reason' => $reason,
            'status' => 'approved',
            'note' => $requestId ? 'Created from request #'.$requestId : null,
            'created_by' => $createdBy,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $weekday = strtolower($date->englishDayOfWeek);
            $entries = Timetable::query()
                ->where('teacher_id', $teacherId)
                ->where('is_active', true)
                ->where('day_of_week', $weekday)
                ->whereNotNull('period_id')
                ->get();

            foreach ($entries as $entry) {
                if ($entry->valid_from && $date->lt($entry->valid_from)) {
                    continue;
                }
                if ($entry->valid_until && $date->gt($entry->valid_until)) {
                    continue;
                }

                SubstitutionRequest::query()->firstOrCreate([
                    'timetable_entry_id' => $entry->id,
                    'date' => $date->toDateString(),
                    'absent_teacher_id' => $teacherId,
                ], [
                    'subject_id' => $entry->subject_id,
                    'classroom_id' => $entry->class_id,
                    'period_id' => $entry->period_id,
                    'status' => 'open',
                    'notes' => $requestId ? 'Suggested from approved leave request #'.$requestId : 'Suggested from approved leave',
                ]);
            }
        }

        return $absence;
    }
}
