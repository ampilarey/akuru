<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\HasActiveConsentAction;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;

/**
 * BLOG_PLAN BL3b (§5, §9): what a blog page may carry, from the consents on
 * the student's record — asked fresh each time, so a consent withdrawn on the
 * record takes the links off at once.
 *
 *  - a picture: a full writer always; a student only where the parent said
 *    the photo may show (`show_photo`, BL1);
 *  - links: a full writer, or a student of 18 or over, always; a student
 *    under 18 (or of an age the record cannot tell) only with the parent's
 *    `blog_links` consent in force.
 */
class BlogPageRulesAction
{
    public function photoAllowed(WriterProfile $profile): bool
    {
        return ! $profile->isStudent() || (bool) $profile->show_photo;
    }

    /**
     * BL5 (D13): whether a blog takes comments — the writer's switch, and
     * never under 13 (nor at an age the record cannot tell).
     */
    public function commentsOpen(WriterProfile $profile): bool
    {
        return (bool) ($profile->comments_enabled ?? true) && ! $this->underThirteen($profile);
    }

    public function underThirteen(WriterProfile $profile): bool
    {
        if (! $profile->isStudent()) {
            return false;
        }
        $age = app(ResolveStudentAgeForUserAction::class)->execute((int) $profile->user_id)['age'] ?? null;

        return $age === null || $age < 13;
    }

    public function linksAllowed(WriterProfile $profile): bool
    {
        if (! $profile->isStudent()) {
            return true;
        }
        $student = app(ResolveStudentAgeForUserAction::class)->execute((int) $profile->user_id);
        if ($student === null) {
            return false;
        }
        if ($student['age'] !== null && $student['age'] >= 18) {
            return true;
        }

        return app(HasActiveConsentAction::class)->execute('student', $student['student_id'], 'blog_links');
    }
}
