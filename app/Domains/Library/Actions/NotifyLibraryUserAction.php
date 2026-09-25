<?php

namespace App\Domains\Library\Actions;

use App\Domains\Notifications\Actions\SendUserNotificationAction;

/**
 * LIBRARY_PLAN §41, the Library's notifications, through Notifications'
 * one writer (rule 3: another domain's Action, never its model). In-app
 * only: the portal's Notifications page lists them with a link. Category
 * `library`, which a person can switch off in their preferences.
 *
 *  - writer: application decided, submission received, changes requested,
 *    rejected, published, new sale, payout decided;
 *  - reader: access granted after a purchase;
 *  - office: new writer application, new submission.
 *
 * Every call is fire-and-forget: a notification that fails must never fail
 * the decision it describes.
 */
class NotifyLibraryUserAction
{
    public function execute(int $userId, string $title, string $message, ?string $href = null): void
    {
        try {
            app(SendUserNotificationAction::class)->execute($userId, $title, $message, [
                'category' => 'library',
                'href' => $href,
            ]);
        } catch (\Throwable) {
            // Recorded nowhere on purpose: the caller's transaction matters more.
        }
    }

    /** Everyone who runs the Library: the holders of `library.manage`. */
    public function office(string $title, string $message, ?string $href = null): void
    {
        $userModel = config('auth.providers.users.model');
        try {
            $ids = $userModel::query()->permission('library.manage')->pluck('id');
        } catch (\Throwable) {
            return;
        }
        foreach ($ids as $id) {
            $this->execute((int) $id, $title, $message, $href);
        }
    }
}
