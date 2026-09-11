<?php

namespace App\Domains\Offerings\Services;

use App\Domains\Offerings\Contracts\VideoConferencingInterface;
use App\Domains\Offerings\DTOs\VideoMeeting;
use App\Domains\Offerings\DTOs\VideoRecording;

/**
 * The default binding: no provider configured, and that is a supported state.
 *
 * ROADMAP §2d is explicit that "engine and attendance must function fully in
 * Level 1 mode if no provider is configured". So every method answers *nothing*
 * rather than throwing. A school that pastes a Zoom link onto a session is
 * running the product as designed, and nothing downstream may treat the absence
 * of a provider as an error.
 *
 * The one method that tells the truth is `isConfigured()`, so a caller can
 * decide whether to offer Level 2 affordances at all.
 */
class NullVideoConferencing implements VideoConferencingInterface
{
    public function createMeeting(string $externalKey, string $title, \DateTimeInterface $startsAt, int $durationMinutes): ?VideoMeeting
    {
        return null;
    }

    public function getJoinUrl(string $externalKey, string $displayName, bool $asModerator = false): ?string
    {
        return null;
    }

    public function getAttendance(string $externalKey): array
    {
        return [];
    }

    public function getRecording(string $externalKey): ?VideoRecording
    {
        return null;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
