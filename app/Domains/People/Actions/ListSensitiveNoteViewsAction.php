<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\SensitiveNoteView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who has read this child's sensitive notes.
 *
 * The access log is shown **in the same screen as the notes**, not buried in
 * an admin audit page nobody opens. Somebody about to read a child's welfare
 * record should be able to see that their own name will appear on this list.
 */
class ListSensitiveNoteViewsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId, int $limit = 25): Collection
    {
        $views = SensitiveNoteView::query()
            ->where('student_id', $studentId)
            ->orderByDesc('viewed_at')
            ->limit($limit)
            ->get();

        if ($views->isEmpty()) {
            return collect();
        }

        $users = DB::table('users')
            ->whereIn('id', $views->pluck('viewed_by')->all())
            ->pluck('name', 'id');

        return $views->map(fn (SensitiveNoteView $view): array => [
            'id' => (int) $view->id,
            'who' => $users->get((int) $view->viewed_by) ?? 'Unknown',
            'at' => $view->viewed_at?->toDateTimeString(),
        ])->values();
    }
}
