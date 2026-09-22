<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Announcement;
use Illuminate\Support\Collection;

/**
 * Every notice, newest first, as the staff noticeboard admin lists them —
 * including the ones not yet published or already expired, which is what an
 * author needs to see and the family portal must not.
 */
class ListAnnouncementsForStaffAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return Announcement::query()
            ->with('createdBy:id,name')
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Announcement $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'title_dhivehi' => $row->title_dhivehi,
                'title_arabic' => $row->title_arabic,
                'content' => $row->content,
                'type' => $row->type,
                'priority' => $row->priority,
                'target_audience' => $row->target_audience ?: ['all'],
                'target_classes' => $row->target_classes ?: [],
                'publish_date' => $row->publish_date?->toDateString(),
                'expiry_date' => $row->expiry_date?->toDateString(),
                'is_published' => (bool) $row->is_published,
                'author' => $row->createdBy?->name,
            ]);
    }
}
