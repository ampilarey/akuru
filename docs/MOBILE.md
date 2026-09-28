# Phase 5 — Capacitor mobile packaging (SPEC §50)

The mobile app is the existing responsive Inertia React PWA, wrapped in a
Capacitor shell that loads the hosted site (`capacitor.config.ts`
`server.url`). One codebase; every web deploy reaches the app instantly.

## Building (operator machine — needs Android Studio / Xcode)

```bash
npm install                       # pulls @capacitor/* added in Phase 5
npx cap add android               # once per platform
npx cap add ios                   # macOS only
CAPACITOR_SERVER_URL=https://test.akuru.edu.mv npx cap sync
npm run cap:android               # opens Android Studio → run on device
npm run cap:ios                   # opens Xcode → run on device
```

Point `CAPACITOR_SERVER_URL` at test while rehearsing; drop it (defaults
to production) for release builds. Store signing, icons/splash, and
listing assets are operator tasks.

## §50 device test checklist (operator — record results in STATUS)

- [ ] Authentication (login, OTP SMS arrival, logout)
- [ ] Lesson player (content blocks, pinned revisions)
- [ ] Media playback (audio/video blocks)
- [ ] Audio recording (pronunciation practice — mic permission prompt,
      MediaRecorder webm upload)
- [ ] File upload (writer PDF upload from the device)
- [ ] RTL + Thaana/Arabic fonts on device webviews
- [ ] Scheduled session views (`/learn/schedule`, `/teach/schedule`)
- [ ] Library reader (watermark overlay, page navigation, purchase flow —
      BML redirect must return into the shell)
- [ ] Push notifications — wired on the app side (STATUS §5jr): the shell
      registers its token at `/account/devices` after sign-in and forgets it
      at sign-out; every in-app notification fans out to the person's phones
      through `PushSenderInterface`. Needs `@capacitor/push-notifications`
      synced into the native projects (`npx cap sync`), a Firebase project
      with the Android app (google-services.json) and the iOS APNs key
      uploaded to it, and on the host `PUSH_DRIVER=fcm`, `FCM_PROJECT_ID`
      and `FCM_CREDENTIALS_PATH` (a service-account JSON kept outside the
      web root). Rehearse on staging with `PUSH_DRIVER=log` first: the
      pushes appear in the log. The person's phones are listed under
      *Your phones* on `/portal/notifications`.

## App Store review risk — Apple guideline 4.2 (read before submission)

Apple's "minimum functionality" guideline (4.2) routinely rejects apps
that are plainly a website in a WebView — which a bare server-URL shell
is. Before the iOS submission:

- Lead the listing and the first-run experience with the app-like
  capabilities: native mic recording for pronunciation practice, the
  offline page, install-free login persistence — not "browse our site".
- Push notifications are wired on the app side (see the checklist); the
  FCM/APNs keys are what remains, and they are the strongest 4.2 mitigator.
- If rejected anyway, the fallback is a config change, not a rewrite:
  drop `server.url` and ship the built assets in the binary (`webDir`
  bundling; the app then calls the API remotely). App updates go back
  through review, but 4.2 objections usually clear.
- Google Play is more lenient but holds the same lever; the same
  mitigations apply.

## Notes

- The webview needs microphone permission entries:
  - Android: `RECORD_AUDIO` in the generated manifest
  - iOS: `NSMicrophoneUsageDescription` in Info.plist
- BML checkout redirects leave the origin; Capacitor keeps navigation
  inside the shell for the same host only — verify the return URL lands
  back on the app origin (it does: `payments.bml.return` is same-host).
- The PWA service worker and offline page keep working inside the shell.
