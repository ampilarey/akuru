<?php

namespace App\Domains\ExamsGrades\Enums;

enum ExamStatus: string
{
    case Scheduled = 'scheduled';
    case MarksEntry = 'marks_entry';
    case Review = 'review';
    case Published = 'published';
    case Locked = 'locked';

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Scheduled => [self::MarksEntry],
            self::MarksEntry => [self::Review],
            self::Review => [self::Published],
            self::Published => [self::Locked],
            self::Locked => [],
        };
    }

    public function allowsEdits(): bool
    {
        return $this !== self::Locked;
    }
}
