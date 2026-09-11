# ADR-028: Live classes Level 2 pulled forward — interface and BigBlueButton adapter

Date: 2026-09-12
Status: Accepted
Phase: ROADMAP §2d (Level 2, scheduled post-Phase 2)

## Context

ROADMAP §2d schedules live classes Level 2 — the provider creates the meeting,
participant logs auto-mark attendance, cloud recordings become lesson content —
for after Phase 2, on the assumption that face-to-face and hybrid stay the
primary delivery. It also says, in terms:

> **Pull-forward trigger (decision point, record an ADR if taken):** … the
> *decision* must be taken consciously, not discovered under load.

This ADR is that record. The owner asked for the remaining coding to be
completed and, asked specifically about §2d, chose to build the interface **and**
a provider adapter.

Level 1 already ships: `course_offering_sessions.online_meeting_url`, saved and
validated through `SaveOfferingSessionAction`, surfaced on schedules.

## Decision

**1. `VideoConferencingInterface` in Offerings**, with the four operations §2d
names — `createMeeting`, `getJoinUrl`, `getAttendance`, `getRecording` — plus
`isConfigured()` so callers can decide whether to offer Level 2 affordances at
all. Rule 4: no SDK reaches domain logic.

**2. `NullVideoConferencing` is the default binding, and that is a supported
production state.** §2d requires the engine and attendance to "function fully in
Level 1 mode if no provider is configured", so every method answers *nothing*
rather than throwing. A school pasting a Zoom link onto a session is running the
product as designed. The binding also falls back to null when a driver is named
but its credentials are missing — a half-configured provider is the dangerous
case, and it must degrade to Level 1 rather than to a stack trace on a teacher's
schedule.

**3. BigBlueButton is the first adapter, chosen over Zoom for testability.**
Every BBB call is `{base}/api/{call}?{query}&checksum=sha1(call+query+secret)` —
deterministic, and therefore assertable exactly in a unit test without a server.
Zoom's OAuth flow cannot be checked without live credentials, and an adapter
nobody can test is an adapter nobody should trust. §2d already names BBB as the
education-focused, self-hosted, Moodle-ecosystem option.

**4. Provider failures are not exceptions.** Any non-200, any non-`SUCCESS`
payload, any thrown transport error logs a warning and returns null or an empty
list. A provider outage must not take a lesson's attendance screen down.

## What this ADR does NOT claim

**The adapter has never spoken to a real BigBlueButton server.** The checksum,
URL shape, XML parsing and failure handling are unit-tested; the response field
names are taken from the published API rather than observed. The first live call
is the real test, and parsing details should be expected to need correction
then. This is recorded rather than buried because a green test suite here proves
the request is well-formed, not that the integration works.

Nothing is wired into the engine yet either: no session auto-creates a meeting,
no participant log marks attendance, no recording is ingested into Media. Those
are the consuming slices, and each needs the adapter verified against a real
host first.

## Consequences

- The provider choice stays reversible, which was §2d's whole reason for
  requiring an interface: swapping BigBlueButton for Jitsi or Zoom is a new
  implementation and a config change.
- Level 1 is untouched and remains the default everywhere.
- `config/offerings.php` gains `video.driver`, `video.timeout_seconds` and the
  BigBlueButton credentials, all env-driven and all defaulting to off.
- The auto-attendance matching policy is deliberately absent. Providers report a
  display name, people rename themselves, and deciding when a name is a student
  is a judgement that belongs in an Action with its own tests — not in a
  transport adapter.
