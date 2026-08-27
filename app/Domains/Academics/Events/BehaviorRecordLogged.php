<?php

namespace App\Domains\Academics\Events;

class BehaviorRecordLogged
{
    public function __construct(
        public int $recordId,
        public int $studentId,
        public string $studentName,
        public string $type,
        public string $date,
    ) {}
}
