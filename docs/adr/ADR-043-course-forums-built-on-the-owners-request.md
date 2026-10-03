# ADR-043: Course forums are built now, on the owner's request

## Context

ROADMAP §7 and §8.3 parked discussion forums behind a revisit trigger: review
forums and Q&A "when the first fully-online cohort completes", against
retention data. On 2026-10-03 the owner asked whether Akuru has Moodle's
course-building tools, was told which it lacks (forums among them), and said
"Yes build" (BACKLOG C18).

## Decision

The owner's request is the decision the trigger was waiting for. Every
engine course has a forum (Moodle parity slice M3, STATUS §5oj):

- It belongs to the course's people only: learners with an active, approved
  or completed enrolment, the course's assigned teachers, and whoever holds
  `courses.manage`. Not the public, not other courses' learners.
- Teachers moderate: pin, lock, hide. Hiding is reversible; nothing is
  deleted by a moderator.
- New topics tell the course's teachers; replies tell the topic's starter
  and earlier repliers, through the one notification writer, under a new
  `courses` category a person can switch off.
- Topics and replies carry the academic year they were written in (rule 10).

## Consequences

- Gamification and badges, parked with forums in §8.3, stay parked.
- No anonymous posting, no attachments, no editing of one's own post, no
  per-offering (batch) forums yet. A course run in several batches shares
  one forum. Each is a later decision if asked for.
