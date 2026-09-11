<?php

namespace App\Domains\Offerings\DTOs;

/** A finished cloud recording, ready to be ingested into the Media domain. */
final class VideoRecording
{
    public function __construct(
        public readonly string $externalKey,
        public readonly string $url,
        public readonly ?int $durationSeconds = null,
        public readonly ?\DateTimeInterface $recordedAt = null,
    ) {}
}
