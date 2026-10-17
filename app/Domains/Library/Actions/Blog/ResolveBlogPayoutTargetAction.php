<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\HasActiveConsentAction;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL6b (§7.3): where a student blogger's payout goes. Never a
 * bank:
 *
 *  - under 18 (or an age the record cannot tell), to the Akuru wallet of the
 *    parent who consented — and only while that consent is in force;
 *  - at 18 or over, to the student's own Akuru wallet, until BL7 makes them
 *    a full writer who is paid as writers are.
 *
 * Null for a full writer, who is paid as today.
 */
class ResolveBlogPayoutTargetAction
{
    /**
     * @return array{method: string, wallet_user_id: int, to_parent: bool}|null
     */
    public function execute(WriterProfile $profile): ?array
    {
        if (! $profile->isStudent()) {
            return null;
        }
        $student = app(ResolveStudentAgeForUserAction::class)->execute((int) $profile->user_id);
        if ($student !== null && $student['age'] !== null && $student['age'] >= 18) {
            return ['method' => 'wallet', 'wallet_user_id' => (int) $profile->user_id, 'to_parent' => false];
        }
        if ($student === null || $profile->guardian_user_id === null
            || ! app(HasActiveConsentAction::class)->execute('student', $student['student_id'], 'blog_publishing')) {
            throw ValidationException::withMessages(['payout' => __('blog.error_payout_no_parent')]);
        }

        return ['method' => 'wallet', 'wallet_user_id' => (int) $profile->guardian_user_id, 'to_parent' => true];
    }
}
