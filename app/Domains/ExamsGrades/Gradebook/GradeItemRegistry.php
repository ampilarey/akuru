<?php

namespace App\Domains\ExamsGrades\Gradebook;

use App\Domains\ExamsGrades\Contracts\GradeItemContract;
use App\Domains\ExamsGrades\Contracts\GradeItemProvider;

class GradeItemRegistry
{
    /** @var list<GradeItemProvider> */
    private array $providers = [];

    public function register(GradeItemProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @param  list<int>  $studentIds
     * @return list<GradeItemContract>
     */
    public function items(int $classId, int $subjectId, int $termId, array $studentIds): array
    {
        $items = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->items($classId, $subjectId, $termId, $studentIds) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
