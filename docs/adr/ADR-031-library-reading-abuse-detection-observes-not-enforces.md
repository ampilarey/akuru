# ADR-031: Library reading-abuse detection observes; it does not enforce, and it does not store identities

Date: 2026-09-12
Status: Accepted
Phase: L2b (LIBRARY_PLAN §9.2, §30.3; ROADMAP §9)

## Context

`LIBRARY_PLAN` §9.2 lists what the protected reader must do to make copying
hard. L2 shipped most of it — private storage, no download path, a per-request
permission check, a dynamic per-page watermark. Four clauses were never built:

> detect rapid page opening, detect multi-device/session abuse, limit
> simultaneous sessions, log suspicious activity

§30.3 repeats them, and §29 lists "suspicious activity" among the things the
admin dashboard shows. None of it existed: there were zero references to abuse,
suspicion or session limits anywhere in `Library` or `Commerce`.

This is a gap in a **shipped** slice rather than future-phase work, which is why
it is built now rather than deferred behind the L-track hold rule.

## Decision

**1. Identity is hashed on the way in; the raw value never reaches the
database.** Every question §30.3 asks — is this the same device? how many at
once? — is answerable from a hash, so storing an IP address or user-agent string
would add no capability and considerable liability. **Some readers here are
children.** §30 is a security section, not a surveillance one.

The hash is peppered with `config('app.key')`, which is what makes it
irreversible in practice: the IPv4 space is small enough to enumerate against a
bare SHA-256 in seconds.

Device identity is the **pair** (address + agent), not either half. Two people
behind one office NAT are then two devices, and the same browser on a new
address is also two — the conservative direction in both cases, because the cost
of merging two readers into one identity is worse than the cost of splitting one
across two.

**2. Anonymous reading is not logged at all.** An event with no reader answers
none of §30.3's questions, so free public content read by a visitor produces no
row. Logging it would be surveillance for its own sake.

**3. Detection raises a question, never a verdict.** A reader skimming a
reference book legitimately turns pages fast; a family sharing one account
across a phone, a tablet and a laptop is not a book being resold. An alert
records what was observed against which threshold and waits for a person.
Alerts de-duplicate while open — a reader who flips pages fast for ten minutes
is one concern, not six hundred rows — and the open row keeps the **worst**
reading rather than the latest, because how far past the line it went is what a
reviewer needs.

**4. Enforcement is off by default, and that is the substantive decision here.**
§9.2 does say "limit simultaneous sessions", and `shouldBlock()` implements
exactly that. It is gated on `library.abuse.enforce`, default false.

Blocking on a heuristic refuses a paying reader a book they own. The thresholds
that would decide it are guesses about human behaviour that nobody has yet
checked against a real reader, and a false positive here does not inconvenience
someone — it accuses them of theft. Detection is useful from the first day;
enforcement should wait for evidence the numbers are right. Every threshold is
env-driven so that evidence can change them without a deploy.

**5. Events expire; decisions do not.** Reading events are pruned by age
(default 90 days) through the existing `akuru:prune-expired` command — they
answer "what happened lately", and keeping a child's page-by-page history
indefinitely serves nothing the plan asks for. Alerts are **never** pruned: an
alert is a decision somebody took, and a pattern of dismissals is itself worth
seeing.

**6. The queue is not in the navigation.** It is opened from the Library admin
hub. A list that accuses readers of theft should take a decision to open, not
sit in a menu beside sales figures where it gets skimmed. The reachability guard
records it in `$allowed` naming that parent, rather than being silently exempt.

## What this ADR does NOT claim

**The thresholds are guesses.** 40 pages a minute, 5 devices a day, 3 concurrent
sessions — none of these came from observing an Akuru reader, because there are
no Akuru readers yet. They are set deliberately loose and every one is
env-driven. Expect them all to move once there is real traffic, and expect the
first few alerts to be legitimate readers.

**Detection is not prevention.** §9.2 is explicit that this class of measure
"reduces copying; cannot stop screenshots/cameras". Nothing here changes that.
A determined person with a phone camera defeats every measure in this ADR, and
the point is to make casual redistribution inconvenient, not to make copying
impossible.

## Consequences

- `library_reading_events` (append-only, pruned by age) and
  `library_reading_alerts` (kept). Both carry `academic_year_id` (rule 10).
- The reader records an event and evaluates signals **after** the access gate,
  never before: a refused request is not a read, and counting it would let a
  locked-out reader trip their own alert.
- `config/library.php` gains an `abuse` block; every value is env-driven and
  documented as a guess rather than an observation.
- Both models registered in `config/morph-map.php` (ADR-005).
- Reader names on the admin screen come from `DB::table('users')`, the shape
  four other domains already use — a display name is not worth a cross-domain
  model import (rule 3).
