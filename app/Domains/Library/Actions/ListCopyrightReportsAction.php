<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryCopyrightReport;

/** BP3 (BACKLOG B11): the office's copyright reports, the open ones first, newest first. */
class ListCopyrightReportsAction
{
    /** @return array{reports: list<array<string, mixed>>, open_only: bool, open_count: int} */
    public function execute(bool $openOnly = true): array
    {
        $reports = LibraryCopyrightReport::query()->with('item:id,title,slug,status')
            ->when($openOnly, fn ($q) => $q->where('status', 'open'))
            ->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('id')->limit(500)->get();

        return [
            'reports' => $reports->map(fn (LibraryCopyrightReport $r) => [
                'id' => $r->id,
                'item_title' => $r->item?->title,
                'item_slug' => $r->item?->slug,
                'item_status' => $r->item?->status?->value,
                'name' => $r->name,
                'email' => $r->email,
                'work_claimed' => $r->work_claimed,
                'statement' => $r->statement,
                'status' => $r->status,
                'decision_note' => $r->decision_note,
                'reported_at' => $r->created_at?->toDateTimeString(),
                'decided_at' => $r->decided_at?->toDateTimeString(),
            ])->values()->all(),
            'open_only' => $openOnly,
            'open_count' => LibraryCopyrightReport::query()->where('status', 'open')->count(),
        ];
    }
}
