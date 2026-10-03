<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Instructor;

/**
 * The instructors a course form can assign (C16 slice N6): every profile,
 * active or not, as id, name and whether a staff login is linked — so the
 * office can see which assignments will also open the review queue to
 * somebody.
 */
class ListInstructorOptionsAction
{
    /**
     * @return list<array{id: int, name: string, is_active: bool, has_login: bool}>
     */
    public function execute(): array
    {
        return Instructor::query()->ordered()->get(['id', 'name', 'is_active', 'user_id'])
            ->map(fn (Instructor $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'is_active' => (bool) $row->is_active,
                'has_login' => $row->user_id !== null,
            ])
            ->values()
            ->all();
    }
}
