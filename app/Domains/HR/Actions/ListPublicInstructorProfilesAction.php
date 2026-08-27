<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Instructor;

class ListPublicInstructorProfilesAction
{
    /**
     * @return list<array{id: int, slug: string, name: string, bio: ?string, photo_url: ?string, qualification: ?string, specialization: ?string, is_active: bool}>
     */
    public function execute(): array
    {
        $reader = app(ReadPublicInstructorProfileAction::class);

        return Instructor::query()
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->map(fn (Instructor $row) => $reader->present($row))
            ->values()
            ->all();
    }
}
