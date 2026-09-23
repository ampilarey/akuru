<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Academics\Actions\ListClassesTaughtByUserAction;
use App\Domains\Notifications\Models\MessageThread;
use App\Domains\People\Actions\ListFamilyUserIdsForStudentsAction;
use Illuminate\Validation\ValidationException;

/**
 * E2b — a member of staff writes to one class's families.
 *
 * This is what the reply policy in StartMessageThreadAction was written for.
 * Until now nothing could create a wide thread — the portal compose form passes
 * exactly one recipient — so the author-only default above five recipients had
 * no way to fire. Class fan-out is the case it exists to handle.
 *
 * The thread is filed against the class it concerns via the morph alias
 * `class_room` (ADR-005), so a reply can later be shown in the context of that
 * class rather than floating free.
 */
class StartClassMessageThreadAction
{
    public function execute(
        int $authorId,
        int $classId,
        string $subject,
        string $body,
        string $audience = ListFamilyUserIdsForStudentsAction::AUDIENCE_GUARDIANS,
        ?array $poll = null,
    ): MessageThread {
        $class = app(ListClassesTaughtByUserAction::class)
            ->execute($authorId)
            ->firstWhere('id', $classId);

        // Authorisation is the same rule as the directory: you may address the
        // classes you teach, and no others.
        if ($class === null) {
            throw ValidationException::withMessages([
                'class_id' => 'You do not teach that class.',
            ]);
        }

        $recipients = app(ListFamilyUserIdsForStudentsAction::class)
            ->execute($class['student_ids'], $audience);

        if ($recipients === []) {
            throw ValidationException::withMessages([
                'class_id' => 'Nobody in that class has an account to receive this.',
            ]);
        }

        return app(StartMessageThreadAction::class)->execute(
            $authorId,
            $recipients,
            $subject,
            $body,
            // A class send is an all-parents send, and the plan's rule for
            // those is reply-all OFF (E2, "reply-all is off by default for
            // school-wide or all-parents sends"). This used to leave the
            // policy to the >5 default, so a class with three guardian
            // accounts got `all`: one family's reply reached the other two,
            // and each saw the others by name. The family walk found it
            // (STATUS §5fp). The size rule still governs person threads.
            [
                'context_type' => 'class_room',
                'context_id' => $classId,
                'reply_policy' => 'author_only',
                'poll' => $poll,
            ],
        );
    }
}
