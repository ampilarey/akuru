<?php

/**
 * SPEC §6.3 "Mobile App Path" and §6.4 "Platform Abstraction Layer":
 *
 *   > Keep web/native differences behind a single platform abstraction layer.
 *   > **Do not scatter browser/native detection logic across components.**
 *   > Audio recording must use a **replaceable recorder interface**.
 *   > A Capacitor native audio plugin can replace it later behind the same
 *   > interface.
 *   >
 *   > **Do not spread platform-specific logic through React components.**
 *
 * The layer did not exist. `navigator.mediaDevices.getUserMedia()` and
 * `new MediaRecorder(stream)` were called from inside
 * `Pages/Pronunciation/Practice.jsx`, so there was no interface for a native
 * plugin to replace — the seam §6.3 describes had nowhere to be.
 *
 * Only one file had strayed, which is exactly when a rule like this is worth
 * writing down: the cost of keeping it is currently zero, and §36's audio
 * upload (shipped earlier the same day) is the sort of second entry point that
 * starts the scatter §6.3 warns about.
 *
 * **This is a placement rule, not a ban.** `resources/js/Platform/` is where
 * these APIs belong; anywhere else under `resources/js/` they are a defect.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('keeps browser-only APIs inside the platform layer', function () {
    // §6.4's list — audio recording, file picking, download handling, device
    // capability detection, storage helpers — as the APIs that implement them.
    $browserOnly = [
        'MediaRecorder',
        'navigator.mediaDevices',
        'navigator.permissions',
        'navigator.share',
        'navigator.clipboard',
        'localStorage',
        'sessionStorage',
        'indexedDB',
    ];

    $layer = base_path('resources/js/Platform');
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('resources/js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['js', 'jsx'], true)) {
            continue;
        }
        if (str_starts_with($file->getPathname(), $layer)) {
            continue;
        }

        $source = stripJsComments((string) file_get_contents($file->getPathname()));
        foreach ($browserOnly as $api) {
            if (str_contains($source, $api)) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).' → '.$api;
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        "These files reach for a browser-only API outside the platform layer:\n  "
        .implode("\n  ", $offenders)
        ."\n\nSPEC §6.3: \"Keep web/native differences behind a single platform "
        .'abstraction layer" and "Do not scatter browser/native detection logic across '
        .'components." §6.4: "Do not spread platform-specific logic through React '
        ."components.\"\n\nAdd it to resources/js/Platform/ and import from there, so a "
        .'Capacitor plugin can replace it later behind the same interface.'
    );
});

it('gives the recorder a replaceable interface and a reason when it cannot run', function () {
    $source = (string) file_get_contents(base_path('resources/js/Platform/recorder.js'));

    // §6.3: "Audio recording must use a replaceable recorder interface."
    // `createRecorder` is the seam a native plugin swaps at.
    foreach (['createRecorder', 'start', 'stop', 'cancel'] as $member) {
        expect($source)->toContain($member);
    }

    // §6.3: "Avoid browser-only APIs without fallbacks." The old page answered
    // a denied microphone with `} catch { setRecording(false); }` — a Record
    // button that did nothing and said nothing.
    expect($source)->toContain('describeRecordingFailure');

    $page = (string) file_get_contents(base_path('resources/js/Pages/Pronunciation/Practice.jsx'));
    expect($page)->toContain('describeRecordingFailure');
    expect($page)->toContain('recordingSupport');
});
