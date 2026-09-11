<?php

namespace App\Domains\Offerings\DTOs;

/**
 * One person's presence in a meeting, as the provider reports it.
 *
 * `displayName` is how the engine matches a participant back to a student, and
 * it is the weak link in auto-attendance: people rename themselves. Matching
 * policy belongs to the Action that consumes this, not here.
 */
final class VideoParticipant
{
    public function __construct(
        public readonly string $displayName,
        public readonly ?string $providerUserId = null,
        public readonly ?\DateTimeInterface $joinedAt = null,
        public readonly ?\DateTimeInterface $leftAt = null,
    ) {}
}
