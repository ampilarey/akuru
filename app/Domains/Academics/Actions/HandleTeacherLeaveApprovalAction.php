<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\RequestTypeHandler;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\Academics\Models\Timetable;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class HandleTeacherLeaveApprovalAction implements RequestTypeHandler
{
    public function onApproved(SchoolRequest $request): void
    {
        $payload = $request->payload ?? [];
        $teacherId = (int) ($payload['teacher_id'] ?? $request->regarding_id ?? 0);
        $from = (string) ($payload['from_date'] ?? '');
        $to = (string) ($payload['to_date'] ?? $from);

        if ($teacherId < 1 || $from === '') {
            throw ValidationException::withMessages([
                'payload' => 'Teacher leave needs teacher_id and from_date.',
            ]);
        }

        TeacherAbsence::query()->create([
            'teacher_id' => $teacherId,
            'from_date' => $from,
            'to_date' => $to,
            'reason' => $request->reason,
            'status' => 'approved',
            'note' => 'Created from request #'.$request->id,
            'created_by' => $request->requester_id,
            'approved_by' => $request->reviewed_by,
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
                    'notes' => 'Suggested from approved leave request #'.$request->id,
                ]);
            }
        }
    }
}
