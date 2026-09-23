<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\SchoolRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The requests screen's rows: everything for a reviewer, only their own
 * for anyone else — with the names a card needs to be read without a
 * lookup. Until the requests walk (STATUS §5fw) a card was type, status
 * and reason: the office could not tell which family asked or which pupil
 * it was about, and a family whose request was decided saw the word and
 * neither the date nor the reviewer's reason.
 *
 * Names come from People's tables by DB read (rule 3), in two queries for
 * the whole list rather than one per row.
 */
class ListSchoolRequestsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $userId, bool $canReview): Collection
    {
        $rows = SchoolRequest::query()
            ->when(! $canReview, fn ($query) => $query->where('requester_id', $userId))
            ->orderByDesc('id')
            ->get();

        $requesters = DB::table('users')
            ->whereIn('id', $rows->pluck('requester_id')->unique()->all())
            ->pluck('name', 'id');

        $students = DB::table('students')
            ->whereIn('id', $rows->where('regarding_type', 'student')->pluck('regarding_id')->unique()->all())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $rows->map(function (SchoolRequest $row) use ($requesters, $students): array {
            $student = $row->regarding_type === 'student' ? ($students[(int) $row->regarding_id] ?? null) : null;

            return [
                'id' => $row->id,
                'type' => $row->type?->value,
                'status' => $row->status?->value,
                'reason' => $row->reason,
                'payload' => $row->payload,
                'review_notes' => $row->review_notes,
                'requester_name' => (string) ($requesters[(int) $row->requester_id] ?? ''),
                'regarding_name' => $student ? trim($student->first_name.' '.$student->last_name) : null,
                'submitted_at' => $row->created_at?->toDateString(),
                'reviewed_at' => $row->reviewed_at?->toDateString(),
            ];
        })->values();
    }
}
