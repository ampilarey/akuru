<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\ListConsentingGuardiansAction;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;

/**
 * BLOG_PLAN BL1: what *My blog* shows a person. One of:
 *  - `blog`: their student blog is approved;
 *  - `writer`: they write as a full writer, and their blog comes with it;
 *  - `request`: a request of theirs is waiting, or was declined or refused;
 *  - `start`: they may ask;
 *  - `not_student`: blogs here start from a student record.
 */
class PresentStudentBlogAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId): array
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->first();
        if ($profile !== null) {
            return [
                'state' => $profile->isStudent() ? 'blog' : 'writer',
                'blog' => [
                    'pen_name' => $profile->display_name,
                    'blog_title' => $profile->blog_title,
                    'about' => $profile->bio,
                    'slug' => $profile->slug,
                    'since' => $profile->approved_at?->toDateString(),
                    // BL5: the comments switch, forced off under 13, and the comments on the posts (read only).
                    'comments_enabled' => (bool) $profile->comments_enabled,
                    'under_13' => app(BlogPageRulesAction::class)->underThirteen($profile),
                    'comments' => app(ListCommentsAction::class)->forWriter((int) $profile->id),
                    // BL6a: the tip box switch and the latest tips.
                    'tips_enabled' => (bool) $profile->tips_enabled,
                    'tips' => app(ListTipsAction::class)->forWriter((int) $profile->id, 20),
                    // BL6b: a student's earnings and payout, to a wallet only.
                    'earnings' => $profile->isStudent() ? app(PresentBlogEarningsAction::class)->execute($profile) : null,
                ],
            ];
        }

        $student = app(ResolveStudentAgeForUserAction::class)->execute($userId);
        if ($student === null) {
            return ['state' => 'not_student'];
        }

        $request = app(ListBlogRequestsAction::class)->latestFor($userId);
        $under18 = $student['age'] === null || $student['age'] < 18;
        $base = [
            'under_18' => $under18,
            // Under 18 with no verified parent on the record, nobody can say yes.
            'guardian_linked' => ! $under18 || app(ListConsentingGuardiansAction::class)->execute($student['student_id']) !== [],
        ];

        if ($request !== null && ($request->status->isOpen() || $request->created_at?->gt(now()->subDays(30)))) {
            return $base + [
                'state' => 'request',
                'request' => [
                    'status' => $request->status->value,
                    'pen_name' => $request->pen_name,
                    'blog_title' => $request->blog_title,
                    'decision_note' => $request->decision_note,
                    'created_at' => $request->created_at?->toDateString(),
                    'can_ask_again' => ! $request->status->isOpen(),
                ],
            ];
        }

        return $base + ['state' => 'start'];
    }
}
