# ADR-036: The microphone is open to this origin, and nothing else is

## Context

`SecurityHeaders` sent:

```
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

`()` is an **empty allowlist**, not a default. It denies every origin including
the page's own, and it cannot be overridden by the visitor — no browser setting
outranks a response header. So `navigator.mediaDevices.getUserMedia({audio:
true})` threw `NotAllowedError` on `/learn/pronounce` for every student, in
every browser, on every deployment.

That is the whole student surface of Arabic B (SPEC §51): the recorder, the
teacher's review queue, the training dataset, the export manifest and the model
shelf are all downstream of a student being able to record one sound, and none
of it could ever receive an attempt.

Two things kept it invisible:

- **It arrived sideways.** The header came in with the E8 student-pick-up slice,
  which has nothing to do with audio. Locking down camera and microphone reads
  as unambiguously good hygiene in a diff about collecting children.
- **Nothing named it.** There was no test on any of the security headers, so
  the value could change without a single failure. The header is also invisible
  to feature tests of the pronunciation flow, which post a file directly and
  never touch `getUserMedia`.

The error handling then made it worse. A policy block throws the same
`NotAllowedError` a visitor's own refusal throws, so `classifyRecorderError`
mapped it to "denied" and the student was told *"Allow microphone access for
this site and try again"* — advice that cannot work, aimed at a setting that
was never the problem.

## Decision

The microphone is opened to this origin, and only to this origin:

```
Permissions-Policy: geolocation=(), camera=(), microphone=(self)
```

`(self)` is not "unrestricted". Same-origin documents may *ask*; the browser
still prompts, the student may still refuse, and cross-origin frames are still
denied. It restores the state the recorder was written against.

Geolocation and camera stay `()`. Nothing in the app calls either, and a feature
that needs one should open its own door in its own slice rather than inherit it
here.

Two supporting decisions:

- **`recordingSupport()` asks the policy before offering the button.** Where a
  browser exposes `document.permissionsPolicy` or `document.featurePolicy`, a
  disallowed microphone is reported as `RECORDER_BLOCKED_BY_SITE` — *"This site
  is set up to block the microphone… please tell the school"* — rather than as
  the visitor's own refusal. The two need opposite advice, and only one of them
  is the visitor's to fix.
- **The headers get tests.** `tests/Feature/Security/PermissionsPolicyTest.php`
  asserts that this origin may ask for the microphone, that the empty-allowlist
  form never returns, that geolocation and camera stay shut, and that the other
  four headers are still sent.

## Consequences

**Easier.** Arabic B works. `/learn/pronounce` records, and everything
downstream of it — §51.16's queues, dataset, export and model shelf — can
receive real attempts for the first time. §1d is walkable, and is walked:
`scripts/smoke/pronounce.mjs`, 17/17.

**Harder, honestly.** This is a real widening of what the page may ask for. An
XSS on this origin could now request the microphone — but it would still face
the browser's own permission prompt, and an attacker with script execution on
the page has far better options than a dialog the visitor must accept. The
alternative, keeping it shut, does not trade that risk for safety; it trades a
working product for nothing, because the feature simply does not exist with the
door closed.

**Watch for.** A reverse proxy or CDN that sets its own `Permissions-Policy`
will override this and reproduce the fault on that deployment only. That is
precisely the case `RECORDER_BLOCKED_BY_SITE` exists to name out loud, and why
the message tells the student to report it rather than to change a setting.
