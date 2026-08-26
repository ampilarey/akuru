<?php

namespace App\Domains\Courses\Enums;

enum CertificateKind: string
{
    case CourseCompletion = 'course_completion';
    case OfferingCompletion = 'offering_completion';
    case Assessment = 'assessment';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::CourseCompletion => 'Course completion',
            self::OfferingCompletion => 'Offering completion',
            self::Assessment => 'Assessment',
            self::Manual => 'Manual',
        };
    }
}
