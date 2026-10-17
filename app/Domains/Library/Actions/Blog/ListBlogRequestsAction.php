<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\BlogRequestStatus;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\People\Actions\ListConsentingGuardiansAction;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL1: blog requests as the office, a parent and the student see
 * them. The office sees the student's real name and number beside the pen
 * name, so it knows who it approves; nobody else does.
 */
class ListBlogRequestsAction
{
    /**
     * The office's list, open requests first, oldest first among them.
     *
     * @return list<array<string, mixed>>
     */
    public function office(?string $status = null, int $limit = 500): array
    {
        $rows = BlogRequest::query()
            ->when($status !== null && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status in ('waiting_office', 'waiting_parent') then 0 else 1 end")
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $students = app(ListStudentsByIdsAction::class)->execute($rows->pluck('student_id')->all())->keyBy('id');
        $userIds = $rows->pluck('guardian_user_id')->merge($rows->pluck('decided_by'))->filter()->unique()->values()->all();
        $names = $userIds === [] ? collect() : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        $guardians = app(ListConsentingGuardiansAction::class);

        return $rows->map(fn (BlogRequest $r) => [
            'id' => (int) $r->id,
            'pen_name' => $r->pen_name,
            'blog_title' => $r->blog_title,
            'about' => $r->about,
            'under_18' => (bool) $r->under_18,
            'status' => $r->status->value,
            'student_name' => $students[$r->student_id]['name'] ?? null,
            'student_number' => $students[$r->student_id]['student_number'] ?? null,
            'guardian_name' => $r->guardian_user_id ? ($names[$r->guardian_user_id] ?? null) : null,
            // Under 18 with nobody verified to ask: the office has to link a parent first.
            'no_guardian' => $r->under_18 && $r->status === BlogRequestStatus::WaitingParent && $guardians->execute((int) $r->student_id) === [],
            'show_photo' => (bool) $r->show_photo,
            'show_school' => (bool) $r->show_school,
            'decided_by' => $r->decided_by ? ($names[$r->decided_by] ?? null) : null,
            'decided_at' => $r->decided_at?->toDateTimeString(),
            'decision_note' => $r->decision_note,
            'created_at' => $r->created_at?->toDateTimeString(),
        ])->values()->all();
    }

    /** The latest request a student made, by their account. */
    public function latestFor(int $userId): ?BlogRequest
    {
        return BlogRequest::query()->where('user_id', $userId)->latest('id')->first();
    }

    /**
     * For a parent's children: each student's latest request, by student id.
     *
     * @param  list<int>  $studentIds
     * @return array<int, array{id: int, status: string, pen_name: string, blog_title: string}>
     */
    public function latestForStudents(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }
        $out = [];
        foreach (BlogRequest::query()->whereIn('student_id', $studentIds)->orderBy('id')->get() as $r) {
            $out[(int) $r->student_id] = ['id' => (int) $r->id, 'status' => $r->status->value, 'pen_name' => $r->pen_name, 'blog_title' => $r->blog_title];
        }

        return $out;
    }
}
