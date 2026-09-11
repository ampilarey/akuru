<?php

namespace App\Domains\Offerings\Contracts;

use App\Domains\Offerings\DTOs\VideoMeeting;
use App\Domains\Offerings\DTOs\VideoParticipant;
use App\Domains\Offerings\DTOs\VideoRecording;

/**
 * The seam between an offering session and whatever hosts it.
 *
 * ROADMAP §2d puts both levels of live classes behind this interface so the
 * provider choice stays reversible: Level 1 is a teacher pasting a meeting link
 * onto a session (already shipped — `course_offering_sessions.online_meeting_url`),
 * Level 2 is the provider creating the meeting, reporting who attended, and
 * handing back a recording.
 *
 * **Level 1 must keep working when no provider is configured** (§2d). That is
 * why `NullVideoConferencing` is the default binding rather than an exception:
 * a school with a pasted Zoom link is a supported configuration, not a broken
 * one, and nothing in the engine may assume a provider exists.
 *
 * Rule 4: no SDK reaches domain logic. Implementations own their transport.
 */
interface VideoConferencingInterface
{
    /**
     * Create (or return) the meeting for a session.
     *
     * Implementations must be idempotent on `$externalKey` — a session that is
     * saved twice must not produce two meetings.
     */
    public function createMeeting(string $externalKey, string $title, \DateTimeInterface $startsAt, int $durationMinutes): ?VideoMeeting;

    /**
     * The URL this person should open. Providers issue per-attendee URLs, so
     * this is deliberately not a property of the meeting.
     */
    public function getJoinUrl(string $externalKey, string $displayName, bool $asModerator = false): ?string;

    /**
     * Who actually attended, for auto-marking session attendance.
     *
     * @return list<VideoParticipant>
     */
    public function getAttendance(string $externalKey): array;

    /**
     * The cloud recording, once the provider has finished processing it.
     * Null means "not ready or not recorded" — never an error.
     */
    public function getRecording(string $externalKey): ?VideoRecording;

    /** Whether a real provider is behind this binding. */
    public function isConfigured(): bool;
}
