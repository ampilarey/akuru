<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterEarning;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL6a: tips as their writer and the office see them — paid tips
 * and their reversals, with the writer's share. The writer sees a note only
 * once the office published it, and never who tipped beyond a first name.
 */
class ListTipsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forWriter(int $writerId, int $limit = 200): array
    {
        $rows = $this->rows(fn ($q) => $q->where('writer_id', $writerId), $limit);
        $names = DB::table('users')->whereIn('id', $rows->pluck('user_id')->unique()->all())->pluck('name', 'id')
            ->map(fn ($name) => (preg_split('/\s+/u', trim((string) $name)) ?: [''])[0]);

        return $rows->map(fn (LibraryTip $tip) => $this->shape($tip) + [
            'from' => (string) ($names[$tip->user_id] ?? '—'),
            'note' => $tip->note_status === 'published' ? $tip->note : null,
        ])->values()->all();
    }

    /**
     * The office's list, newest first; `notes` lists only notes waiting.
     *
     * @return list<array<string, mixed>>
     */
    public function office(bool $notesWaiting = false, int $limit = 500): array
    {
        $rows = $this->rows(fn ($q) => $q->when($notesWaiting, fn ($w) => $w->where('note_status', 'pending')), $limit);
        $names = DB::table('users')->whereIn('id', $rows->pluck('user_id')->unique()->all())->pluck('name', 'id');

        return $rows->map(fn (LibraryTip $tip) => $this->shape($tip) + [
            'from' => (string) ($names[$tip->user_id] ?? '—'),
            'blog' => $tip->writer?->display_name,
            'note' => $tip->note,
            'note_status' => $tip->note_status,
        ])->values()->all();
    }

    /** @return \Illuminate\Support\Collection<int, LibraryTip> */
    private function rows(callable $scope, int $limit)
    {
        return LibraryTip::query()->with(['writer', 'item'])->whereIn('status', ['paid', 'refunded'])->tap($scope)->orderByDesc('id')->limit($limit)->get();
    }

    /** @return array<string, mixed> */
    private function shape(LibraryTip $tip): array
    {
        $earning = $tip->reverses_tip_id === null ? WriterEarning::query()->where('library_tip_id', $tip->id)->first() : null;

        return [
            'id' => $tip->id,
            'amount' => (float) $tip->amount,
            'method' => $tip->method,
            'status' => $tip->status,
            'refund_of' => $tip->reverses_tip_id,
            'post' => $tip->item?->title,
            'writer_share' => $earning ? (float) $earning->writer_amount : null,
            'earning_status' => $earning?->status,
            'at' => ($tip->paid_at ?? $tip->created_at)?->toDateTimeString(),
        ];
    }
}
