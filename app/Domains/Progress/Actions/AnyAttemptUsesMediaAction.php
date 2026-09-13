<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\ActivityAttempt;

/**
 * Is this private media file something a student attached to an attempt?
 *
 * Asked by the media endpoint's staff check, so a reviewer can open what a
 * student handed in without that meaning "may open every file in the
 * application". Progress owns attempts and their `answers` JSON, so Progress
 * answers the question — a Courses action reaching for `ActivityAttempt`
 * directly is rule 3's boundary, and `Phase1ABoundariesTest` says so out loud.
 *
 * Attachments are stored by `AttachAttemptMediaAction` as objects keyed
 * `id`/`mime`/`original_name` under `answers->attachments`, so containment is
 * asked of the database rather than by loading every attempt in the school.
 */
class AnyAttemptUsesMediaAction
{
    public function execute(int $mediaId): bool
    {
        return ActivityAttempt::query()
            ->whereRaw(
                "JSON_CONTAINS(JSON_EXTRACT(answers, '$.attachments'), JSON_OBJECT('id', ?))",
                [$mediaId],
            )
            ->exists();
    }
}
