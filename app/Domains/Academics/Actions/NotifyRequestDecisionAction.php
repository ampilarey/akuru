<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Notifications\Actions\SendUserNotificationAction;

/**
 * S2 spec: "leave request decision (teacher)".
 *
 * Fired for approve AND reject — a silent rejection is the worst outcome for
 * someone waiting on leave.
 */
class NotifyRequestDecisionAction
{
    public function execute(SchoolRequest $request): void
    {
        $requesterId = (int) $request->requester_id;
        if ($requesterId < 1) {
            return;
        }

        $status = $request->status instanceof SchoolRequestStatus
            ? $request->status->value
            : (string) $request->status;

        if (! in_array($status, [
            SchoolRequestStatus::Approved->value,
            SchoolRequestStatus::Rejected->value,
        ], true)) {
            return;
        }

        $type = $request->type instanceof \BackedEnum
            ? $request->type->value
            : (string) $request->type;

        app(SendUserNotificationAction::class)->execute(
            $requesterId,
            trans('notifications.request.decision_title', [
                'status' => trans('notifications.request.status.'.$status),
            ]),
            trans('notifications.request.decision_body', [
                'type' => str_replace('_', ' ', $type),
                'status' => trans('notifications.request.status.'.$status),
                'notes' => (string) ($request->review_notes ?? ''),
            ]),
            [
                'category' => 'academics',
                'request_id' => $request->id,
                'status' => $status,
            ],
        );
    }
}
