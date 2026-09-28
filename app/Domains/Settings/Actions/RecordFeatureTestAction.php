<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\FeatureTestNote;
use Illuminate\Validation\ValidationException;

/**
 * Records one test of one feature: working, broken or blocked, with a
 * comment. Each call adds a row; the earlier ones stay as the feature's
 * history (the owner, 2026-09-28: "tick and comment and later can be seen").
 */
class RecordFeatureTestAction
{
    public function execute(string $itemKey, string $status, ?string $comment, int $userId): FeatureTestNote
    {
        if (! in_array($itemKey, ListFeatureWalkthroughAction::itemKeys(), true)) {
            throw ValidationException::withMessages(['item' => 'Unknown feature.']);
        }
        if (! in_array($status, FeatureTestNote::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose works, broken or blocked.']);
        }
        $comment = trim((string) $comment);
        if ($status !== 'works' && $comment === '') {
            throw ValidationException::withMessages(['comment' => 'Say what is broken or what it is waiting for.']);
        }

        return FeatureTestNote::query()->create([
            'item_key' => $itemKey,
            'status' => $status,
            'comment' => $comment === '' ? null : mb_substr($comment, 0, 2000),
            'user_id' => $userId,
        ]);
    }
}
