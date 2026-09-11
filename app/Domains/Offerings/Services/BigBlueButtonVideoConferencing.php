<?php

namespace App\Domains\Offerings\Services;

use App\Domains\Offerings\Contracts\VideoConferencingInterface;
use App\Domains\Offerings\DTOs\VideoMeeting;
use App\Domains\Offerings\DTOs\VideoParticipant;
use App\Domains\Offerings\DTOs\VideoRecording;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BigBlueButton adapter for ROADMAP §2d Level 2.
 *
 * BBB was chosen over Zoom for the first implementation for one reason that
 * matters here: **its API is verifiable without a server.** Every call is
 * `{base}/api/{call}?{query}&checksum=sha1({call}{query}{secret})`, so the
 * request this class builds can be asserted exactly in a test. Zoom's OAuth
 * flow cannot be checked without live credentials, and an adapter nobody can
 * test is an adapter nobody should trust. The roadmap also names BBB as the
 * education-focused, self-hosted option and the Moodle-ecosystem standard.
 *
 * **Honest limitation, and it is not a small one:** the checksum, URL shape and
 * XML parsing below are unit-tested, but this class has never spoken to a real
 * BigBlueButton server. Field names in the responses are taken from the
 * published API rather than observed. Treat the first live call as the real
 * test, and expect to correct parsing details then.
 *
 * Every failure answers *nothing* rather than throwing: §2d requires the engine
 * to keep working in Level 1 mode, and a provider outage must not take a
 * lesson's attendance screen down with it.
 */
class BigBlueButtonVideoConferencing implements VideoConferencingInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $secret,
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secret !== '';
    }

    /**
     * `{base}/api/{call}?{query}&checksum=sha1({call}{query}{secret})`.
     *
     * The checksum covers the query string **as sent**, so the order of
     * parameters is part of the signature — build the query once and sign that
     * exact string rather than rebuilding it.
     */
    public function endpoint(string $call, array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $checksum = sha1($call.$query.$this->secret);

        return rtrim($this->baseUrl, '/').'/api/'.$call.'?'.$query.'&checksum='.$checksum;
    }

    public function createMeeting(string $externalKey, string $title, \DateTimeInterface $startsAt, int $durationMinutes): ?VideoMeeting
    {
        // `meetingID` is the idempotency key: BBB returns the existing meeting
        // rather than a second one, which is what the interface promises.
        $xml = $this->call('create', [
            'name' => $title,
            'meetingID' => $externalKey,
            'duration' => $durationMinutes,
            'record' => 'true',
            'autoStartRecording' => 'false',
        ]);

        if ($xml === null) {
            return null;
        }

        return new VideoMeeting(
            externalKey: $externalKey,
            providerId: (string) ($xml->internalMeetingID ?? $externalKey),
        );
    }

    public function getJoinUrl(string $externalKey, string $displayName, bool $asModerator = false): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // A join URL is signed, not fetched — the attendee's browser follows it.
        return $this->endpoint('join', [
            'fullName' => $displayName,
            'meetingID' => $externalKey,
            'role' => $asModerator ? 'MODERATOR' : 'VIEWER',
        ]);
    }

    public function getAttendance(string $externalKey): array
    {
        $xml = $this->call('getMeetingInfo', ['meetingID' => $externalKey]);

        if ($xml === null || ! isset($xml->attendees->attendee)) {
            return [];
        }

        $participants = [];
        foreach ($xml->attendees->attendee as $attendee) {
            $participants[] = new VideoParticipant(
                displayName: trim((string) $attendee->fullName),
                providerUserId: isset($attendee->userID) ? (string) $attendee->userID : null,
            );
        }

        return $participants;
    }

    public function getRecording(string $externalKey): ?VideoRecording
    {
        $xml = $this->call('getRecordings', ['meetingID' => $externalKey]);

        if ($xml === null || ! isset($xml->recordings->recording)) {
            return null;
        }

        $recording = $xml->recordings->recording[0] ?? null;
        $url = $recording?->playback?->format?->url ?? null;

        if ($url === null) {
            return null;
        }

        return new VideoRecording(
            externalKey: $externalKey,
            url: (string) $url,
        );
    }

    /** Returns parsed XML, or null for any failure at all. */
    private function call(string $call, array $params): ?\SimpleXMLElement
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)->get($this->endpoint($call, $params));

            if (! $response->successful()) {
                Log::warning('BigBlueButton call failed', ['call' => $call, 'status' => $response->status()]);

                return null;
            }

            $xml = @simplexml_load_string($response->body());

            if ($xml === false || (string) ($xml->returncode ?? '') !== 'SUCCESS') {
                Log::warning('BigBlueButton returned a non-success payload', ['call' => $call]);

                return null;
            }

            return $xml;
        } catch (\Throwable $e) {
            // A provider outage must not take the attendance screen down (§2d).
            Log::warning('BigBlueButton threw', ['call' => $call, 'message' => $e->getMessage()]);

            return null;
        }
    }
}
