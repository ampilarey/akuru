<?php

namespace App\Domains\Courses\Enums;

enum QuestionType: string
{
    case McqSingle = 'mcq_single';
    case McqMultiple = 'mcq_multiple';
    case TrueFalse = 'true_false';
    case FillBlank = 'fill_blank';
    case Audio = 'audio';
    case Image = 'image';
    case Matching = 'matching';
    case Arrange = 'arrange';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';
    case TeacherMarked = 'teacher_marked';
    case FileSubmission = 'file_submission';

    public function pattern(): ActivityPattern
    {
        return match ($this) {
            self::McqSingle,
            self::McqMultiple,
            self::TrueFalse,
            self::Audio,
            self::Image => ActivityPattern::Selection,
            self::FillBlank, self::ShortAnswer => ActivityPattern::TextInput,
            // SPEC §17 lists "Match pairs" under Pattern 3, not Pattern 1.
            // Matching was routed to Selection, which compares an unordered set
            // of ids — so a matching question's *pairing* was never checked: a
            // student who picked every right item and paired them all wrongly
            // scored full marks.
            self::Matching,
            self::Arrange => ActivityPattern::Arrange,
            self::Essay, self::TeacherMarked, self::FileSubmission => ActivityPattern::TeacherMarked,
        };
    }
}
