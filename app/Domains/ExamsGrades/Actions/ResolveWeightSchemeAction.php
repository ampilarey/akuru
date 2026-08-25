<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\AssessmentWeightScheme;

class ResolveWeightSchemeAction
{
    public function execute(int $yearId, ?int $classId, ?int $subjectId): ?AssessmentWeightScheme
    {
        if ($classId !== null && $subjectId !== null) {
            $subject = AssessmentWeightScheme::query()
                ->where('academic_year_id', $yearId)
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->first();

            if ($subject !== null) {
                return $subject;
            }
        }

        if ($classId !== null) {
            $class = AssessmentWeightScheme::query()
                ->where('academic_year_id', $yearId)
                ->where('class_id', $classId)
                ->whereNull('subject_id')
                ->first();

            if ($class !== null) {
                return $class;
            }
        }

        return AssessmentWeightScheme::query()
            ->where('academic_year_id', $yearId)
            ->whereNull('class_id')
            ->whereNull('subject_id')
            ->first();
    }
}
