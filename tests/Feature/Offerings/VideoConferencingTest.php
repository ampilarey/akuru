<?php

use App\Domains\Offerings\Contracts\VideoConferencingInterface;
use App\Domains\Offerings\Services\BigBlueButtonVideoConferencing;
use App\Domains\Offerings\Services\NullVideoConferencing;
use Illuminate\Support\Facades\Http;

/**
 * ROADMAP §2d Level 2 — the seam, not the provider.
 *
 * What can honestly be tested here is the half that does not need a server:
 * the default binding, the promise that a missing provider degrades to Level 1
 * rather than throwing, and BigBlueButton's request construction — which is
 * deterministic (`sha1(call + query + secret)`) and therefore checkable exactly.
 *
 * What **cannot** be tested here is whether a real BigBlueButton server accepts
 * these calls and returns the field names this adapter reads. That needs
 * credentials and a host, and is recorded in STATUS as unverified.
 */
it('binds the null implementation when no provider is configured', function () {
    config()->set('offerings.video.driver', 'null');

    $video = app(VideoConferencingInterface::class);

    expect($video)->toBeInstanceOf(NullVideoConferencing::class)
        ->and($video->isConfigured())->toBeFalse();
});

it('keeps level 1 working with no provider, answering nothing rather than throwing', function () {
    $video = new NullVideoConferencing;

    // §2d: "engine and attendance must function fully in Level 1 mode if no
    // provider is configured". None of these may raise.
    expect($video->createMeeting('offering-session-1', 'Arabic', now(), 60))->toBeNull()
        ->and($video->getJoinUrl('offering-session-1', 'Aishath'))->toBeNull()
        ->and($video->getAttendance('offering-session-1'))->toBe([])
        ->and($video->getRecording('offering-session-1'))->toBeNull();
});

it('falls back to null when the driver is named but credentials are missing', function () {
    config()->set('offerings.video.driver', 'bigbluebutton');
    config()->set('offerings.video.bigbluebutton.base_url', '');
    config()->set('offerings.video.bigbluebutton.secret', '');
    app()->forgetInstance(VideoConferencingInterface::class);

    // A half-configured provider is the dangerous case: it must degrade to
    // Level 1, not to a stack trace on a teacher's schedule screen.
    expect(app(VideoConferencingInterface::class))->toBeInstanceOf(NullVideoConferencing::class);
});

it('signs a bigbluebutton request exactly as the api specifies', function () {
    $bbb = new BigBlueButtonVideoConferencing('https://bbb.example.test/bigbluebutton', 'sekret');

    $url = $bbb->endpoint('create', ['name' => 'Arabic', 'meetingID' => 'sess-7']);

    // The checksum covers the query string as sent, so it is computed from that
    // exact string rather than from a rebuilt one.
    $query = 'name=Arabic&meetingID=sess-7';
    $expected = sha1('create'.$query.'sekret');

    expect($url)->toBe('https://bbb.example.test/bigbluebutton/api/create?'.$query.'&checksum='.$expected);
});

it('issues a per-attendee join url without calling the server', function () {
    Http::fake();
    $bbb = new BigBlueButtonVideoConferencing('https://bbb.example.test/bigbluebutton', 'sekret');

    $viewer = $bbb->getJoinUrl('sess-7', 'Aishath Shifa');
    $moderator = $bbb->getJoinUrl('sess-7', 'Ustadh Ali', asModerator: true);

    expect($viewer)->toContain('role=VIEWER')
        ->and($moderator)->toContain('role=MODERATOR')
        ->and($viewer)->toContain('fullName=Aishath%20Shifa');

    // A join URL is signed and handed to the browser; nothing is fetched.
    Http::assertNothingSent();
});

it('reads attendees out of a getMeetingInfo response', function () {
    Http::fake(['*' => Http::response(<<<'XML'
        <response>
            <returncode>SUCCESS</returncode>
            <attendees>
                <attendee><userID>u1</userID><fullName>Aishath Shifa</fullName></attendee>
                <attendee><userID>u2</userID><fullName>Mariyam Ali</fullName></attendee>
            </attendees>
        </response>
        XML)]);

    $people = (new BigBlueButtonVideoConferencing('https://bbb.example.test', 'sekret'))->getAttendance('sess-7');

    expect($people)->toHaveCount(2)
        ->and($people[0]->displayName)->toBe('Aishath Shifa')
        ->and($people[1]->providerUserId)->toBe('u2');
});

it('treats a provider failure as no data rather than an exception', function () {
    // A provider outage must not take a lesson's attendance screen down.
    Http::fake(['*' => Http::response('upstream exploded', 500)]);
    $bbb = new BigBlueButtonVideoConferencing('https://bbb.example.test', 'sekret');

    expect($bbb->getAttendance('sess-7'))->toBe([])
        ->and($bbb->getRecording('sess-7'))->toBeNull()
        ->and($bbb->createMeeting('sess-7', 'Arabic', now(), 60))->toBeNull();
});

it('treats a non-SUCCESS payload as no data', function () {
    // BBB answers 200 with `returncode=FAILED` for business errors, so the HTTP
    // status alone is not enough to decide the call worked.
    Http::fake(['*' => Http::response('<response><returncode>FAILED</returncode><messageKey>checksumError</messageKey></response>')]);

    expect((new BigBlueButtonVideoConferencing('https://bbb.example.test', 'sekret'))->getAttendance('sess-7'))->toBe([]);
});
