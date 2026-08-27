<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;

class PresentCourseTitlesAction
{
    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function execute(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $clean[] = $intId;
            }
        }
        $clean = array_values(array_unique($clean));
        if ($clean === []) {
            return [];
        }

        return Course::query()
            ->whereIn('id', $clean)
            ->pluck('title', 'id')
            ->all();
    }
}
