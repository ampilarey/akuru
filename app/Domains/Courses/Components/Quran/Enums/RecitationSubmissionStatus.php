<?php

namespace App\Domains\Courses\Components\Quran\Enums;

/**
 * SPEC §52.19. The ai_* states are reserved for the Pronunciation phases —
 * nothing sets them while rule 8 holds.
 */
enum RecitationSubmissionStatus: string
{
    case Submitted = 'submitted';
    case AiChecked = 'ai_checked';
    case TeacherReviewed = 'teacher_reviewed';
    case SupervisorReviewed = 'supervisor_reviewed';
    case DeanReviewed = 'dean_reviewed';
    case NeedsRepeat = 'needs_repeat';
    case Passed = 'passed';
    case Failed = 'failed';
    case AiProcessedLater = 'ai_processed_later';
}
