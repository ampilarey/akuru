<?php

namespace App\Domains\Courses\Enums;

enum CourseWorkflowStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview],
            self::InReview => [self::Draft, self::Published],
            self::Published => [self::Archived],
            self::Archived => [],
        };
    }
}
