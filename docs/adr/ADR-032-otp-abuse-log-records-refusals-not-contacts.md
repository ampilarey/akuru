# ADR-032: The OTP abuse log records refusals, not contacts — and the limits are configuration

Date: 2026-09-12
Status: Accepted
Phase: SPEC §32 ("OTP-Ready Rules")

## Context

SPEC §32 states four rules and then, unusually for that document, the reason
they exist:

> Maximum 3 OTP sends per phone number per 15 minutes. Minimum 60-second resend
> cooldown. Maximum failed OTP verification attempts before temporary lockout.
> OTP abuse event logging for admin review. These limits should be configurable
> in system settings.
>
> This protects future Dhiraagu SMS integration from cost abuse and spam.

Every OTP send is a message somebody pays for. That last sentence makes §32 a
money rule wearing an auth rule's clothes, and it is why the gap mattered:

| §32 requires | `OtpService` did |
|---|---|
| 3 sends per number per 15 minutes | 5 per 60 minutes |
| minimum 60-second resend cooldown | 30 seconds |
| abuse event logging for admin review | nothing |
| configurable in system settings | hardcoded `const`s |

A 30-second cooldown is half the mandated floor, which doubles the achievable
send rate against an unmetered gateway that has not been connected yet. Nothing
was broken in production because there is no production SMS — which is exactly
why this is cheap to fix now and expensive to fix later.

## Decision

**1. Every limit is configuration, but none can be switched off.**
`config/otp.php` holds all six numbers, each read through a method wrapped in
`max(1, …)`. §32 says the limits must be configurable so an operator can tighten
them under attack without waiting for a deploy. It does not say they must be
disableable, and a cooldown of zero is not a configuration, it is a removal —
so the floor is 1 second and the config cannot cross it.

**2. The log stores that a refusal happened, not who was refused.** An
`otp_abuse_events` row carries a SHA-256 of the contact peppered with
`config('app.key')`, plus the last four characters, plus the kind, threshold,
observed count and time. The raw phone number or email address is never written.

Everything an admin needs to answer — is this one contact or forty? is it the
same one as yesterday? — is answerable from the hash. Storing the raw value
would add no capability and considerable liability: this is the table most
likely to be exported to CSV and mailed around while somebody investigates, and
a list of every phone number that has ever mistyped a code is a phone book.

**3. The tail is not sufficient identity, so the hash prefix is shown too.** For
a mobile number the last four digits are what lets a person recognise their own
entry. For an email address they are the end of the domain, which every address
at one provider shares — the first browser walk rendered two unrelated contacts
as identical rows reading `…test`. A 12-character prefix of the hash is
displayed beside the tail: it separates rows, is stable across days, and reveals
nothing further.

**4. The log observes; it never locks an account.** A tripped limit refuses that
one request and writes a row. It does not disable the contact, flag the user, or
feed any automatic decision. The rate limiter's own window *is* the "temporary
lockout" §32 asks for, and it expires on its own. An OTP limit is tripped far
more often by confusion than by attack — a parent on a bad connection pressing
Resend is the common case — and an automatic penalty would fall hardest on
exactly those people.

**5. Rows are grouped by contact for review, and expire after 90 days.** A flat
chronological list does not answer the question an admin has, which is *one
person or many*. Retention is a configurable 90 days, pruned by the existing
`akuru:prune-expired` sweep: the point of the log is a pattern across days, not
a permanent record of everyone who ever mistyped something.

## Consequences

- An operator can tighten the limits from the environment during an attack; they
  cannot accidentally remove a protection by zeroing it.
- An admin reviewing the log can tell one persistent contact from a spread, and
  can hand the CSV to somebody else without handing over contact details.
- Nobody can be looked up *by* phone number in this table — investigating a
  specific complaint means matching on the tail and the code, not searching. That
  is a deliberate cost of (2), and the alternative is a searchable directory of
  everyone who has failed an OTP.
- Throttling remains **per contact only**. A spread attack across many numbers is
  slowed at each number and not at all in aggregate. Adding an IP or device
  dimension needs a decision about how much a shared connection — a school
  computer lab, a household behind one NAT — may do before it looks like an
  attack; getting that threshold wrong locks out a whole building, so it is left
  to the owner rather than guessed at here.
