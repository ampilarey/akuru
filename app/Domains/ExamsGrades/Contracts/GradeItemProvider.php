<?php

namespace App\Domains\ExamsGrades\Contracts;

interface GradeItemProvider
{
    /**
     * @param  list<int>  $studentIds
     * @return list<GradeItemContract>
     */
    public function items(int $classId, int $subjectId, int $termId, array $studentIds): array;
}
