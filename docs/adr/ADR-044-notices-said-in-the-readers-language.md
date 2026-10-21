# ADR-044: A notice is said in the reader's language, when it is read

## Context

An in-app notification was written once, when it was sent, in the
language of the request that sent it. A nightly job runs in English, so a
cart reminder read "Still thinking it over?" to a family that reads
Dhivehi. A notice the office sends from `/en` reaches a parent in English
the same way. STATUS §5pf (2026-10-08) named the choice and left it open:
- keep a language on the account; or
- look the notice's own words up when it is read.

The household audit (2026-10-21, STATUS §5tv) found the reminder doing
exactly this. The owner's instruction for the open items was "based on ur
thinking complete all".

## Decision

The in-app notice looks its words up when it is read.

- **Sending:** `App\Support\Said::of('book.key', [...])` stands in for
  `__()`. It keeps the phrase's key and its values. A value can be a `Said`
  too, such as a state's name inside a sentence. `Said::join()` keeps a
  sentence and the lines a case adds to it.
- **Storing:** `SendUserNotificationAction` takes `string|Said`. A `Said`
  is kept in `data['said']`. The row's `title` and `message` still hold
  the words in the sender's language.
- **Showing:** `ListUserNotificationsAction`, the one reader, says a kept
  phrase in the page's language. A row without one, or whose key has gone,
  shows its text as written.
- **Email, SMS and push** leave when the notice is sent, so they go in the
  sender's language. Each notifier turns a `Said` into text for those
  channels.

## Consequences

- No setting, and no migration: a notice reads in whatever language the
  reader opens the inbox in, and switching language changes it.
- Rows written before this stay as they were.
- Senders change one call at a time, `__(` to `Said::of(`:
  - HP3a: the Bookstore and Lending.
  - HP3b: the Library, whose notices are partly English literals with no key.
  - HP3c: the school's senders.

  A sender still passing a plain string keeps working as before.
- A value that must be said in the reader's language must be a `Said`
  itself. A plain string value, such as a name, a number or a link, stays as
  given.
- **Email and SMS, added 2026-10-21 (LA1, STATUS §5ug):**
  - The account keeps a language, `users.locale`: the one its person last
    opened a page in, written by `RememberAccountLanguage`.
  - An email or SMS to a person is written in it, the mail's own frame too
    (a queued mailable's `locale`). An account with none gets the sender's
    language.
  - Office addresses and numbers get the sender's language.

  So §5pf's two halves are both taken: the inbox says a notice in the
  page's language, and the channels that leave at once say it in the
  person's.
