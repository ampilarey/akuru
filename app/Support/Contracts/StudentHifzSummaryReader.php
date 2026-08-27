<?php

namespace App\Support\Contracts;

interface StudentHifzSummaryReader
{
    /**
     * Read-only Hifz snapshot for portal composition. Never used to mutate Hifz.
     *
     * @param  list<int>  $studentIds
     * @return list<array<string, mixed>>
     */
    public function summariesForStudents(array $studentIds): array;
}
