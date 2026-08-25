<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\DocumentExpiryNotice;
use App\Domains\Identity\Actions\ListUserIdsWithPermissionAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;

class NotifyExpiringDocumentsAction
{
    /**
     * @param  list<int>  $horizons
     */
    public function execute(array $horizons = [90, 60, 30]): int
    {
        $max = max($horizons);
        $documents = app(ExpiringDocumentsReportAction::class)->execute($max);
        $hrUsers = app(ListUserIdsWithPermissionAction::class)->execute('hr.manage');
        $sent = 0;

        foreach ($documents as $document) {
            $daysUntil = (int) $document['days_until'];
            foreach ($horizons as $horizon) {
                if ($daysUntil > $horizon) {
                    continue;
                }

                $already = DocumentExpiryNotice::query()
                    ->where('document_id', $document['id'])
                    ->where('horizon_days', $horizon)
                    ->exists();

                if ($already) {
                    continue;
                }

                $title = 'Document expiring in '.$horizon.' days';
                $message = trim(($document['title'] ?: $document['document_type']).' expires on '.$document['expires_at']);
                $recipients = $hrUsers;
                if (! empty($document['user_id'])) {
                    $recipients[] = (int) $document['user_id'];
                }

                foreach (array_unique($recipients) as $userId) {
                    app(SendUserNotificationAction::class)->execute((int) $userId, $title, $message, [
                        'category' => 'hr',
                        'document_id' => $document['id'],
                        'horizon_days' => $horizon,
                    ]);
                    $sent++;
                }

                DocumentExpiryNotice::query()->create([
                    'document_id' => $document['id'],
                    'horizon_days' => $horizon,
                    'notified_at' => now(),
                ]);
            }
        }

        return $sent;
    }
}
