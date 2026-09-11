<?php

namespace App\Domains\Offerings\DTOs;

/** A meeting as the provider knows it. */
final class VideoMeeting
{
    public function __construct(
        public readonly string $externalKey,
        public readonly string $providerId,
        public readonly ?string $moderatorUrl = null,
        public readonly ?string $attendeeUrl = null,
    ) {}
}
