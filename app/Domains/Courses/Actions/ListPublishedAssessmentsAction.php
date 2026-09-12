<?php

namespace App\Domains\Courses\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Published assessments, as options for anything that has to name one.
 *
 * Two screens need this list — the certificate template builder (SPEC §27's
 * "Pass final assessment") and the offering-level rule override (SPEC §39) —
 * and Offerings must not read Courses' tables itself (rule 3), so the query
 * lives here once rather than being written out twice.
 *
 * @return Collection<int, array{id: int, course_id: int|null, title: string}>
 */
class ListPublishedAssessmentsAction
{
    /**
     * @return Collection<int, array{id: int, course_id: int|null, title: string}>
     */
    public function execute(): Collection
    {
        return DB::table('assessments')
            ->where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'course_id', 'title'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'course_id' => $row->course_id === null ? null : (int) $row->course_id,
                'title' => (string) $row->title,
            ])
            ->values();
    }
}
